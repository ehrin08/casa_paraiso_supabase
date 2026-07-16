[CmdletBinding()]
param(
    [Parameter(Position = 0)]
    [ValidateSet('start', 'stop', 'status', 'compose')]
    [string] $Action = 'status',
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]] $ComposeArguments
)

Import-Module (Join-Path $PSScriptRoot 'CasaDocker.psm1') -Force

switch ($Action) {
    'start' {
        Invoke-CasaCompose -EnsureEngine -Arguments @('up', '-d') | Write-Output
    }
    'stop' {
        Invoke-CasaCompose -Arguments @('--profile', 'tunnel', 'stop') | Write-Output
    }
    'status' {
        Assert-CasaDockerEngine
        Write-Output 'Docker engine: Docker Desktop (desktop-linux)'
        Write-Output "Compose project: $(Get-CasaComposeProjectName)"
        Invoke-CasaCompose -Arguments @('ps', '--all') | Write-Output
    }
    'compose' {
        if (-not $ComposeArguments) {
            throw 'Usage: .\scripts\casa-docker.ps1 compose <docker compose arguments>'
        }

        Invoke-CasaCompose -EnsureEngine -Arguments $ComposeArguments | Write-Output
    }
}
