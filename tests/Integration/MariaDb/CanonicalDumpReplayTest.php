<?php

namespace Tests\Integration\MariaDb;

use App\Support\DisposableMariaDbGuard;
use PDO;
use RuntimeException;
use Tests\Support\SqlScriptRunner;

class CanonicalDumpReplayTest extends MariaDbTestCase
{
    public function test_canonical_dump_replays_only_under_a_guarded_name_and_matches_task_three_contracts(): void
    {
        $dumpPath = base_path('quan_ly_nhan_su.session.sql');
        $dump = file_get_contents($dumpPath);
        if ($dump === false) {
            throw new RuntimeException('Unable to read canonical SQL dump.');
        }

        $guardedDatabase = 'quan_ly_nhan_su_employee_test_'.bin2hex(random_bytes(6));
        DisposableMariaDbGuard::assertSafeDatabaseName($guardedDatabase);
        $tempPath = tempnam(sys_get_temp_dir(), 'nv-canonical-');
        if ($tempPath === false) {
            throw new RuntimeException('Unable to create canonical replay temp file.');
        }

        try {
            $rewritten = $this->rewriteDatabaseStatements($dump, $guardedDatabase);
            if (file_put_contents($tempPath, $rewritten) === false) {
                throw new RuntimeException('Unable to write canonical replay temp file.');
            }

            SqlScriptRunner::run($this->pdo(), $tempPath);

            $this->assertSame($guardedDatabase, $this->pdo()->query('SELECT DATABASE()')->fetchColumn());
            $this->assertFoundationSchema();
            $this->assertSafeView();
            $this->assertLegacyRoutineSignatures();
        } finally {
            try {
                DisposableMariaDbGuard::assertSafeDatabaseName($guardedDatabase);
                $this->pdo()->exec("DROP DATABASE IF EXISTS `{$guardedDatabase}`");
            } finally {
                if (is_file($tempPath)) {
                    unlink($tempPath);
                }
            }
        }
    }

    private function rewriteDatabaseStatements(string $dump, string $guardedDatabase): string
    {
        $patterns = [
            '/^DROP DATABASE IF EXISTS quan_ly_nhan_su;\s*$/m',
            '/^CREATE DATABASE quan_ly_nhan_su CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\s*$/m',
            '/^USE quan_ly_nhan_su;\s*$/m',
        ];
        $replacements = [
            "DROP DATABASE IF EXISTS `{$guardedDatabase}`;",
            "CREATE DATABASE `{$guardedDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
            "USE `{$guardedDatabase}`;",
        ];

        foreach ($patterns as $index => $pattern) {
            $this->assertSame(1, preg_match_all($pattern, $dump), "Canonical dump must contain exactly one database statement matching {$pattern}.");
            $count = 0;
            $dump = preg_replace($pattern, $replacements[$index].PHP_EOL, $dump, 1, $count);
            $this->assertSame(1, $count);
        }

        $productionIdentifier = '(?<![A-Za-z0-9_])`?quan_ly_nhan_su`?(?![A-Za-z0-9_])';
        $this->assertDoesNotMatchRegularExpression(
            '/^(?:DROP\s+DATABASE(?:\s+IF\s+EXISTS)?|CREATE\s+DATABASE|USE)\b[^;]*'.$productionIdentifier.'[^;]*;\s*$/mi',
            $dump,
            'A production database identifier remained in a database-level statement.'
        );

        $withoutBlockComments = preg_replace('/\/\*.*?\*\//s', '', $dump);
        if ($withoutBlockComments === null) {
            throw new RuntimeException('Unable to remove block comments from guarded replay copy.');
        }

        $withoutLineComments = preg_replace('/^\s*--.*$/m', '', $withoutBlockComments);
        if ($withoutLineComments === null) {
            throw new RuntimeException('Unable to remove line comments from guarded replay copy.');
        }

        return $withoutLineComments;
    }

    private function assertFoundationSchema(): void
    {
        $expectedColumns = [
            'trang_thai_lam_viec' => ['ky_hieu'],
            'vai_tro' => ['ky_hieu'],
            'nhan_vien' => ['anh_dai_dien', 'ngay_nghi_viec'],
            'dia_chi_nhan_vien' => ['ma_nv', 'dia_chi_cu_the', 'phuong_xa', 'quan_huyen', 'tinh_thanh'],
            'bo_dem_ma_nhan_vien' => ['ten_bo_dem', 'so_da_cap'],
        ];
        foreach ($expectedColumns as $table => $columns) {
            $statement = $this->pdo()->prepare(
                'SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
            );
            $statement->execute([$table]);
            $actual = $statement->fetchAll(PDO::FETCH_COLUMN);
            foreach ($columns as $column) {
                $this->assertContains($column, $actual, "Missing {$table}.{$column} after canonical replay.");
            }
        }

        foreach ([
            ['trang_thai_lam_viec', 'ky_hieu', 'varchar(20)', 'NO'],
            ['vai_tro', 'ky_hieu', 'varchar(50)', 'YES'],
            ['nhan_vien', 'anh_dai_dien', 'varchar(255)', 'YES'],
            ['nhan_vien', 'ngay_nghi_viec', 'date', 'YES'],
            ['bo_dem_ma_nhan_vien', 'so_da_cap', 'smallint(5) unsigned', 'NO'],
        ] as [$table, $column, $type, $nullable]) {
            $statement = $this->pdo()->prepare(
                'SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $statement->execute([$table, $column]);
            $this->assertSame(
                ['COLUMN_TYPE' => $type, 'IS_NULLABLE' => $nullable],
                $statement->fetch(PDO::FETCH_ASSOC),
                "Unexpected canonical definition for {$table}.{$column}."
            );
        }

        $this->assertSame(1, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro WHERE ky_hieu = 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn());
        $this->assertSame(0, (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM vai_tro_quyen vtq JOIN vai_tro vt ON vt.ma_vt = vtq.ma_vt
             WHERE vt.ky_hieu = 'NHAN_VIEN_MAC_DINH'"
        )->fetchColumn());
        $this->assertSame('0', (string) $this->pdo()->query(
            "SELECT so_da_cap FROM bo_dem_ma_nhan_vien WHERE ten_bo_dem = 'NHAN_VIEN'"
        )->fetchColumn());
        $this->assertSame('CASCADE', $this->pdo()->query(
            "SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'dia_chi_nhan_vien'"
        )->fetchColumn());

        foreach ([
            ['nhan_vien', 'uq_nhan_vien_email'],
            ['nhan_vien', 'uq_nhan_vien_cccd'],
            ['trang_thai_lam_viec', 'uq_trang_thai_lam_viec_ky_hieu'],
            ['vai_tro', 'uq_vai_tro_ky_hieu'],
        ] as [$table, $index]) {
            $statement = $this->pdo()->prepare(
                'SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? AND NON_UNIQUE = 0'
            );
            $statement->execute([$table, $index]);
            $this->assertSame(1, (int) $statement->fetchColumn());
        }
    }

    private function assertSafeView(): void
    {
        $columns = $this->pdo()->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vw_danh_sach_nhan_vien_chi_tiet'
             ORDER BY ORDINAL_POSITION"
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('ky_hieu', $columns);
        $this->assertContains('ky_hieu_vai_tro', $columns);
        $this->assertContains('anh_dai_dien', $columns);
        $this->assertNotContains('mat_khau', $columns);
    }

    private function assertLegacyRoutineSignatures(): void
    {
        $expected = [
            'sp_nhan_vien_tim_kiem' => ['IN:p_tu_khoa:varchar(100)', 'IN:p_ma_pb:int(11)', 'IN:p_ma_cv:int(11)', 'IN:p_ma_tt:tinyint(4)'],
            'sp_nhan_vien_danh_sach' => [],
            'sp_nhan_vien_chi_tiet' => ['IN:p_ma_nv:varchar(5)'],
            'sp_nhan_vien_them' => [
                'IN:p_ma_nv:varchar(5)', 'IN:p_ho_ten:varchar(50)', 'IN:p_ngay_sinh:date', 'IN:p_gioi_tinh:tinyint(4)',
                'IN:p_sdt:varchar(15)', 'IN:p_email:varchar(50)', 'IN:p_ngay_vao_lam:date', 'IN:p_ma_pb:int(11)',
                'IN:p_ma_cv:int(11)', 'IN:p_dan_toc:varchar(50)', 'IN:p_cccd:varchar(12)', 'IN:p_noi_cap_cccd:varchar(50)',
                'IN:p_hoc_van:varchar(50)', 'IN:p_ma_tt:tinyint(4)', 'IN:p_mat_khau:varchar(255)', 'IN:p_ma_vt:int(11)',
            ],
            'sp_nhan_vien_sua' => [
                'IN:p_ma_nv:varchar(5)', 'IN:p_ho_ten:varchar(50)', 'IN:p_ngay_sinh:date', 'IN:p_gioi_tinh:tinyint(4)',
                'IN:p_sdt:varchar(15)', 'IN:p_email:varchar(50)', 'IN:p_ngay_vao_lam:date', 'IN:p_ma_pb:int(11)',
                'IN:p_ma_cv:int(11)', 'IN:p_dan_toc:varchar(50)', 'IN:p_cccd:varchar(12)', 'IN:p_noi_cap_cccd:varchar(50)',
                'IN:p_hoc_van:varchar(50)', 'IN:p_ma_tt:tinyint(4)', 'IN:p_mat_khau:varchar(255)', 'IN:p_ma_vt:int(11)',
            ],
            'sp_nhan_vien_xoa' => ['IN:p_ma_nv:varchar(5)'],
            'sp_nhan_vien_dang_nhap' => ['IN:p_ten_dang_nhap:varchar(50)', 'IN:p_mat_khau:varchar(255)'],
            'sp_cham_cong_nhan_vien_tim_kiem' => ['IN:p_ma_nv:varchar(5)', 'IN:p_ngay_lam:date'],
            'sp_luong_tim_kiem' => ['IN:p_tu_khoa:varchar(255)', 'IN:p_ky_luong:date', 'IN:p_ma_pb:int(11)', 'IN:p_ma_cv:int(11)'],
            'sp_luong_xem' => ['IN:p_ma_nv:varchar(5)', 'IN:p_ky_luong:date'],
        ];

        foreach ($expected as $routine => $signature) {
            $statement = $this->pdo()->prepare(
                "SELECT CONCAT(PARAMETER_MODE, ':', PARAMETER_NAME, ':', DTD_IDENTIFIER)
                 FROM information_schema.PARAMETERS
                 WHERE SPECIFIC_SCHEMA = DATABASE() AND SPECIFIC_NAME = ? AND ORDINAL_POSITION > 0
                 ORDER BY ORDINAL_POSITION"
            );
            $statement->execute([$routine]);
            $this->assertSame($signature, $statement->fetchAll(PDO::FETCH_COLUMN), "Signature drift for {$routine}.");

            $exists = $this->pdo()->prepare(
                'SELECT COUNT(*) FROM information_schema.ROUTINES
                 WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = ? AND ROUTINE_TYPE = ?'
            );
            $exists->execute([$routine, 'PROCEDURE']);
            $this->assertSame(1, (int) $exists->fetchColumn(), "Missing procedure {$routine}.");
        }
    }
}
