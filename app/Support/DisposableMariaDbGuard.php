<?php

namespace App\Support;

use RuntimeException;

class DisposableMariaDbGuard
{
    public static function assertSafeDatabaseName(string $database): void
    {
        if (preg_match('/\Aquan_ly_nhan_su_employee_test_[a-f0-9]+\z/', $database) !== 1) {
            throw new RuntimeException('Unsafe MariaDB test database name.');
        }
    }

    /**
     * @return array{host: string, port: string, username: string, password: string}
     */
    public static function environment(): array
    {
        if (getenv('MARIADB_TEST_ENABLED') !== '1') {
            throw new RuntimeException('MARIADB_TEST_ENABLED=1 is required.');
        }

        $username = getenv('MARIADB_TEST_USERNAME');
        if (! is_string($username) || $username === '') {
            throw new RuntimeException('MARIADB_TEST_USERNAME is required.');
        }

        return [
            'host' => getenv('MARIADB_TEST_HOST') ?: '127.0.0.1',
            'port' => getenv('MARIADB_TEST_PORT') ?: '3306',
            'username' => $username,
            'password' => getenv('MARIADB_TEST_PASSWORD') ?: '',
        ];
    }
}
