<?php

namespace Tests\Feature\Backend\Dashboard;

use Tests\TestCase;

final class DashboardFeatureTest extends TestCase
{
    public function test_dashboard_writes_database_values_with_text_content_in_contract_rows(): void
    {
        $source = file_get_contents(resource_path('views/backend/tongquan/index.blade.php'));
        self::assertIsString($source);
        self::assertStringContainsString('cell.textContent = String(value)', $source);
        self::assertStringContainsString('formatDisplayDate(contract.ngay_bat_dau)', $source);
        self::assertStringContainsString('formatDisplayDate(contract.ngay_ket_thuc)', $source);
        self::assertStringContainsString('cell.textContent = String(value)', $source);
        self::assertStringNotContainsString('tbody.innerHTML = html', $source);
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
