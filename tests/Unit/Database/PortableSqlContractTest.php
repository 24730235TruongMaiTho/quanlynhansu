<?php

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;

final class PortableSqlContractTest extends TestCase
{
    public function test_combined_snapshot_is_generated_from_all_current_sources(): void
    {
        $root = dirname(__DIR__, 3);
        $snapshot = file_get_contents($root.'\\quan_ly_nhan_vien_session_update.sql');
        self::assertIsString($snapshot);

        $sources = [
            'database/sql/tao_bang.sql',
            'database/sql/du_lieu_mau.sql',
            'database/sql/quyen_vai_tro.sql',
            'database/sql/salary/2026_09_09_001_luong_functions.sql',
        ];
        $previousMarkerPosition = -1;

        foreach ($sources as $index => $relativePath) {
            $source = file_get_contents($root.'\\'.$relativePath);
            self::assertIsString($source, $relativePath);

            $marker = '-- ===== BEGIN ACTIVE SOURCE '.($index + 1).': '.$relativePath.' =====';
            $markerPosition = strpos($snapshot, $marker);
            self::assertNotFalse($markerPosition, $marker);
            self::assertGreaterThan($previousMarkerPosition, $markerPosition, $marker);
            self::assertStringContainsString(rtrim($source), $snapshot, $relativePath);
            $previousMarkerPosition = $markerPosition;
        }

        self::assertSame(15, preg_match_all('/^CREATE TABLE IF NOT EXISTS\s+/mi', $snapshot));
        self::assertSame(12, preg_match_all('/^CREATE PROCEDURE\s+/mi', $snapshot));
        self::assertSame(4, preg_match_all('/^CREATE FUNCTION\s+/mi', $snapshot));
        self::assertStringNotContainsString('CREATE VIEW', strtoupper($snapshot));
        self::assertStringNotContainsString('VW_DANH_SACH_NHAN_VIEN_CHI_TIET', strtoupper($snapshot));
    }

    public function test_active_seed_and_salary_source_lock_current_counts_and_dependencies(): void
    {
        $root = dirname(__DIR__, 3);
        $seed = file_get_contents($root.'\\database\\sql\\du_lieu_mau.sql');
        $rbac = file_get_contents($root.'\\database\\sql\\quyen_vai_tro.sql');
        $salary = file_get_contents($root.'\\database\\sql\\salary\\2026_09_09_001_luong_functions.sql');
        $upgrade = file_get_contents($root.'\\database\\sql\\rbac\\2026_09_09_001_add_nghiphep_approve_permission.sql');
        $selfServiceUpgrade = file_get_contents($root.'\\database\\sql\\rbac\\2026_09_16_001_add_employee_self_service_permissions.sql');
        $selfServiceCleanup = file_get_contents($root.'\\database\\sql\\rbac\\2026_09_17_001_remove_employee_management_permissions.sql');

        self::assertIsString($seed);
        self::assertIsString($rbac);
        self::assertIsString($salary);
        self::assertIsString($upgrade);
        self::assertIsString($selfServiceUpgrade);
        self::assertIsString($selfServiceCleanup);

        $permissionSection = strstr($seed, 'INSERT INTO quyen');
        self::assertIsString($permissionSection);
        $permissionSection = strstr($permissionSection, 'ALTER TABLE quyen', true);
        self::assertIsString($permissionSection);
        preg_match_all('/^\((\d+),/m', $permissionSection, $permissionMatches);
        self::assertSame(range(1, 43), array_map('intval', $permissionMatches[1]));
        self::assertStringContainsString('ALTER TABLE quyen AUTO_INCREMENT = 44;', $seed);

        $grantSection = strstr($seed, 'INSERT INTO vai_tro_quyen');
        self::assertIsString($grantSection);
        $grantSection = strstr($grantSection, '-- ==================== 8.', true);
        self::assertIsString($grantSection);
        preg_match_all('/\((\d+),\s*(\d+)\)/', $grantSection, $grantMatches, PREG_SET_ORDER);
        $grants = array_map(
            static fn (array $grant): array => [(int) $grant[1], (int) $grant[2]],
            $grantMatches,
        );
        self::assertContains([1, 43], $grants);
        self::assertContains([4, 43], $grants);

        preg_match_all('/^CREATE PROCEDURE\s+([a-z0-9_]+)/mi', $rbac, $procedureMatches);
        self::assertCount(12, $procedureMatches[1]);

        preg_match_all('/^CREATE FUNCTION\s+([a-z0-9_]+)/mi', $salary, $functionMatches);
        self::assertSame([
            'fn_so_ngay_cong_chuan',
            'fn_so_ngay_cong_thuc_te',
            'fn_tinh_luong_thuc_nhan',
            'fn_thong_bao_tinh_luong',
        ], $functionMatches[1]);
        self::assertStringNotContainsString('CREATE PROCEDURE', strtoupper($salary));
        self::assertStringNotContainsString('CREATE VIEW', strtoupper($salary));
        self::assertStringNotContainsString('VW_DANH_SACH_NHAN_VIEN_CHI_TIET', strtoupper($salary));

        self::assertStringNotContainsString('DROP DATABASE', strtoupper($upgrade));
        self::assertStringNotContainsString('TRUNCATE', strtoupper($upgrade));
        self::assertStringNotContainsString('DELETE FROM QUYEN', strtoupper($upgrade));
        self::assertStringContainsString('RBAC_EMPLOYEE_SELF_SERVICE_SCRIPT_SUPERSEDED', $selfServiceUpgrade);
        self::assertStringContainsString('RBAC_EMPLOYEE_SELF_SERVICE_CLEANUP_APPROVAL_REQUIRED', $selfServiceCleanup);
        self::assertStringContainsString('ma_quyen IN (25, 26, 33)', $selfServiceCleanup);
        $roleFiveAssignments = array_values(array_filter(
            $grants,
            static fn (array $grant): bool => $grant[0] === 5,
        ));
        self::assertSame([], $roleFiveAssignments);
    }

    public function test_role_permission_procedures_allow_mapping_mutations_for_role_five_but_role_deletion_stays_protected(): void
    {
        $root = dirname(__DIR__, 3);
        $rbac = file_get_contents($root.'\\database\\sql\\quyen_vai_tro.sql');
        self::assertIsString($rbac);

        $procedureBody = static function (string $source, string $name): string {
            $start = strpos($source, 'CREATE PROCEDURE '.$name);
            self::assertNotFalse($start, $name);
            $end = strpos($source, 'END//', $start);
            self::assertNotFalse($end, $name);

            return substr($source, $start, $end - $start);
        };

        $rolePermissionAdd = $procedureBody($rbac, 'sp_vai_tro_quyen_them');
        $rolePermissionRemove = $procedureBody($rbac, 'sp_vai_tro_quyen_xoa');
        $roleDelete = $procedureBody($rbac, 'sp_vai_tro_xoa');

        self::assertStringNotContainsString('p_ma_vt = 5', $rolePermissionAdd);
        self::assertStringNotContainsString('p_ma_vt = 5', $rolePermissionRemove);
        self::assertStringContainsString('p_ma_vt = 5', $roleDelete);
        self::assertStringContainsString('VT_DEFAULT_ROLE_FORBIDDEN', $roleDelete);
    }

    public function test_current_database_docs_describe_the_portable_sources_and_counts(): void
    {
        $root = dirname(__DIR__, 3);
        $readme = file_get_contents($root.'\\README.md');
        $database = file_get_contents($root.'\\docs\\DATABASE.md');
        $status = file_get_contents($root.'\\docs\\PROJECT_STATUS.md');
        $agents = file_get_contents($root.'\\AGENTS.md');

        foreach ([$readme, $database, $status, $agents] as $document) {
            self::assertIsString($document);
            self::assertStringContainsString('15 bảng', $document);
            self::assertStringContainsString('43 quyền', $document);
            self::assertStringContainsString('12 thủ tục', $document);
            self::assertStringContainsString('RBAC', $document);
            self::assertStringContainsString('4 hàm lương', $document);
        }

        $canonicalSources = [
            'database/sql/tao_bang.sql',
            'database/sql/du_lieu_mau.sql',
            'database/sql/quyen_vai_tro.sql',
            'database/sql/salary/2026_09_09_001_luong_functions.sql',
        ];
        foreach ([$agents, $database] as $document) {
            $previousPosition = -1;
            foreach ($canonicalSources as $relativePath) {
                $position = strpos($document, $relativePath);
                self::assertNotFalse($position, $relativePath);
                self::assertGreaterThan($previousPosition, $position, $relativePath);
                $previousPosition = $position;
            }
        }

        self::assertStringContainsString(
            'database/sql/salary/2026_09_09_001_luong_functions.sql',
            $agents,
        );
        self::assertStringContainsString('LuongRepository@all` hiện dùng Query Builder', $agents);
        self::assertStringContainsString('4 hàm lương canonical', $agents);
        self::assertStringContainsString('sp_luong_tim_kiem_phan_trang` là routine lịch sử', $agents);
    }
}
