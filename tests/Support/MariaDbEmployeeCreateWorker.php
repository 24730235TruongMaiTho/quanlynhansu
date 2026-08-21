<?php

use App\Support\DisposableMariaDbGuard;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function requiredEnvironment(string $name): string
{
    $value = getenv($name);
    if (! is_string($value) || $value === '') {
        throw new RuntimeException("{$name} is required.");
    }

    return $value;
}

try {
    if (requiredEnvironment('MARIADB_TEST_ENABLED') !== '1') {
        throw new RuntimeException('MARIADB_TEST_ENABLED=1 is required.');
    }

    $database = requiredEnvironment('MARIADB_TEST_DATABASE');
    DisposableMariaDbGuard::assertSafeDatabaseName($database);
    $host = requiredEnvironment('MARIADB_TEST_HOST');
    $port = requiredEnvironment('MARIADB_TEST_PORT');
    $username = requiredEnvironment('MARIADB_TEST_USERNAME');
    $password = getenv('MARIADB_TEST_PASSWORD');
    if (! is_string($password)) {
        throw new RuntimeException('MARIADB_TEST_PASSWORD is required.');
    }

    $barrier = requiredEnvironment('MARIADB_TEST_BARRIER');
    $ready = requiredEnvironment('MARIADB_TEST_READY');
    $profileJson = requiredEnvironment('MARIADB_TEST_PROFILE');
    $profile = json_decode($profileJson, true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($profile) || count($profile) !== 15) {
        throw new RuntimeException('MARIADB_TEST_PROFILE is invalid.');
    }
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $pdo->exec("SET time_zone = '+07:00'");

    $pdo->beginTransaction();
    $pdo->exec('SET @nv_ma = NULL');
    $statement = $pdo->prepare(
        'CALL sp_nhan_vien_them(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @nv_ma)'
    );

    $readyHandle = @fopen($ready, 'x');
    if ($readyHandle === false) {
        throw new RuntimeException('Worker ready marker could not be created.');
    }
    fwrite($readyHandle, 'ready');
    fclose($readyHandle);

    $deadline = microtime(true) + 15;
    while (! is_file($barrier)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Worker barrier timed out.');
        }
        usleep(10_000);
    }

    $statement->execute(array_values($profile));
    $statement->closeCursor();
    $maNv = (string) $pdo->query('SELECT @nv_ma')->fetchColumn();
    $pdo->commit();

    fwrite(STDOUT, json_encode(['ok' => true, 'ma_nv' => $maNv], JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $message = $exception->getMessage();
    $safeCode = preg_match('/\bNV_(?:EMAIL_DUPLICATE|CCCD_DUPLICATE)\b/', $message, $matches) === 1
        ? $matches[0]
        : 'WORKER_FAILED';
    fwrite(STDERR, json_encode(['ok' => false, 'error' => $safeCode], JSON_THROW_ON_ERROR));
    exit(1);
}
