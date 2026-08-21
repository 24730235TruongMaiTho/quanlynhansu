<?php

use App\Support\DisposableMariaDbGuard;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function lifecycleRequired(string $name): string
{
    $value = getenv($name);
    if (! is_string($value) || $value === '') {
        throw new RuntimeException("{$name} is required.");
    }

    return $value;
}

function lifecycleMarkReadyAndWait(PDO $pdo): void
{
    $ready = lifecycleRequired('MARIADB_TEST_READY');
    $barrier = getenv('MARIADB_TEST_BARRIER');
    $handle = @fopen($ready, 'x');
    if ($handle === false) {
        throw new RuntimeException('Worker ready marker could not be created.');
    }
    $connectionId = (string) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    fwrite($handle, "ready:{$connectionId}");
    fclose($handle);

    if (! is_string($barrier) || $barrier === '') {
        return;
    }
    $deadline = microtime(true) + 20;
    while (! is_file($barrier)) {
        if (microtime(true) >= $deadline) {
            throw new RuntimeException('Worker barrier timed out.');
        }
        usleep(10_000);
    }
}

function lifecycleCall(PDO $pdo, string $maNv, string $date): array
{
    $pdo->exec('SET @nv_hanh_dong = NULL');
    $pdo->exec('SET @nv_anh_cu = NULL');
    $statement = $pdo->prepare(
        'CALL sp_nhan_vien_xoa_hoac_nghi_viec(?, ?, @nv_hanh_dong, @nv_anh_cu)'
    );
    $statement->execute([$maNv, $date]);
    $statement->closeCursor();

    return [
        'action' => $pdo->query('SELECT @nv_hanh_dong')->fetchColumn(),
        'avatar_path' => $pdo->query('SELECT @nv_anh_cu')->fetchColumn(),
    ];
}

function lifecycleUpdate(PDO $pdo, string $maNv): void
{
    $statement = $pdo->prepare(
        'SELECT ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam,
                ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt
         FROM nhan_vien WHERE BINARY ma_nv = BINARY ? FOR UPDATE'
    );
    $statement->execute([$maNv]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    if (! is_array($row)) {
        throw new RuntimeException('NV_NOT_FOUND');
    }
    $statement->closeCursor();

    # Simulate a stale active edit form: after lifecycle wins, the update must fail rather than
    # reactivate a terminal account or erase its termination date.
    $row['ma_tt'] = $pdo->query(
        "SELECT ma_tt FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY 'DANG_LAM'"
    )->fetchColumn();

    $update = $pdo->prepare(
        'CALL sp_nhan_vien_sua(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $update->execute([
        $maNv,
        $row['ho_ten'],
        $row['ngay_sinh'],
        $row['gioi_tinh'],
        $row['sdt'],
        $row['email'],
        $row['ngay_vao_lam'],
        $row['ma_pb'],
        $row['ma_cv'],
        $row['dan_toc'],
        $row['cccd'],
        $row['noi_cap_cccd'],
        $row['hoc_van'],
        $row['ma_tt'],
    ]);
    $update->closeCursor();
}

try {
    if (lifecycleRequired('MARIADB_TEST_ENABLED') !== '1') {
        throw new RuntimeException('MARIADB_TEST_ENABLED=1 is required.');
    }
    $database = lifecycleRequired('MARIADB_TEST_DATABASE');
    DisposableMariaDbGuard::assertSafeDatabaseName($database);
    $host = lifecycleRequired('MARIADB_TEST_HOST');
    $port = lifecycleRequired('MARIADB_TEST_PORT');
    $username = lifecycleRequired('MARIADB_TEST_USERNAME');
    $password = getenv('MARIADB_TEST_PASSWORD');
    if (! is_string($password)) {
        throw new RuntimeException('MARIADB_TEST_PASSWORD is required.');
    }
    $operation = lifecycleRequired('MARIADB_TEST_OPERATION');
    if (! in_array($operation, ['lifecycle', 'hold_lifecycle', 'hold_update', 'update'], true)) {
        throw new RuntimeException('MARIADB_TEST_OPERATION is invalid.');
    }
    $maNv = lifecycleRequired('MARIADB_TEST_MA_NV');
    $date = lifecycleRequired('MARIADB_TEST_DATE');

    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database),
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $pdo->exec("SET time_zone = '+07:00'");
    $pdo->beginTransaction();

    if ($operation === 'hold_lifecycle') {
        $result = lifecycleCall($pdo, $maNv, $date);
        // Keep the routine's own row lock until the barrier so the competing operation
        // proves it waits on the lifecycle mutation, not on a worker-only pre-lock.
        lifecycleMarkReadyAndWait($pdo);
        $pdo->commit();
        fwrite(STDOUT, json_encode(['ok' => true] + $result, JSON_THROW_ON_ERROR));
        exit(0);
    }

    if ($operation === 'hold_update') {
        lifecycleUpdate($pdo, $maNv);
        // The profile procedure has completed while this transaction still owns its row
        // lock; the barrier makes the lifecycle routine observe that real update lock.
        lifecycleMarkReadyAndWait($pdo);
        $pdo->commit();
        fwrite(STDOUT, json_encode(['ok' => true], JSON_THROW_ON_ERROR));
        exit(0);
    }

    if ($operation === 'update') {
        lifecycleMarkReadyAndWait($pdo);
        lifecycleUpdate($pdo, $maNv);
        $pdo->commit();
        fwrite(STDOUT, json_encode(['ok' => true], JSON_THROW_ON_ERROR));
        exit(0);
    }

    lifecycleMarkReadyAndWait($pdo);
    $result = lifecycleCall($pdo, $maNv, $date);
    $pdo->commit();
    fwrite(STDOUT, json_encode(['ok' => true] + $result, JSON_THROW_ON_ERROR));
    exit(0);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $message = $exception->getMessage();
    $safeCode = preg_match('/\bNV_(?:NOT_FOUND|REFERENCE_INVALID|STATUS_MISSING|PRIVILEGED_TARGET|AUTH_HASH_STALE)\b/', $message, $matches) === 1
        ? $matches[0]
        : 'WORKER_FAILED';
    fwrite(STDERR, json_encode(['ok' => false, 'error' => $safeCode], JSON_THROW_ON_ERROR));
    exit(1);
}
