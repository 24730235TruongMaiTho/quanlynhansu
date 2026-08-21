<?php

use App\Support\DisposableMariaDbGuard;
use Tests\Support\EmployeeDependencyFixture;

require dirname(__DIR__, 2).'/vendor/autoload.php';

/** @return never */
function failAcceptanceDependency(string $message): never
{
    fwrite(STDERR, json_encode(['ok' => false, 'error' => 'WORKER_FAILED'], JSON_THROW_ON_ERROR));
    exit(1);
}

try {
    if (getenv('MARIADB_TEST_ENABLED') !== '1') {
        throw new RuntimeException('MARIADB_TEST_ENABLED=1 is required.');
    }

    $database = getenv('MARIADB_TEST_DATABASE');
    if (! is_string($database) || $database === '') {
        throw new RuntimeException('MARIADB_TEST_DATABASE is required.');
    }
    DisposableMariaDbGuard::assertSafeDatabaseName($database);

    $maNv = getenv('EMPLOYEE_ACCEPTANCE_MA_NV');
    if (! is_string($maNv) || preg_match('/\ANV[0-9]{3}\z/', $maNv) !== 1) {
        throw new RuntimeException('EMPLOYEE_ACCEPTANCE_MA_NV is invalid.');
    }
    $dependency = getenv('EMPLOYEE_ACCEPTANCE_DEPENDENCY');
    if (! is_string($dependency) || ! in_array($dependency, EmployeeDependencyFixture::dependencyNames(), true)) {
        throw new RuntimeException('EMPLOYEE_ACCEPTANCE_DEPENDENCY is invalid.');
    }

    $required = [
        'MARIADB_TEST_HOST',
        'MARIADB_TEST_PORT',
        'MARIADB_TEST_USERNAME',
        'MARIADB_TEST_PASSWORD',
    ];
    $credentials = [];
    foreach ($required as $name) {
        $value = getenv($name);
        if (! is_string($value)) {
            throw new RuntimeException("{$name} is required.");
        }
        $credentials[$name] = $value;
    }

    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $credentials['MARIADB_TEST_HOST'],
            $credentials['MARIADB_TEST_PORT'],
            $database,
        ),
        $credentials['MARIADB_TEST_USERNAME'],
        $credentials['MARIADB_TEST_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $pdo->exec("SET time_zone = '+07:00'");
    (new EmployeeDependencyFixture($pdo))->add($maNv, $dependency);

    fwrite(STDOUT, json_encode(['ok' => true], JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $exception) {
    failAcceptanceDependency($exception->getMessage());
}
