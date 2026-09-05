<?php

namespace Tests\Feature\Backend;

use Tests\TestCase;

class NghiPhepJsonListContractTest extends TestCase
{
    public function test_leave_json_list_has_allowlisted_tab_and_server_counts(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Backend/NghiPhepController.php'));
        $service = file_get_contents(app_path('Services/NghiPhepService.php'));

        self::assertIsString($controller);
        self::assertIsString($service);
        self::assertStringContainsString("'tab' => ['nullable', 'in:pending,history']", $controller);
        self::assertStringContainsString("'tab' => \$validated['tab'] ?? null", $controller);
        self::assertStringContainsString("'counts' =>", $service);
        self::assertStringContainsString("whereIn('np.trang_thai_duyet', [1, 2])", $service);
        self::assertStringContainsString("'message' => 'Không thể tải danh sách nghỉ phép.'", $service);
    }

    public function test_explicit_tabs_are_the_only_status_defaults(): void
    {
        $service = file_get_contents(app_path('Services/NghiPhepService.php'));
        $getAllStart = strpos($service, 'public function getAll(');
        $getByIdStart = strpos($service, 'public function getById(');
        $getAll = substr($service, $getAllStart, $getByIdStart - $getAllStart);

        self::assertIsString($service);
        self::assertIsString($getAll);
        self::assertStringContainsString("if ((\$filters['tab'] ?? null) === 'pending')", $getAll);
        self::assertStringContainsString("elseif ((\$filters['tab'] ?? null) === 'history')", $getAll);
        self::assertStringNotContainsString("\$filters['tab'] ?? 'pending'", $getAll);
    }

    public function test_legacy_status_filter_is_honored_without_a_tab(): void
    {
        $service = file_get_contents(app_path('Services/NghiPhepService.php'));

        self::assertIsString($service);
        self::assertStringContainsString("array_key_exists('trang_thai_duyet', \$filters)", $service);
        self::assertStringContainsString("\$filters['trang_thai_duyet'] !== null", $service);
    }

    public function test_missing_tab_and_status_do_not_force_pending(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Backend/NghiPhepController.php'));
        $service = file_get_contents(app_path('Services/NghiPhepService.php'));
        $getAllStart = strpos($service, 'public function getAll(');
        $getByIdStart = strpos($service, 'public function getById(');
        $getAll = substr($service, $getAllStart, $getByIdStart - $getAllStart);

        self::assertIsString($controller);
        self::assertIsString($service);
        self::assertIsString($getAll);
        self::assertStringContainsString("'tab' => \$validated['tab'] ?? null", $controller);
        self::assertStringNotContainsString("'tab' => \$validated['tab'] ?? 'pending'", $controller);
        self::assertStringContainsString("elseif (\n                array_key_exists('trang_thai_duyet', \$filters)", $getAll);
    }
}
