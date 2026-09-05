<?php

namespace Tests\Feature\Backend;

use Tests\TestCase;

final class ContentFourFeedbackUiTest extends TestCase
{
    public function test_contract_list_matches_the_feedback_contract(): void
    {
        $source = file_get_contents(resource_path('views/backend/hopdong/index.blade.php'));

        self::assertIsString($source);
        self::assertStringContainsString('Bộ lọc hợp đồng', $source);
        self::assertStringContainsString('[5, 10, 20, 50, 100]', $source);
        self::assertStringContainsString("backend.partials.pagination-summary", $source);
        self::assertStringContainsString("backend.partials.pagination", $source);
        self::assertStringNotContainsString('data-row-action-select', $source);
        self::assertStringContainsString('btn-icon-action', $source);
        self::assertStringContainsString('Áp dụng bộ lọc', $source);
        self::assertStringNotContainsString('<th scope="col">Lương cơ bản</th>', $source);
    }

    public function test_contract_form_and_permission_pages_share_page_hierarchy(): void
    {
        $contractForm = file_get_contents(resource_path('views/backend/hopdong/form.blade.php'));
        $permissionForm = file_get_contents(resource_path('views/backend/vaitro/permissions.blade.php'));

        self::assertIsString($contractForm);
        self::assertIsString($permissionForm);
        self::assertStringContainsString('<x-backend.page-header', $contractForm);
        self::assertStringContainsString('icon="bi-file-earmark-text"', $contractForm);
        self::assertStringContainsString('<x-backend.page-header', $permissionForm);
        self::assertStringContainsString('icon="bi-shield-lock"', $permissionForm);
        self::assertStringContainsString('Quyền theo module', $permissionForm);
    }

    public function test_account_page_is_named_as_role_assignment_not_direct_permission_management(): void
    {
        $accountPage = file_get_contents(resource_path('views/backend/taikhoan/index.blade.php'));
        $sidebar = file_get_contents(resource_path('views/backend/layouts/sidebar.blade.php'));

        self::assertIsString($accountPage);
        self::assertIsString($sidebar);
        self::assertStringContainsString('Phân Quyền', $accountPage);
        self::assertStringContainsString("backend.taikhoan.assign-roles", $accountPage);
        self::assertStringContainsString('Quyền được cấu hình theo vai trò', $accountPage);
        self::assertStringContainsString('Phân Quyền', $sidebar);
        self::assertStringNotContainsString('Gán vai trò', $sidebar);
        self::assertStringNotContainsString('Phân quyền tài khoản', $sidebar);
    }
}
