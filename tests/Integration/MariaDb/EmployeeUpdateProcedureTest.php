<?php

namespace Tests\Integration\MariaDb;

use PDO;
use PDOException;

class EmployeeUpdateProcedureTest extends MariaDbTestCase
{
    private int $department;

    private int $otherDepartment;

    private int $position;

    private int $otherPosition;

    private int $workingStatus;

    private int $probationStatus;

    private int $terminatedStatus;

    private int $defaultRole;

    private int $privilegedRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runSql(base_path('tests/Fixtures/MariaDb/employee_legacy_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_001_schema.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_002_read_routines.sql'));
        $this->runSql(base_path('database/sql/employee/2026_08_12_003_create_routines.sql'));

        $updateScript = base_path('database/sql/employee/2026_08_12_004_update_routines.sql');
        if (is_file($updateScript)) {
            $this->runSql($updateScript);
        }

        $this->seedLookups();
    }

    public function test_update_and_avatar_signatures_are_exact_and_use_the_outer_transaction(): void
    {
        $this->assertSame([
            'IN:p_ma_nv:varchar(5)', 'IN:p_ho_ten:varchar(50)', 'IN:p_ngay_sinh:date',
            'IN:p_gioi_tinh:tinyint(4)', 'IN:p_sdt:varchar(15)', 'IN:p_email:varchar(100)',
            'IN:p_ngay_vao_lam:date', 'IN:p_ma_pb:int(11)', 'IN:p_ma_cv:int(11)',
            'IN:p_dan_toc:varchar(50)', 'IN:p_cccd:varchar(12)', 'IN:p_noi_cap_cccd:varchar(50)',
            'IN:p_hoc_van:varchar(50)', 'IN:p_ma_tt:tinyint(4)',
        ], $this->signature('sp_nhan_vien_sua'));
        $this->assertSame([
            'IN:p_ma_nv:varchar(5)', 'IN:p_anh_moi:varchar(255)', 'OUT:p_anh_cu:varchar(255)',
        ], $this->signature('sp_nhan_vien_cap_nhat_anh'));

        foreach (['sp_nhan_vien_sua', 'sp_nhan_vien_cap_nhat_anh'] as $procedure) {
            $definition = $this->routineDefinition($procedure);
            $this->assertNotSame('', $definition, "Missing procedure {$procedure}.");
            $this->assertDoesNotMatchRegularExpression(
                '/\b(?:START\s+TRANSACTION|COMMIT|ROLLBACK)\b/i',
                $definition,
            );
        }
    }

    public function test_profile_update_normalizes_every_mutable_field_and_preserves_system_owned_columns(): void
    {
        $this->insertEmployee('NV001', [
            'mat_khau' => '$2y$12$'.str_repeat('h', 53),
            'anh_dai_dien' => 'legacy/avatar value.png',
        ]);
        $before = $this->employee('NV001');

        $this->updateEmployee('nv001', [
            'ho_ten' => '  Trần Bình  ',
            'ngay_sinh' => '1992-02-03',
            'gioi_tinh' => 0,
            'sdt' => ' 0912345678 ',
            'email' => '  BINH@EXAMPLE.TEST ',
            'ngay_vao_lam' => '2024-03-04',
            'ma_pb' => $this->otherDepartment,
            'ma_cv' => $this->otherPosition,
            'dan_toc' => '  Tày ',
            'cccd' => '001200000777',
            'noi_cap_cccd' => '  Hà Nội ',
            'hoc_van' => '  Cao học ',
            'ma_tt' => $this->probationStatus,
        ]);

        $after = $this->employee('NV001');
        $this->assertSame([
            'ho_ten' => 'Trần Bình',
            'ngay_sinh' => '1992-02-03',
            'gioi_tinh' => 0,
            'sdt' => '0912345678',
            'email' => 'binh@example.test',
            'ngay_vao_lam' => '2024-03-04',
            'ma_pb' => $this->otherDepartment,
            'ma_cv' => $this->otherPosition,
            'dan_toc' => 'Tày',
            'cccd' => '001200000777',
            'noi_cap_cccd' => 'Hà Nội',
            'hoc_van' => 'Cao học',
            'ma_tt' => $this->probationStatus,
        ], array_intersect_key($after, array_flip([
            'ho_ten', 'ngay_sinh', 'gioi_tinh', 'sdt', 'email', 'ngay_vao_lam', 'ma_pb', 'ma_cv',
            'dan_toc', 'cccd', 'noi_cap_cccd', 'hoc_van', 'ma_tt',
        ])));
        foreach (['ma_nv', 'ma_vt', 'mat_khau', 'anh_dai_dien', 'ngay_nghi_viec'] as $column) {
            $this->assertSame($before[$column], $after[$column], "System column {$column} changed.");
        }
    }

    public function test_lifecycle_pairs_allow_only_active_transitions_or_an_unchanged_terminated_state(): void
    {
        $this->insertEmployee('NV001');
        $this->updateEmployee('NV001', ['ma_tt' => $this->probationStatus]);
        $this->assertSame($this->probationStatus, (int) $this->employee('NV001')['ma_tt']);
        $this->updateEmployee('NV001', ['ma_tt' => $this->workingStatus]);

        $this->assertProcedureError('NV_STATUS_MISSING', fn () => $this->updateEmployee(
            'NV001',
            ['ma_tt' => $this->terminatedStatus],
        ));
        $activeBefore = $this->employee('NV001');
        $this->assertSame($this->workingStatus, (int) $activeBefore['ma_tt']);

        $this->insertEmployee('NV002', [
            'email' => 'terminated@example.test',
            'cccd' => '001200000002',
            'ma_tt' => $this->terminatedStatus,
            'ngay_nghi_viec' => '2026-07-01',
        ]);
        $this->updateEmployee('NV002', [
            'ho_ten' => 'Nhân viên đã nghỉ',
            'email' => 'terminated-updated@example.test',
            'cccd' => '001200000002',
            'ma_tt' => $this->terminatedStatus,
        ]);
        $terminated = $this->employee('NV002');
        $this->assertSame('Nhân viên đã nghỉ', $terminated['ho_ten']);
        $this->assertSame('2026-07-01', $terminated['ngay_nghi_viec']);
        $terminatedBeforeReactivation = $this->employee('NV002');
        $this->assertProcedureError('NV_STATUS_MISSING', fn () => $this->updateEmployee(
            'NV002',
            ['email' => 'terminated-updated@example.test', 'cccd' => '001200000002', 'ma_tt' => $this->workingStatus],
        ));
        $this->assertSame($terminatedBeforeReactivation, $this->employee('NV002'));

        $this->pdo()->exec("UPDATE nhan_vien SET ngay_nghi_viec = '2026-08-01' WHERE ma_nv = 'NV001'");
        $corruptActive = $this->employee('NV001');
        $this->assertProcedureError('NV_STATUS_MISSING', fn () => $this->updateEmployee('NV001'));
        $this->assertSame($corruptActive, $this->employee('NV001'));
        $this->pdo()->exec("UPDATE nhan_vien SET ngay_nghi_viec = NULL WHERE ma_nv = 'NV002'");
        $corruptTerminated = $this->employee('NV002');
        $this->assertProcedureError('NV_STATUS_MISSING', fn () => $this->updateEmployee(
            'NV002',
            ['email' => 'terminated-updated@example.test', 'cccd' => '001200000002', 'ma_tt' => $this->terminatedStatus],
        ));
        $this->assertSame($corruptTerminated, $this->employee('NV002'));
    }

    public function test_privileged_guard_precedes_all_other_validation_and_never_mutates(): void
    {
        $this->insertEmployee('NV001', ['ma_vt' => $this->privilegedRole]);
        $before = $this->employee('NV001');

        $this->assertProcedureError('NV_PRIVILEGED_TARGET', fn () => $this->updateEmployee('NV001', [
            'ho_ten' => '',
            'email' => 'duplicate@example.test',
            'cccd' => 'bad',
            'ma_pb' => 999999,
            'ma_cv' => 999999,
            'ma_tt' => 127,
        ]));
        $this->assertAvatarFailureKeepsOutNullAndRow('NV_PRIVILEGED_TARGET',
            'NV001',
            '  invalid path  ',
        );
        $this->assertSame($before, $this->employee('NV001'));
    }

    public function test_not_found_reference_and_normalized_identity_collisions_are_safe(): void
    {
        $this->insertEmployee('NV001');
        $this->insertEmployee('NV002', [
            'email' => 'other@example.test',
            'cccd' => '001200000002',
        ]);

        $before = $this->employee('NV001');
        $this->assertProcedureError('NV_NOT_FOUND', fn () => $this->updateEmployee('NV999'));
        $this->assertProcedureError('NV_REFERENCE_INVALID', fn () => $this->updateEmployee('NV001', ['ma_pb' => 999999]));
        $this->assertSame($before, $this->employee('NV001'));
        $this->assertProcedureError('NV_EMAIL_DUPLICATE', fn () => $this->updateEmployee('NV001', [
            'email' => '  OTHER@EXAMPLE.TEST ',
        ]));
        $this->assertSame($before, $this->employee('NV001'));
        $this->assertProcedureError('NV_CCCD_DUPLICATE', fn () => $this->updateEmployee('NV001', [
            'cccd' => '001200000002',
        ]));
        $this->assertSame($before, $this->employee('NV001'));
    }

    public function test_avatar_replace_and_removal_return_old_path_while_profile_update_keeps_avatar(): void
    {
        $this->insertEmployee('NV001', ['anh_dai_dien' => 'legacy/untrusted.png']);
        $this->updateEmployee('NV001', ['ho_ten' => 'Tên mới']);
        $this->assertSame('legacy/untrusted.png', $this->employee('NV001')['anh_dai_dien']);

        $this->assertSame('legacy/untrusted.png', $this->replaceAvatar(
            'nv001',
            'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png',
        ));
        $this->assertSame(
            'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png',
            $this->employee('NV001')['anh_dai_dien'],
        );
        $this->assertSame(
            'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png',
            $this->replaceAvatar('NV001', null),
        );
        $this->assertNull($this->employee('NV001')['anh_dai_dien']);

        $this->assertAvatarFailureKeepsOutNullAndRow('NV_NOT_FOUND', 'NV999', null);
        $this->assertAvatarFailureKeepsOutNullAndRow('NV_REFERENCE_INVALID', 'NV001', '');
        $this->assertAvatarFailureKeepsOutNullAndRow('NV_REFERENCE_INVALID', 'NV001', ' trim-me.png ');
        // 128 UTF-8 Vietnamese characters fit VARCHAR(255) by characters but exceed
        // the locked 255-byte path contract, so the routine body owns this error.
        $this->assertAvatarFailureKeepsOutNullAndRow('NV_REFERENCE_INVALID', 'NV001', str_repeat('ă', 128));
    }

    public function test_outer_rollback_restores_profile_address_and_avatar_together(): void
    {
        $this->insertEmployee('NV001', ['anh_dai_dien' => 'old.png']);
        $this->upsertAddress('NV001', ['Số 1', 'Phường A', 'Quận A', 'TP A']);
        $beforeEmployee = $this->employee('NV001');
        $beforeAddress = $this->address('NV001');

        $this->pdo()->beginTransaction();
        $this->updateEmployee('NV001', ['ho_ten' => 'Tên trong transaction']);
        $this->upsertAddress('NV001', ['Số 2', 'Phường B', 'Quận B', 'TP B']);
        $this->replaceAvatar('NV001', 'new.png');
        $this->pdo()->rollBack();

        $this->assertSame($beforeEmployee, $this->employee('NV001'));
        $this->assertSame($beforeAddress, $this->address('NV001'));
    }

    private function seedLookups(): void
    {
        $this->pdo()->exec("INSERT INTO phong_ban (ten_pb) VALUES ('Kỹ thuật'), ('Nhân sự')");
        $departments = $this->pdo()->query('SELECT ma_pb FROM phong_ban ORDER BY ma_pb')->fetchAll(PDO::FETCH_COLUMN);
        [$this->department, $this->otherDepartment] = array_map('intval', $departments);

        $this->pdo()->exec("INSERT INTO chuc_vu (ten_cv, he_so_phu_cap) VALUES ('Lập trình viên', 0.20), ('Chuyên viên', 0.10)");
        $positions = $this->pdo()->query('SELECT ma_cv FROM chuc_vu ORDER BY ma_cv')->fetchAll(PDO::FETCH_COLUMN);
        [$this->position, $this->otherPosition] = array_map('intval', $positions);

        $this->workingStatus = $this->statusId('DANG_LAM');
        $this->probationStatus = $this->statusId('THU_VIEC');
        $this->terminatedStatus = $this->statusId('DA_NGHI');
        $this->defaultRole = $this->role('NHAN_VIEN_MAC_DINH');
        $this->pdo()->exec("INSERT INTO vai_tro (ten_vt, mo_ta, ky_hieu) VALUES ('Quản trị', 'fixture', 'QUAN_TRI')");
        $this->privilegedRole = (int) $this->pdo()->lastInsertId();
    }

    private function insertEmployee(string $maNv, array $overrides = []): void
    {
        $data = array_replace([
            'ma_nv' => $maNv,
            'ho_ten' => 'Nguyễn An',
            'ngay_sinh' => '1990-01-01',
            'gioi_tinh' => 1,
            'sdt' => '0901234567',
            'email' => 'employee@example.test',
            'ngay_vao_lam' => '2020-01-01',
            'ma_pb' => $this->department,
            'ma_cv' => $this->position,
            'dan_toc' => 'Kinh',
            'cccd' => '001200000001',
            'noi_cap_cccd' => 'Cục CSQLHC',
            'hoc_van' => 'Đại học',
            'ma_tt' => $this->workingStatus,
            'mat_khau' => '$2y$12$'.str_repeat('x', 53),
            'ma_vt' => $this->defaultRole,
            'anh_dai_dien' => null,
            'ngay_nghi_viec' => null,
        ], $overrides);

        $statement = $this->pdo()->prepare(
            'INSERT INTO nhan_vien (
                ma_nv, ho_ten, ngay_sinh, gioi_tinh, sdt, email, ngay_vao_lam,
                ma_pb, ma_cv, dan_toc, cccd, noi_cap_cccd, hoc_van, ma_tt,
                mat_khau, ma_vt, anh_dai_dien, ngay_nghi_viec
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute(array_values($data));
    }

    private function updateEmployee(string $maNv, array $overrides = []): void
    {
        $current = $maNv === 'NV999' ? [] : ($this->employee(strtoupper(trim($maNv))) ?: []);
        $data = array_replace([
            'ho_ten' => $current['ho_ten'] ?? 'Nguyễn An',
            'ngay_sinh' => $current['ngay_sinh'] ?? '1990-01-01',
            'gioi_tinh' => isset($current['gioi_tinh']) ? (int) $current['gioi_tinh'] : 1,
            'sdt' => $current['sdt'] ?? '0901234567',
            'email' => $current['email'] ?? 'missing@example.test',
            'ngay_vao_lam' => $current['ngay_vao_lam'] ?? '2020-01-01',
            'ma_pb' => isset($current['ma_pb']) ? (int) $current['ma_pb'] : $this->department,
            'ma_cv' => isset($current['ma_cv']) ? (int) $current['ma_cv'] : $this->position,
            'dan_toc' => $current['dan_toc'] ?? 'Kinh',
            'cccd' => $current['cccd'] ?? '001200000999',
            'noi_cap_cccd' => $current['noi_cap_cccd'] ?? 'Cục CSQLHC',
            'hoc_van' => $current['hoc_van'] ?? 'Đại học',
            'ma_tt' => isset($current['ma_tt']) ? (int) $current['ma_tt'] : $this->workingStatus,
        ], $overrides);

        $statement = $this->pdo()->prepare('CALL sp_nhan_vien_sua(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $statement->execute([$maNv, ...array_values($data)]);
        $statement->closeCursor();
    }

    private function replaceAvatar(string $maNv, ?string $newPath): ?string
    {
        $this->pdo()->exec('SET @nv_anh_cu = NULL');
        $statement = $this->pdo()->prepare('CALL sp_nhan_vien_cap_nhat_anh(?, ?, @nv_anh_cu)');
        $statement->execute([$maNv, $newPath]);
        $statement->closeCursor();

        $old = $this->pdo()->query('SELECT @nv_anh_cu')->fetchColumn();

        return $old === null ? null : (string) $old;
    }

    private function upsertAddress(string $maNv, array $address): void
    {
        $statement = $this->pdo()->prepare('CALL sp_dia_chi_nhan_vien_luu(?, ?, ?, ?, ?)');
        $statement->execute([$maNv, ...$address]);
        $statement->closeCursor();
    }

    private function employee(string $maNv): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM nhan_vien WHERE ma_nv = ?');
        $statement->execute([$maNv]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function address(string $maNv): array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM dia_chi_nhan_vien WHERE ma_nv = ?');
        $statement->execute([$maNv]);

        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function signature(string $procedure): array
    {
        $statement = $this->pdo()->prepare(
            "SELECT CONCAT(PARAMETER_MODE, ':', PARAMETER_NAME, ':', DTD_IDENTIFIER)
             FROM information_schema.PARAMETERS
             WHERE SPECIFIC_SCHEMA = DATABASE() AND SPECIFIC_NAME = ? AND ORDINAL_POSITION > 0
             ORDER BY ORDINAL_POSITION"
        );
        $statement->execute([$procedure]);

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private function routineDefinition(string $procedure): string
    {
        $statement = $this->pdo()->prepare(
            'SELECT ROUTINE_DEFINITION FROM information_schema.ROUTINES
             WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = ? AND ROUTINE_TYPE = ?'
        );
        $statement->execute([$procedure, 'PROCEDURE']);

        return (string) $statement->fetchColumn();
    }

    private function statusId(string $symbol): int
    {
        $statement = $this->pdo()->prepare('SELECT ma_tt FROM trang_thai_lam_viec WHERE BINARY ky_hieu = BINARY ?');
        $statement->execute([$symbol]);

        return (int) $statement->fetchColumn();
    }

    private function role(string $symbol): int
    {
        $statement = $this->pdo()->prepare('SELECT ma_vt FROM vai_tro WHERE BINARY ky_hieu = BINARY ?');
        $statement->execute([$symbol]);

        return (int) $statement->fetchColumn();
    }

    private function assertProcedureError(string $code, callable $operation): void
    {
        try {
            $operation();
            $this->fail("Expected procedure error {$code}.");
        } catch (PDOException $exception) {
            $this->assertStringContainsString($code, $exception->getMessage());
        }
    }

    private function assertAvatarFailureKeepsOutNullAndRow(string $code, string $maNv, ?string $newPath): void
    {
        $canonicalCode = strtoupper(trim($maNv));
        $before = $this->employee($canonicalCode);
        $this->pdo()->exec('SET @nv_anh_cu = NULL');

        $this->assertProcedureError($code, function () use ($maNv, $newPath): void {
            $statement = $this->pdo()->prepare('CALL sp_nhan_vien_cap_nhat_anh(?, ?, @nv_anh_cu)');
            $statement->execute([$maNv, $newPath]);
            $statement->closeCursor();
        });

        $this->assertNull($this->pdo()->query('SELECT @nv_anh_cu')->fetchColumn());
        $this->assertSame($before, $this->employee($canonicalCode));
    }
}
