<?php

namespace Tests\Feature\Backend\Dashboard;

use Tests\TestCase;

final class DashboardFeatureTest extends TestCase
{
    public function test_dashboard_escapes_database_values_before_inserting_contract_rows_as_html(): void
    {
        $source = file_get_contents(resource_path('views/backend/tongquan/index.blade.php'));
        self::assertIsString($source);
        self::assertStringContainsString('function escapeHtml(value)', $source);
        self::assertStringContainsString('formatDisplayDate(contract.ngay_bat_dau)', $source);
        self::assertStringContainsString('formatDisplayDate(contract.ngay_ket_thuc)', $source);
        self::assertStringContainsString('escapeHtml(contract.ho_ten', $source);
        self::assertStringContainsString('escapeHtml(contract.ten_loai_hop_dong', $source);
        self::assertStringContainsString('escapeHtml(startDate)', $source);
        self::assertStringContainsString('escapeHtml(endDate)', $source);
        self::assertStringNotContainsString('escapeHtml(contract.ngay_bat_dau', $source);
        self::assertStringNotContainsString('escapeHtml(contract.ngay_ket_thuc', $source);
        self::assertStringContainsString('escapeHtml(badgeText)', $source);
    }

    public function test_dashboard_controller_does_not_expose_exception_details(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Backend/DashboardController.php'));
        self::assertIsString($source);
        self::assertStringNotContainsString('config(\'app.debug\') ? $e->getMessage()', $source);
        self::assertStringContainsString('\'error\' => null', $source);
        self::assertStringContainsString('\'error\' => \'Vui lòng thử lại sau\'', $source);
    }
}
