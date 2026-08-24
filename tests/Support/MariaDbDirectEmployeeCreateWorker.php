<?php

// Disposable MariaDB worker for the direct Query Builder repository contract.
// This intentionally does not call any legacy employee procedure.

use App\Repositories\NhanVienRepository;
use App\Support\DisposableMariaDbGuard;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function directWorkerRequired(string $name): string
{
    $value = getenv($name);
    if (! is_string($value) || $value === '') {
        throw new RuntimeException("{$name} is required.");
    }

    return $value;
}

function directWorkerSetEnvironment(string $name, string $value): void
{
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

try {
    if (directWorkerRequired('MARIADB_TEST_ENABLED') !== '1') {
        throw new RuntimeException('MARIADB_TEST_ENABLED=1 is required.');
    }

    $database = directWorkerRequired('MARIADB_TEST_DATABASE');
    DisposableMariaDbGuard::assertSafeDatabaseName($database);
    $host = directWorkerRequired('MARIADB_TEST_HOST');
    $port = directWorkerRequired('MARIADB_TEST_PORT');
    $username = directWorkerRequired('MARIADB_TEST_USERNAME');
    $password = getenv('MARIADB_TEST_PASSWORD');
    if (! is_string($password)) {
        throw new RuntimeException('MARIADB_TEST_PASSWORD is required.');
    }

    $profileJson = directWorkerRequired('MARIADB_TEST_PROFILE');
    $profile = json_decode($profileJson, true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($profile) || ! isset($profile['email'], $profile['cccd'])) {
        throw new RuntimeException('MARIADB_TEST_PROFILE is invalid.');
    }

    directWorkerSetEnvironment('APP_ENV', 'testing');
    directWorkerSetEnvironment('DB_CONNECTION', 'mysql');
    directWorkerSetEnvironment('DB_HOST', $host);
    directWorkerSetEnvironment('DB_PORT', $port);
    directWorkerSetEnvironment('DB_DATABASE', $database);
    directWorkerSetEnvironment('DB_USERNAME', $username);
    directWorkerSetEnvironment('DB_PASSWORD', $password);
    directWorkerSetEnvironment('DB_URL', '');
    directWorkerSetEnvironment('DB_SOCKET', '');

    $ready = directWorkerRequired('MARIADB_TEST_READY');
    $barrier = directWorkerRequired('MARIADB_TEST_BARRIER');
    $readyHandle = @fopen($ready, 'x');
    if ($readyHandle === false) {
        throw new RuntimeException('Worker ready marker could not be created.');
    }
    fwrite($readyHandle, 'ready');
    fclose($readyHandle);

    $deadline = microtime(true) + 20;
    while (! is_file($barrier)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Worker barrier timed out.');
        }
        usleep(10_000);
    }

    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $maNv = app(NhanVienRepository::class)->create(
        $profile,
        password_hash('parallel-test', PASSWORD_BCRYPT),
        null,
    );

    fwrite(STDOUT, json_encode(['ok' => true, 'ma_nv' => $maNv], JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, json_encode(['ok' => false, 'error' => 'WORKER_FAILED'], JSON_THROW_ON_ERROR));
    exit(1);
}
