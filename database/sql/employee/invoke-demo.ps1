[CmdletBinding()]
param(
    [ValidateSet('seed', 'cleanup')]
    [string] $Action = 'seed',

    [switch] $EnableLocalOnly
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not $EnableLocalOnly) {
    throw 'Pass -EnableLocalOnly explicitly; employee demo SQL is local/disposable only.'
}

$database = if ($env:DB_DATABASE) { $env:DB_DATABASE } else { 'quan_ly_nhan_su' }
$hostName = if ($env:DB_HOST) { $env:DB_HOST } else { '127.0.0.1' }
$port = if ($env:DB_PORT) { $env:DB_PORT } else { '3306' }
$username = if ($env:DB_USERNAME) { $env:DB_USERNAME } else { 'root' }

if ($database -cne 'quan_ly_nhan_su') {
    throw 'The demo helper only accepts the canonical local database name.'
}

if ($hostName -notin @('127.0.0.1', 'localhost', '::1')) {
    throw 'The demo helper only accepts a local MariaDB host.'
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

& $mariadb.Source '--abort-source-on-error' '--protocol=tcp' "--host=$hostName" "--port=$port" "--user=$username" "--database=$database" "--execute=$guardSql"

if ($LASTEXITCODE -ne 0) {
    throw "Employee demo $Action failed before a successful guarded completion."
}
