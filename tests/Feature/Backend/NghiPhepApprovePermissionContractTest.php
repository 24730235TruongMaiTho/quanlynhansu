<?php

namespace Tests\Feature\Backend;

use App\Enums\NghiPhepPermission;
use App\Enums\PermissionAction;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class NghiPhepApprovePermissionContractTest extends TestCase
{
    public function test_approve_permission_is_a_custom_non_crud_definition(): void
    {
        self::assertSame('NghiPhep.Approve', NghiPhepPermission::Duyet->value);
        self::assertSame(43, NghiPhepPermission::Duyet->id());
        self::assertSame('NghiPhep', NghiPhepPermission::Duyet->module());
        self::assertNull(NghiPhepPermission::Duyet->action());
        self::assertNull(PermissionAction::fromSymbol(NghiPhepPermission::Duyet->value));
    }

    public function test_fresh_seed_and_additive_upgrade_are_idempotent_and_fail_closed(): void
    {
        $seed = File::get(base_path('database/sql/du_lieu_mau.sql'));
        $upgrade = File::get(base_path('database/sql/rbac/2026_09_09_001_add_nghiphep_approve_permission.sql'));

        self::assertStringContainsString("(43, N'NghiPhep.Approve', N'Duyệt nghỉ phép', N'NghiPhep')", $seed);
        self::assertStringContainsString('ALTER TABLE quyen AUTO_INCREMENT = 44;', $seed);
        self::assertStringContainsString('(1, 43)', $seed);
        self::assertStringContainsString('(4, 43)', $seed);
        self::assertStringContainsString('RBAC_NGHIPHEP_APPROVE_ID_COLLISION', $upgrade);
        self::assertStringContainsString('RBAC_NGHIPHEP_APPROVE_SYMBOL_COLLISION', $upgrade);
        self::assertStringContainsString('INSERT IGNORE INTO vai_tro_quyen', $upgrade);
        self::assertStringContainsString('WHERE ma_vt = 1', $upgrade);
        self::assertStringContainsString('WHERE ma_vt = 4', $upgrade);
        self::assertStringContainsString('DROP PROCEDURE IF EXISTS sp_upgrade_nghiphep_approve_permission', $upgrade);
    }
}
