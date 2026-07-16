[CmdletBinding()]
param(
    [ValidateSet('Install', 'Verify')]
    [string] $Action = 'Verify'
)

Import-Module (Join-Path $PSScriptRoot 'CasaDedicatedDocker.psm1') -Force

$distro = 'CasaParaisoDocker'
$location = 'C:\WSL\CasaParaisoDocker'

function Invoke-ProvisionScript {
    $script = @'
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive

if ! id -u casa >/dev/null 2>&1; then
  useradd --create-home --shell /bin/bash casa
fi

cat >/etc/wsl.conf <<'EOF'
[boot]
systemd=true
[interop]
appendWindowsPath=false
EOF

apt-get update
apt-get install -y ca-certificates curl
install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
chmod a+r /etc/apt/keyrings/docker.asc
cat >/etc/apt/sources.list.d/docker.sources <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF
apt-get update
apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
usermod -aG docker casa
install -d -m 0755 /etc/docker
cat >/etc/docker/daemon.json <<'EOF'
{
  "labels": ["com.casaparaiso.engine=dedicated"],
  "log-driver": "local",
  "log-opts": {"max-size": "10m", "max-file": "3"}
}
EOF
'@

    $encoded = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($script))
    & wsl.exe --distribution $distro --user root -- bash -lc "echo $encoded | base64 --decode | bash"
    if ($LASTEXITCODE -ne 0) { throw 'Docker Engine provisioning failed.' }
}

if ($Action -eq 'Install') {
    if (-not (Test-CasaDistro)) {
        New-Item -ItemType Directory -Force -Path (Split-Path $location) | Out-Null
        & wsl.exe --install Ubuntu-24.04 --name $distro --location $location --version 2 --web-download --no-launch
        if ($LASTEXITCODE -ne 0) { throw 'Ubuntu 24.04 installation failed. Restart Windows if WSL reports a pending reboot, then rerun this command.' }
    }

    Invoke-ProvisionScript
    & wsl.exe --terminate $distro
    if ($LASTEXITCODE -ne 0) { throw 'Could not restart the dedicated WSL distribution.' }

    Invoke-CasaWsl -User root -Arguments @('systemctl', 'disable', '--now', 'docker.service', 'docker.socket') | Out-Null
    Start-CasaDockerEngine
    Invoke-CasaWsl -Arguments @('docker', 'run', '--rm', 'hello-world') | Write-Output
    Stop-CasaDockerEngine
}

if (-not (Test-CasaDistro)) {
    throw 'CasaParaisoDocker is not installed.'
}

Start-CasaDockerEngine
$info = Invoke-CasaWsl -Arguments @('docker', 'info', '--format', 'root={{.DockerRootDir}} labels={{json .Labels}}')
Write-Output ($info -join [Environment]::NewLine)
Stop-CasaDockerEngine
