[CmdletBinding()]
param(
    [ValidateSet('seed', 'cleanup')]
    [string] $Action = 'seed',

    [switch] $EnableDisposableMariaDb
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not $EnableDisposableMariaDb) {
    throw 'Pass -EnableDisposableMariaDb explicitly; employee demo SQL only accepts a guarded disposable database.'
}

$testEnabled = [Environment]::GetEnvironmentVariable('MARIADB_TEST_ENABLED', 'Process')
$database = [Environment]::GetEnvironmentVariable('MARIADB_TEST_DATABASE', 'Process')
$hostName = [Environment]::GetEnvironmentVariable('MARIADB_TEST_HOST', 'Process')
$port = [Environment]::GetEnvironmentVariable('MARIADB_TEST_PORT', 'Process')
$username = [Environment]::GetEnvironmentVariable('MARIADB_TEST_USERNAME', 'Process')
$password = [Environment]::GetEnvironmentVariable('MARIADB_TEST_PASSWORD', 'Process')

if ($testEnabled -cne '1') {
    throw 'MARIADB_TEST_ENABLED=1 is required; the demo helper never targets canonical or shared databases.'
}

foreach ($name in @('MARIADB_TEST_DATABASE', 'MARIADB_TEST_HOST', 'MARIADB_TEST_PORT', 'MARIADB_TEST_USERNAME')) {
    if ([string]::IsNullOrWhiteSpace([Environment]::GetEnvironmentVariable($name, 'Process'))) {
        throw "$name is required for the disposable MariaDB target."
    }
}

if ($database -cnotmatch '\Aquan_ly_nhan_su_employee_test_[a-f0-9]+\z') {
    throw 'MARIADB_TEST_DATABASE must match the DisposableMariaDbGuard allowlist.'
}

$mariadb = Get-Command mariadb -ErrorAction Stop
$scriptName = if ($Action -eq 'seed') {
    '2026_08_21_001_demo_seed.sql'
} else {
    '2026_08_21_002_demo_cleanup.sql'
}
$scriptPath = (Join-Path $PSScriptRoot "demo\$scriptName") -replace '\\', '/'
if (-not (Test-Path -LiteralPath $scriptPath -PathType Leaf)) {
    throw "Demo SQL file is missing: $scriptName"
}

$token = ([Convert]::ToHexString([System.Security.Cryptography.RandomNumberGenerator]::GetBytes(32))).ToLowerInvariant()
$guardSql = @"
CREATE TEMPORARY TABLE employee_demo_guard (
    marker_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    token CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    database_name VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
) ENGINE=MEMORY;
SET @employee_demo_guard_token = '$token';
INSERT INTO employee_demo_guard (marker_id, token, database_name)
VALUES (1, @employee_demo_guard_token, DATABASE());
SOURCE $scriptPath
"@

$mysqlPwdWasPresent = Test-Path Env:MYSQL_PWD
$mysqlPwdPrevious = if ($mysqlPwdWasPresent) { $env:MYSQL_PWD } else { $null }
$exitCode = 1
try {
    $env:MYSQL_PWD = if ($null -eq $password) { '' } else { $password }
    & $mariadb.Source '--abort-source-on-error' '--protocol=tcp' "--host=$hostName" "--port=$port" "--user=$username" "--database=$database" "--execute=$guardSql"
    $exitCode = $LASTEXITCODE
}
finally {
    if ($mysqlPwdWasPresent) {
        Set-Item -Path Env:MYSQL_PWD -Value $mysqlPwdPrevious
    }
    else {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }
}

if ($exitCode -ne 0) {
    throw "Employee demo $Action failed before a successful guarded completion."
}
