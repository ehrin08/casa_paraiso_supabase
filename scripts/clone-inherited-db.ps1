[CmdletBinding()]
param(
    [switch] $Apply,
    [switch] $KeepDump,
    [string] $SourceRoot = 'C:\casa_paraiso'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Import-Module (Join-Path $PSScriptRoot 'CasaDocker.psm1') -Force

if (-not $Apply) {
    throw 'This tool is dry-run protected. Rerun with -Apply only after confirming the inherited Docker Desktop stack is the intended read-only source.'
}

$sourceCompose = Join-Path $SourceRoot 'compose.yaml'
if (-not (Test-Path $sourceCompose)) { throw "Inherited Compose file not found: $sourceCompose" }

$projectRoot = Get-CasaProjectPath
$dumpDirectory = Join-Path $projectRoot 'storage\backups\migration'
$dumpPath = Join-Path $dumpDirectory ("inherited-mariadb-{0}.sql" -f (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Force -Path $dumpDirectory | Out-Null

function Invoke-DesktopCompose {
    param([string[]] $Arguments)
    $previousErrorAction = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $result = & docker --context desktop-linux compose --project-directory $SourceRoot -f $sourceCompose -p casa_paraiso @Arguments 2>&1
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorAction
    }
    if ($exitCode -ne 0) { throw "Inherited Docker Desktop command failed: $($result -join [Environment]::NewLine)" }
    return $result
}

function Get-DatabaseCount {
    param([ValidateSet('source', 'destination')] [string] $Side, [ValidatePattern('^[a-z_]+$')] [string] $Table)

    $command = 'MYSQL_PWD="$MYSQL_PASSWORD" mariadb --user="$MYSQL_USER" --skip-column-names --batch "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM ' + $Table + '"'
    $command = 'MYSQL_PWD=$MYSQL_PASSWORD mariadb --user=$MYSQL_USER --skip-column-names --batch $MYSQL_DATABASE -e ''SELECT COUNT(*) FROM ' + $Table + ''''
    if ($Side -eq 'source') {
        return ((Invoke-DesktopCompose @('exec', '-T', 'mariadb', 'sh', '-lc', $command)) | Select-Object -Last 1).Trim()
    }

    return ((Invoke-CasaCompose -Arguments @('exec', '-T', 'mariadb', 'sh', '-lc', $command)) | Select-Object -Last 1).Trim()
}

Invoke-CasaCompose -EnsureEngine -Arguments @('up', '-d', '--wait', '--wait-timeout', '120', 'mariadb', 'laravel.test', 'mailpit') | Out-Null
Invoke-CasaCompose -Arguments @('exec', '-T', '--user', 'sail', 'laravel.test', 'php', 'artisan', 'migrate', '--force') | Out-Null

$sourceUserCount = Get-DatabaseCount -Side source -Table 'users'
$destinationUserCount = Get-DatabaseCount -Side destination -Table 'users'
if ([int]$destinationUserCount -gt 0) {
    foreach ($table in 'users', 'customer_profiles', 'staff_profiles') {
        $sourceCount = Get-DatabaseCount -Side source -Table $table
        $destinationCount = Get-DatabaseCount -Side destination -Table $table
        if ($sourceCount -ne $destinationCount) {
            throw "Destination already contains accounts and differs from the inherited source for ${table}: source=$sourceCount destination=$destinationCount. Refusing to overwrite it."
        }
        Write-Output "${table}: $destinationCount rows verified"
    }

    Write-Output 'The account-bearing destination already matches the inherited source; no data was changed.'
    return
}

$ignored = @('cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'migrations', 'transaction_adjustments') |
    ForEach-Object { '--ignore-table="$MYSQL_DATABASE.' + $_ + '"' }
$dumpCommand = 'MYSQL_PWD="$MYSQL_PASSWORD" exec mariadb-dump --user="$MYSQL_USER" --single-transaction --quick --skip-lock-tables --no-create-info --skip-triggers ' + ($ignored -join ' ') + ' "$MYSQL_DATABASE"'

$dumpCommand = $dumpCommand.Replace('--skip-triggers ', '--skip-triggers --complete-insert --insert-ignore --skip-extended-insert ')
Invoke-DesktopCompose @('exec', '-T', 'mariadb', 'sh', '-lc', $dumpCommand) | Set-Content -LiteralPath $dumpPath -Encoding utf8
if ((Get-Item $dumpPath).Length -eq 0) { throw 'The inherited database dump was empty.' }

$dumpText = Get-Content -LiteralPath $dumpPath -Raw
$quotedAmountColumns = '`service_id`, `quoted_amount`, `staff_profile_id`'
if ($dumpText.Contains($quotedAmountColumns)) {
    $appointmentRows = '(?m)^(INSERT IGNORE INTO `appointments` \([^\r\n]+\) VALUES \()(\d+,''(?:\\.|[^''])*'',\d+,\d+),(?:NULL|-?\d+(?:\.\d+)?),(.*\);)\r?$'
    $rowCount = [regex]::Matches($dumpText, $appointmentRows).Count
    if ($rowCount -eq 0) { throw 'The inherited appointments schema contains quoted_amount, but its rows could not be normalized safely.' }

    $dumpText = $dumpText.Replace($quotedAmountColumns, '`service_id`, `staff_profile_id`')
    $dumpText = [regex]::Replace($dumpText, $appointmentRows, '$1$2,$3')
    Set-Content -LiteralPath $dumpPath -Value $dumpText -Encoding utf8
    Write-Output "Normalized $rowCount appointment rows by omitting the obsolete quoted_amount column."
}

$amountPaidColumns = '`amount`, `amount_paid`, `payment_status`'
if ($dumpText.Contains($amountPaidColumns)) {
    $transactionRows = '(?m)^(INSERT IGNORE INTO `transactions` \([^\r\n]+\) VALUES \()(\d+,''(?:\\.|[^''])*'',(?:NULL|\d+),(?:NULL|\d+),(?:NULL|\d+),(?:NULL|-?\d+(?:\.\d+)?)),(?:NULL|-?\d+(?:\.\d+)?),(.*\);)\r?$'
    $rowCount = [regex]::Matches($dumpText, $transactionRows).Count
    if ($rowCount -eq 0) { throw 'The inherited transactions schema contains amount_paid, but its rows could not be normalized safely.' }

    $dumpText = $dumpText.Replace($amountPaidColumns, '`amount`, `payment_status`')
    $dumpText = [regex]::Replace($dumpText, $transactionRows, '$1$2,$3')
    Set-Content -LiteralPath $dumpPath -Value $dumpText -Encoding utf8
    Write-Output "Normalized $rowCount transaction rows by omitting the obsolete amount_paid column."
}

$wslDumpPath = '/migration/' + (Split-Path -Leaf $dumpPath)
$import = 'MYSQL_PWD="$MYSQL_PASSWORD" mariadb --user="$MYSQL_USER" "$MYSQL_DATABASE" < "' + $wslDumpPath + '"'
Invoke-CasaCompose -Arguments @('exec', '-T', 'mariadb', 'sh', '-lc', $import) | Out-Null

foreach ($table in 'users', 'customer_profiles', 'staff_profiles') {
    $sourceCount = Get-DatabaseCount -Side source -Table $table
    $destinationCount = Get-DatabaseCount -Side destination -Table $table
    if ($sourceCount -ne $destinationCount) { throw "Verification failed for ${table}: source=$sourceCount destination=$destinationCount" }
    Write-Output "${table}: $destinationCount rows verified"
}

if (-not $KeepDump) { Remove-Item -LiteralPath $dumpPath -Force }
Write-Output 'Inherited MariaDB data cloned into the dedicated engine. The Docker Desktop source was not modified.'
