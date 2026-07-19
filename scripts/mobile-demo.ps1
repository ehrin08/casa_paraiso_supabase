[CmdletBinding()]
param(
    [ValidateSet('Start', 'Rotate', 'Status', 'Stop')]
    [string] $Action = 'Status'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'CasaDocker.psm1') -Force

$projectRoot = Get-CasaProjectPath
$envPath = Join-Path $projectRoot '.env'
$statePath = Join-Path $projectRoot 'storage\app\private\mobile-demo-state.json'
$managedKeys = @('APP_URL', 'APP_DEBUG', 'SESSION_SECURE_COOKIE', 'TRUSTED_HOSTS', 'MOBILE_PAIRING_ENABLED', 'MOBILE_INSTANCE_ID', 'MOBILE_DEMO_APK_ENABLED')

function Get-DotEnvState {
    param([string] $Key)

    $line = Get-Content $envPath | Where-Object { $_ -match "^$([regex]::Escape($Key))=" } | Select-Object -First 1
    if ($null -eq $line) { return @{ exists = $false; value = $null } }

    return @{ exists = $true; value = ($line -replace "^$([regex]::Escape($Key))=", '') }
}

function Set-DotEnvValue {
    param([string] $Key, [string] $Value, [switch] $Remove)

    $lines = [System.Collections.Generic.List[string]](Get-Content $envPath)
    $pattern = "^$([regex]::Escape($Key))="
    $index = -1
    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match $pattern) { $index = $i; break }
    }

    if ($Remove) {
        if ($index -ge 0) { $lines.RemoveAt($index) }
    } elseif ($index -ge 0) {
        $lines[$index] = "$Key=$Value"
    } else {
        $lines.Add("$Key=$Value")
    }

    Set-Content -LiteralPath $envPath -Value $lines -Encoding utf8
}

function Save-EnvironmentState {
    if (Test-Path $statePath) { return }

    $state = @{}
    foreach ($key in $managedKeys) { $state[$key] = Get-DotEnvState $key }
    New-Item -ItemType Directory -Force -Path (Split-Path $statePath) | Out-Null
    $state | ConvertTo-Json -Depth 3 | Set-Content -LiteralPath $statePath -Encoding utf8
}

function Restore-EnvironmentState {
    if (-not (Test-Path $statePath)) { return }

    $state = Get-Content $statePath -Raw | ConvertFrom-Json
    foreach ($key in $managedKeys) {
        $entry = $state.$key
        if ($entry.exists) {
            Set-DotEnvValue -Key $key -Value $entry.value
        } else {
            Set-DotEnvValue -Key $key -Remove
        }
    }

    Remove-Item -LiteralPath $statePath -Force
}

function Ensure-InstanceId {
    $current = Get-DotEnvState 'MOBILE_INSTANCE_ID'
    if (-not $current.exists -or [string]::IsNullOrWhiteSpace($current.value)) {
        Set-DotEnvValue -Key 'MOBILE_INSTANCE_ID' -Value ([guid]::NewGuid().ToString())
    }
}

function Get-TunnelUrl {
    param([string] $Since)

    for ($attempt = 0; $attempt -lt 45; $attempt++) {
        $logs = Invoke-CasaCompose -Arguments @('logs', '--no-color', '--since', $Since, 'cloudflared')
        $urls = [regex]::Matches(($logs -join "`n"), 'https://[a-z0-9-]+\.trycloudflare\.com') | ForEach-Object Value
        if ($urls) { return $urls | Select-Object -Last 1 }
        if (($logs -join "`n") -match 'failed to (unmarshal|request).*quick tunnel|error unmarshaling quicktunnel') {
            throw 'Cloudflare rejected the Quick Tunnel request.'
        }
        Start-Sleep -Seconds 1
    }

    throw 'Cloudflared did not publish a Quick Tunnel URL within 45 seconds.'
}

function Start-QuickTunnel {
    for ($attempt = 1; $attempt -le 3; $attempt++) {
        try {
            Invoke-CasaCompose -Arguments @('--profile', 'tunnel', 'rm', '-s', '-f', 'cloudflared') | Out-Null
            $startedAt = (Get-Date).ToUniversalTime().AddSeconds(-1).ToString('o')
            Invoke-CasaCompose -Arguments @('--profile', 'tunnel', 'up', '-d', '--force-recreate', 'cloudflared') | Out-Null
            return Get-TunnelUrl -Since $startedAt
        } catch {
            if ($attempt -eq 3) { throw }
            Start-Sleep -Seconds 2
        }
    }
}

function Wait-MobileMeta {
    param([string] $Url)

    for ($attempt = 0; $attempt -lt 15; $attempt++) {
        Start-Sleep -Seconds 2
        try {
            return Invoke-RestMethod -Uri "$Url/api/v1/meta" -Headers @{ Accept = 'application/json' } -TimeoutSec 10
        } catch {
            if ($attempt -eq 14) { throw }
        }
    }
}

function Invoke-Laravel {
    param([string[]] $Arguments)
    return Invoke-CasaCompose -Arguments (@('exec', '-T', '--user', 'sail', 'laravel.test') + $Arguments)
}

function Configure-TunnelEnvironment {
    param([string] $Url)

    $tunnelHost = ([uri]$Url).Host
    $escapedHost = [regex]::Escape($tunnelHost)
    Set-DotEnvValue -Key 'APP_URL' -Value $Url
    Set-DotEnvValue -Key 'APP_DEBUG' -Value 'false'
    Set-DotEnvValue -Key 'SESSION_SECURE_COOKIE' -Value 'true'
    Set-DotEnvValue -Key 'TRUSTED_HOSTS' -Value "^localhost$,^127\.0\.0\.1$,^laravel\.test$,^$escapedHost$"
    Set-DotEnvValue -Key 'MOBILE_PAIRING_ENABLED' -Value 'true'
    Set-DotEnvValue -Key 'MOBILE_DEMO_APK_ENABLED' -Value 'false'
    Invoke-Laravel @('php', 'artisan', 'config:clear') | Out-Null
}

if (-not (Test-Path $envPath)) {
    throw '.env is missing. Copy .env.example, set APP_KEY, and complete the normal Laravel dependency setup first.'
}

switch ($Action) {
    'Start' {
        Save-EnvironmentState
        Ensure-InstanceId
        try {
            Invoke-CasaCompose -EnsureEngine -Arguments @('up', '-d', 'laravel.test', 'mariadb', 'mailpit') | Out-Null
            $url = Start-QuickTunnel
            Configure-TunnelEnvironment $url
            $meta = Wait-MobileMeta -Url $url
            if ($meta.data.service -ne 'casa-paraiso-mobile-api' -or -not $meta.data.pairing.enabled) { throw 'The tunnel did not return a pairing-enabled mobile API.' }
            Write-Output "Tunnel URL: $url"
            Write-Output 'Browser/API development tunnel is active; Android builds use the Render endpoint.'
            Write-Output "Google web callback: $url/auth/google/callback"
            Write-Output "Google mobile callback: $url/auth/google/mobile/callback"
        } catch {
            try { Invoke-CasaCompose -Arguments @('--profile', 'tunnel', 'stop', 'cloudflared') | Out-Null } catch { }
            Restore-EnvironmentState
            try { Invoke-Laravel @('php', 'artisan', 'config:clear') | Out-Null } catch { }
            throw
        }
    }
    'Rotate' {
        Invoke-CasaCompose -EnsureEngine -Arguments @('--profile', 'tunnel', 'rm', '-s', '-f', 'cloudflared') | Out-Null
        & $PSCommandPath -Action Start
    }
    'Status' {
        & (Join-Path $PSScriptRoot 'casa-docker.ps1') status
        if (Test-Path $statePath) { Write-Output 'Tunnel configuration is active.' } else { Write-Output 'Tunnel configuration is inactive.' }
    }
    'Stop' {
        try { Invoke-CasaCompose -Arguments @('--profile', 'tunnel', 'stop', 'cloudflared') | Out-Null } finally {
            Restore-EnvironmentState
            try { Invoke-Laravel @('php', 'artisan', 'config:clear') | Out-Null } catch { }
            & (Join-Path $PSScriptRoot 'casa-docker.ps1') stop
        }
    }
}
