<?php

namespace Tests\Unit\Database;

use PHPUnit\Framework\TestCase;

final class EmployeeSchemaContractTest extends TestCase
{
    public function test_fresh_employee_schema_is_exactly_the_fifteen_table_contract(): void
    {
        $schema = file_get_contents(dirname(__DIR__, 3).'\\database\\sql\\tao_bang.sql');
        self::assertIsString($schema);

        preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)?\s+([a-z_]+)/i', $schema, $matches);
        $tables = array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));
        sort($tables);

        self::assertSame([
            'bo_dem_ma_nhan_vien', 'cham_cong', 'chuc_vu', 'hop_dong', 'lich_su_he_so_luong',
            'loai_hop_dong', 'loai_phep', 'luong', 'nghi_phep', 'nhan_vien', 'phong_ban',
            'quyen', 'trang_thai_lam_viec', 'vai_tro', 'vai_tro_quyen',
        ], $tables);
        self::assertStringNotContainsString('dia_chi_nhan_vien', strtolower($schema));
        self::assertDoesNotMatchRegularExpression(
            '/CREATE TABLE IF NOT EXISTS vai_tro\s*\([^)]*\bky_hieu\b/is',
            $schema,
        );
        self::assertDoesNotMatchRegularExpression(
            '/CREATE TABLE IF NOT EXISTS trang_thai_lam_viec\s*\([^)]*\bky_hieu\b/is',
            $schema,
        );
        self::assertStringContainsString('dia_chi_cu_the', strtolower($schema));
        self::assertStringContainsString('anh_dai_dien', strtolower($schema));
        self::assertStringContainsString('ngay_nghi_viec', strtolower($schema));
    }

    public function test_active_sql_seed_defines_the_five_digit_employee_and_permission_catalog(): void
    {
        $seed = file_get_contents(dirname(__DIR__, 3).'\\database\\sql\\du_lieu_mau.sql');
        self::assertIsString($seed);

        self::assertStringNotContainsString('LEGACY SAMPLE DATA', $seed);
        self::assertStringContainsString("('00001'", $seed);
        self::assertStringContainsString("N'NhanVien.Read'", $seed);
        self::assertStringContainsString("('NHAN_VIEN', 19)", $seed);
        self::assertStringContainsString('A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3', $seed);
    }

    public function test_existing_schema_migration_is_preflighted_and_backup_friendly(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 3).'\\database\\sql\\employee\\2026_08_24_001_migrate_to_fifteen_tables.sql');
        self::assertIsString($migration);
        self::assertStringContainsString('MariaDB DDL implicitly commits', $migration);
        self::assertStringContainsString('orphan_address_rows', $migration);
        self::assertStringContainsString('role_fixed_ids', $migration);
        self::assertStringContainsString('status_fixed_ids', $migration);
        self::assertStringContainsString('permission_id_conflicts', $migration);
        self::assertStringContainsString('INSERT INTO vai_tro_quyen', $migration);
        self::assertStringContainsString('role_two_department_position_mappings', $migration);
        self::assertStringContainsString('department_permission_id_conflicts', $migration);
        self::assertStringContainsString('position_permission_id_conflicts', $migration);
        self::assertStringContainsString('role_two_permission_count', $migration);
        self::assertStringContainsString('DROP TABLE dia_chi_nhan_vien', $migration);
        self::assertStringContainsString('2026_08_24_002_cleanup_legacy_employee_objects.sql', $migration);
        self::assertStringContainsString('GREATEST(so_da_cap', $migration);
        self::assertStringNotContainsString('DROP DATABASE', strtoupper($migration));
        self::assertDoesNotMatchRegularExpression('/^\s*USE\s+quan_ly_nhan_su\s*;/mi', $migration);
    }

    public function test_historical_sql_sources_are_marked_legacy_before_any_mutation(): void
    {
        $root = dirname(__DIR__, 3);
        $historical = [
            'database/sql/employee/2026_08_12_001_schema.sql',
            'database/sql/employee/2026_08_12_002_read_routines.sql',
            'database/sql/employee/2026_08_12_003_create_routines.sql',
            'database/sql/employee/2026_08_12_004_update_routines.sql',
            'database/sql/employee/2026_08_12_005_lifecycle_auth_routines.sql',
            'database/sql/employee/2026_08_12_006_rbac.sql',
            'database/sql/employee/demo/2026_08_21_001_demo_seed.sql',
            'database/sql/employee/demo/2026_08_21_002_demo_cleanup.sql',
        ];

        foreach ($historical as $relativePath) {
            $source = file_get_contents($root.'\\'.$relativePath);
            self::assertIsString($source);
            self::assertMatchesRegularExpression('/\A\s*--\s*LEGACY\b/i', $source, $relativePath);
            self::assertStringNotContainsString('LEGACY', substr($source, -180));
        }

        $cleanup = file_get_contents($root.'\\database\\sql\\employee\\2026_08_24_002_cleanup_legacy_employee_objects.sql');
        self::assertIsString($cleanup);
        self::assertStringContainsString('DROP FUNCTION IF EXISTS fn_dem_nhan_vien_theo_phong_ban', $cleanup);
        self::assertStringNotContainsString('sp_cham_cong_sentinel', $cleanup);
    }
}
