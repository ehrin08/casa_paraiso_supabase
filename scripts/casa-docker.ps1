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
        Invoke-CasaCompose -Arguments @('stop') | Write-Output
        Stop-CasaDockerEngine
    }
    'status' {
        if (-not (Test-CasaDistro)) {
            Write-Output 'CasaParaisoDocker is not installed.'
            exit 1
        }

        $service = Invoke-CasaWsl -User root -Arguments @('systemctl', 'is-active', 'docker') -IgnoreExitCode
        Write-Output "Docker service: $(($service -join '').Trim())"

        if ((($service -join '').Trim()) -eq 'active') {
            Assert-CasaDockerEngine
            Invoke-CasaCompose -Arguments @('ps', '--all') | Write-Output
        }
    }
    'compose' {
        if (-not $ComposeArguments) {
            throw 'Usage: .\scripts\casa-docker.ps1 compose <docker compose arguments>'
        }

        Invoke-CasaCompose -EnsureEngine -Arguments $ComposeArguments | Write-Output
    }
}
