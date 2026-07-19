[CmdletBinding()]
param(
    [switch] $InitializeSigning,
    [switch] $Install,
    [Parameter(Mandatory)]
    [string] $BackendUrl
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

try {
    $backend = [uri]$BackendUrl
} catch {
    throw 'BackendUrl must be an HTTPS origin such as https://your-service.onrender.com.'
}
if ($backend.Scheme -ne 'https' -or $backend.UserInfo -or $backend.Port -ne 443 -or $backend.Query -or $backend.Fragment -or ($backend.AbsolutePath -ne '/' -and $backend.AbsolutePath -ne '')) {
    throw 'BackendUrl must be an HTTPS origin without a path, credentials, query, or fragment.'
}

$projectRoot = Split-Path $PSScriptRoot -Parent
$mobileRoot = Join-Path $projectRoot 'mobile'
$signingRoot = Join-Path $env:USERPROFILE '.casa-paraiso'
$propertiesPath = Join-Path $signingRoot 'android-release.properties'
$keystorePath = Join-Path $signingRoot 'casa-paraiso-release.jks'

function Get-ToolPath {
    param([string] $Name, [string] $Fallback)

    $command = Get-Command $Name -ErrorAction SilentlyContinue
    if ($command) { return $command.Source }
    if (Test-Path $Fallback) { return $Fallback }
    throw "$Name was not found. Install the Android SDK/JDK prerequisites first."
}

function New-SigningMaterial {
    if ((Test-Path $propertiesPath) -or (Test-Path $keystorePath)) {
        throw "Signing material already exists under $signingRoot. Back it up; do not replace it."
    }

    New-Item -ItemType Directory -Force -Path $signingRoot | Out-Null
    $random = [byte[]]::new(32)
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($random) } finally { $generator.Dispose() }
    $password = [Convert]::ToBase64String($random).TrimEnd('=').Replace('+', '-').Replace('/', '_')
    $alias = 'casa-paraiso-mobile'
    $keytoolFallback = if ($env:JAVA_HOME) { Join-Path $env:JAVA_HOME 'bin\keytool.exe' } else { '' }
    $keytool = Get-ToolPath -Name 'keytool.exe' -Fallback $keytoolFallback

    & $keytool -genkeypair -v -keystore $keystorePath -storepass $password -keypass $password -alias $alias -keyalg RSA -keysize 4096 -validity 9125 -dname 'CN=Casa Paraiso Body and Wellness Spa, OU=Mobile, O=Casa Paraiso, L=Manila, ST=Metro Manila, C=PH'
    if ($LASTEXITCODE -ne 0) { throw 'Android release keystore generation failed.' }

    @(
        "storeFile=$keystorePath"
        "storePassword=$password"
        "keyAlias=$alias"
        "keyPassword=$password"
    ) | Set-Content -LiteralPath $propertiesPath -Encoding utf8

    $acl = Get-Acl $signingRoot
    $acl.SetAccessRuleProtection($true, $false)
    $rule = [System.Security.AccessControl.FileSystemAccessRule]::new(
        [System.Security.Principal.WindowsIdentity]::GetCurrent().User,
        [System.Security.AccessControl.FileSystemRights]::FullControl,
        [System.Security.AccessControl.InheritanceFlags]'ContainerInherit, ObjectInherit',
        [System.Security.AccessControl.PropagationFlags]::None,
        [System.Security.AccessControl.AccessControlType]::Allow
    )
    $acl.SetAccessRule($rule)
    Set-Acl -LiteralPath $signingRoot -AclObject $acl
    Write-Output "Release signing material created under $signingRoot. Back up this directory securely."
}

function Read-SigningProperties {
    if (-not (Test-Path $propertiesPath) -or -not (Test-Path $keystorePath)) {
        throw 'Release signing material is missing. Re-run with -InitializeSigning once.'
    }

    $values = @{}
    foreach ($line in Get-Content -LiteralPath $propertiesPath) {
        if ($line -match '^([^=]+)=(.*)$') { $values[$Matches[1]] = $Matches[2] }
    }
    foreach ($key in @('storeFile', 'storePassword', 'keyAlias', 'keyPassword')) {
        if (-not $values.ContainsKey($key) -or [string]::IsNullOrWhiteSpace($values[$key])) {
            throw "Signing property is missing: $key"
        }
    }
    return $values
}

function Get-AdbPath {
    return Get-ToolPath -Name 'adb.exe' -Fallback (Join-Path $env:LOCALAPPDATA 'Android\Sdk\platform-tools\adb.exe')
}

if ($InitializeSigning) { New-SigningMaterial }
$signing = Read-SigningProperties

$env:CASA_RELEASE_STORE_FILE = $signing.storeFile
$env:CASA_RELEASE_STORE_PASSWORD = $signing.storePassword
$env:CASA_RELEASE_KEY_ALIAS = $signing.keyAlias
$env:CASA_RELEASE_KEY_PASSWORD = $signing.keyPassword
$env:ANDROID_HOME = Join-Path $env:LOCALAPPDATA 'Android\Sdk'
$env:VITE_BACKEND_URL = $backend.GetLeftPart([System.UriPartial]::Authority)

Push-Location $mobileRoot
try {
    & npm.cmd run android:sync
    if ($LASTEXITCODE -ne 0) { throw 'Capacitor synchronization failed.' }
    & .\android\gradlew.bat -p android clean assembleRelease testReleaseUnitTest
    if ($LASTEXITCODE -ne 0) { throw 'Signed Android release build failed.' }
} finally {
    Pop-Location
    Remove-Item Env:CASA_RELEASE_STORE_FILE, Env:CASA_RELEASE_STORE_PASSWORD, Env:CASA_RELEASE_KEY_ALIAS, Env:CASA_RELEASE_KEY_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:VITE_BACKEND_URL -ErrorAction SilentlyContinue
}

$apkPath = Join-Path $mobileRoot 'android\app\build\outputs\apk\release\app-release.apk'
if (-not (Test-Path $apkPath)) { throw 'The signed release APK was not produced.' }

$apksigner = Get-ChildItem (Join-Path $env:ANDROID_HOME 'build-tools') -Filter apksigner.bat -Recurse |
    Sort-Object FullName -Descending | Select-Object -First 1
if (-not $apksigner) { throw 'apksigner.bat was not found in the Android SDK.' }
& $apksigner.FullName verify --verbose --print-certs $apkPath
if ($LASTEXITCODE -ne 0) { throw 'APK signature verification failed.' }

$hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $apkPath).Hash
Set-Content -LiteralPath "$apkPath.sha256" -Value "$hash  app-release.apk" -Encoding ascii
Write-Output "Signed APK: $apkPath"
Write-Output "SHA256: $hash"

if ($Install) {
    $adb = Get-AdbPath
    $devices = & $adb devices | Select-Object -Skip 1 | Where-Object { $_ -match "`tdevice$" }
    if (($devices | Measure-Object).Count -ne 1) { throw 'Connect and authorize exactly one Android phone before using -Install.' }
    & $adb install -r $apkPath
    if ($LASTEXITCODE -ne 0) { throw 'APK installation failed.' }
    Write-Output 'Signed APK installed on the connected phone.'
}
