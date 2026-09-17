<?php

namespace Tests\Feature\Backend;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class FeedbackV6RbacContractTest extends TestCase
{
    public function test_employee_upgrade_is_superseded_by_approval_gated_cleanup(): void
    {
        $script = File::get(base_path('database/sql/rbac/2026_09_16_001_add_employee_self_service_permissions.sql'));
        $cleanup = File::get(base_path('database/sql/rbac/2026_09_17_001_remove_employee_management_permissions.sql'));

        self::assertStringContainsString('RBAC_EMPLOYEE_SELF_SERVICE_SCRIPT_SUPERSEDED', $script);
        $scriptUpper = strtoupper($script);
        $cleanupUpper = strtoupper($cleanup);
        self::assertStringNotContainsString('INSERT INTO VAI_TRO_QUYEN', $scriptUpper);
        self::assertStringNotContainsString('DELETE FROM VAI_TRO_QUYEN', $scriptUpper);
        self::assertStringContainsString('@approved_employee_self_service_cleanup', $cleanup);
        self::assertStringContainsString('RBAC_EMPLOYEE_SELF_SERVICE_CLEANUP_APPROVAL_REQUIRED', $cleanup);
        self::assertStringContainsString('MA_QUYEN IN (25, 26, 33)', $cleanupUpper);
        self::assertStringContainsString('RBAC_EMPLOYEE_PERMISSION_CLEANUP_FAILED', $cleanup);
        self::assertStringNotContainsString('DROP DATABASE', $cleanupUpper);
    }
}
