Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$script:DistroName = 'CasaParaisoDocker'
$script:EngineLabel = 'dedicated'
$script:KeeperPath = Join-Path $PSScriptRoot '..\storage\app\private\casa-docker-keeper.pid'

function Get-CasaProjectPath {
    return (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
}

function Test-CasaDistro {
    $names = ((& wsl.exe --list --quiet) -join "`n") -replace [char]0, ''

    return ($names -split "`r?`n" | ForEach-Object { $_.Trim() }) -contains $script:DistroName
}

function Invoke-CasaWsl {
    param(
        [Parameter(Mandatory)] [string[]] $Arguments,
        [ValidateSet('casa', 'root')] [string] $User = 'casa',
        [switch] $IgnoreExitCode
    )

    if (-not (Test-CasaDistro)) {
        throw "The $script:DistroName WSL distribution is not installed. Run .\scripts\provision-casa-docker.ps1 -Action Install first."
    }

    $previousErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & wsl.exe --distribution $script:DistroName --user $User -- @Arguments 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorAction
    }
    $output = @($output | ForEach-Object { $_.ToString() })

    if ($exitCode -ne 0 -and -not $IgnoreExitCode) {
        throw "WSL command failed ($exitCode): $($output -join [Environment]::NewLine)"
    }

    return $output
}

function ConvertTo-CasaWslPath {
    param([Parameter(Mandatory)] [string] $Path)

    $resolved = (Resolve-Path $Path).Path
    if ($resolved -notmatch '^([A-Za-z]):\\(.*)$') {
        throw "Only absolute Windows drive paths can be converted: $resolved"
    }

    $drive = $matches[1].ToLowerInvariant()
    $relative = $matches[2] -replace '\\', '/'
    return "/mnt/$drive/$relative"
}

function Start-CasaDockerEngine {
    Start-CasaDistroKeeper
    Invoke-CasaWsl -User root -Arguments @('systemctl', 'start', 'docker') | Out-Null
    Assert-CasaDockerEngine
}

function Stop-CasaDockerEngine {
    try {
        Invoke-CasaWsl -User root -Arguments @('systemctl', 'stop', 'docker') | Out-Null
    } finally {
        Stop-CasaDistroKeeper
    }
}

function Start-CasaDistroKeeper {
    if (Test-Path $script:KeeperPath) {
        $keeperId = [int](Get-Content -LiteralPath $script:KeeperPath -Raw)
        if (Get-Process -Id $keeperId -ErrorAction SilentlyContinue) { return }
        Remove-Item -LiteralPath $script:KeeperPath -Force
    }

    New-Item -ItemType Directory -Force -Path (Split-Path $script:KeeperPath) | Out-Null
    $keeper = Start-Process -FilePath 'wsl.exe' -ArgumentList @('--distribution', $script:DistroName, '--user', 'casa', '--', 'sleep', 'infinity') -WindowStyle Hidden -PassThru
    Set-Content -LiteralPath $script:KeeperPath -Value $keeper.Id -Encoding ascii
    Start-Sleep -Milliseconds 500
}

function Stop-CasaDistroKeeper {
    if (-not (Test-Path $script:KeeperPath)) { return }

    $keeperId = [int](Get-Content -LiteralPath $script:KeeperPath -Raw)
    Stop-Process -Id $keeperId -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $script:KeeperPath -Force
}

function Assert-CasaDockerEngine {
    $rawLabels = (Invoke-CasaWsl -Arguments @('docker', 'info', '--format', '{{json .Labels}}') | Select-Object -Last 1).Trim()

    if ($rawLabels -notmatch ('"com\.casaparaiso\.engine=' + [regex]::Escape($script:EngineLabel) + '"')) {
        throw "Refusing to run against an unexpected Docker daemon. Expected com.casaparaiso.engine=$script:EngineLabel, received '$rawLabels'."
    }
}

function Invoke-CasaCompose {
    param(
        [Parameter(Mandatory)] [string[]] $Arguments,
        [switch] $EnsureEngine
    )

    if ($EnsureEngine) {
        Start-CasaDockerEngine
    } else {
        Assert-CasaDockerEngine
    }

    $projectPath = ConvertTo-CasaWslPath (Get-CasaProjectPath)
    $argumentSetup = @('set --')
    foreach ($argument in $Arguments) {
        $encodedArgument = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($argument))
        $argumentSetup += 'set -- "$@" "$(printf ''%s'' ''' + $encodedArgument + ''' | base64 -d)"'
    }
    $argumentSetup += 'exec docker compose "$@"'
    $scriptPayload = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes(($argumentSetup -join "`n")))
    $runner = "printf '%s' '$scriptPayload' | base64 -d | sh"
    $command = @('--distribution', $script:DistroName, '--user', 'casa', '--cd', $projectPath, '--', 'sh', '-lc', $runner)
    $previousErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & wsl.exe @command 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorAction
    }
    $output = @($output | ForEach-Object { $_.ToString() })

    if ($exitCode -ne 0) {
        throw "Docker Compose failed ($exitCode): $($output -join [Environment]::NewLine)"
    }

    return $output
}

Export-ModuleMember -Function Get-CasaProjectPath, Test-CasaDistro, Invoke-CasaWsl, ConvertTo-CasaWslPath, Start-CasaDockerEngine, Stop-CasaDockerEngine, Assert-CasaDockerEngine, Invoke-CasaCompose
