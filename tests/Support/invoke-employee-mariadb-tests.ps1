[CmdletBinding()]
param(
    [switch]$EnableDisposableMariaDb,
    [string]$Filter
)

if (-not $EnableDisposableMariaDb) {
    Write-Error 'Pass -EnableDisposableMariaDb to allow disposable MariaDB tests.'
    exit 2
}

$names = @(
    'MARIADB_TEST_ENABLED',
    'MARIADB_TEST_HOST',
    'MARIADB_TEST_PORT',
    'MARIADB_TEST_USERNAME',
    'MARIADB_TEST_PASSWORD'
)
$previous = @{}
foreach ($name in $names) {
    $previous[$name] = @{ Exists = Test-Path "Env:$name"; Value = [Environment]::GetEnvironmentVariable($name, 'Process') }
}

try {
    $env:MARIADB_TEST_ENABLED = '1'
    if ([string]::IsNullOrEmpty($env:MARIADB_TEST_HOST)) { $env:MARIADB_TEST_HOST = '127.0.0.1' }
    if ([string]::IsNullOrEmpty($env:MARIADB_TEST_PORT)) { $env:MARIADB_TEST_PORT = '3306' }
    if (-not (Test-Path Env:MARIADB_TEST_USERNAME)) { $env:MARIADB_TEST_USERNAME = Read-Host 'MariaDB test username' }
    if (-not (Test-Path Env:MARIADB_TEST_PASSWORD)) {
        $securePassword = Read-Host 'MariaDB test password' -AsSecureString
        $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
        try { $env:MARIADB_TEST_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer) }
        finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer) }
    }

    $arguments = @('vendor/bin/phpunit', '-c', 'phpunit.mariadb.xml')
    if (-not [string]::IsNullOrWhiteSpace($Filter)) { $arguments += @('--filter', $Filter) }
    & php @arguments
    exit $LASTEXITCODE
}
finally {
    foreach ($name in $names) {
        if ($previous[$name].Exists) { [Environment]::SetEnvironmentVariable($name, $previous[$name].Value, 'Process') }
        else { Remove-Item "Env:$name" -ErrorAction SilentlyContinue }
    }
}
