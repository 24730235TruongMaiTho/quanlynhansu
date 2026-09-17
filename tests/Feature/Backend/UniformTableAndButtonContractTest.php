<?php

namespace Tests\Feature\Backend;

use Tests\TestCase;

final class UniformTableAndButtonContractTest extends TestCase
{
    public function test_backend_shared_css_defines_the_uniform_button_and_sort_contracts(): void
    {
        $css = file_get_contents(public_path('backend/css/style.css'));

        self::assertIsString($css);
        self::assertStringContainsString('.btn-icon-text', $css);
        self::assertStringContainsString('gap: .5rem', $css);
        self::assertStringContainsString('.table-sort-control', $css);
    }

    public function test_backend_table_views_use_the_shared_sort_control_without_unicode_arrows(): void
    {
        $directory = new \RecursiveDirectoryIterator(resource_path('views/backend'));
        $iterator = new \RecursiveIteratorIterator($directory);
        $paths = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && str_ends_with($file->getFilename(), '.blade.php')) {
                $paths[] = $file->getPathname();
            }
        }
        $views = array_map(static fn (string $path): string => (string) file_get_contents($path), $paths);
        $content = implode("\n", $views);

        self::assertStringContainsString('table-sort-control', $content);
        self::assertStringNotContainsString('↑', $content);
        self::assertStringNotContainsString('↓', $content);
        self::assertStringNotContainsString('↕', $content);
    }

    public function test_server_side_lists_validate_and_map_sort_inputs(): void
    {
        $contracts = [
            app_path('Http/Requests/ListChamCongEmployeeRequest.php'),
            app_path('Http/Requests/ListNghiPhepEmployeeRequest.php'),
            app_path('Http/Requests/ListTaiKhoanRequest.php'),
        ];

        foreach ($contracts as $path) {
            self::assertFileExists($path);
            self::assertStringContainsString("'sort'", (string) file_get_contents($path));
            self::assertStringContainsString("'direction'", (string) file_get_contents($path));
        }

        self::assertStringContainsString("'ma_nv' => 'nv.ma_nv'", (string) file_get_contents(app_path('Repositories/NhanVienRepository.php')));
        self::assertStringContainsString('$sortColumns', (string) file_get_contents(app_path('Repositories/NhanVienRepository.php')));
        self::assertStringContainsString('$sortColumns', (string) file_get_contents(app_path('Services/NghiPhepService.php')));
        self::assertStringContainsString('$sortColumns', (string) file_get_contents(app_path('Repositories/LuongRepository.php')));
        self::assertStringContainsString('$sortColumns', (string) file_get_contents(app_path('Repositories/PhanQuyenRepository.php')));
    }

    public function test_leave_employee_page_sizes_and_attendance_header_are_canonical(): void
    {
        $leaveRequest = (string) file_get_contents(app_path('Http/Requests/ListNghiPhepEmployeeRequest.php'));
        $attendanceRequest = (string) file_get_contents(app_path('Http/Requests/ListChamCongEmployeeRequest.php'));
        $attendanceView = (string) file_get_contents(resource_path('views/backend/chamcong/index.blade.php'));

        self::assertStringContainsString('Rule::in([10, 20, 50])', $leaveRequest);
        self::assertStringNotContainsString("'max:100'", $leaveRequest);
        self::assertStringContainsString("'tong_gio_lam'", $attendanceRequest);
        self::assertStringContainsString('data-attendance-employee-sort="{{ $sortColumn }}"', $attendanceView);
        self::assertStringContainsString("'tong_gio_lam' => 'Tổng giờ'", $attendanceView);
    }

    public function test_dynamic_icon_buttons_update_only_the_nested_label(): void
    {
        $attendance = (string) file_get_contents(resource_path('js/frontend/chamcong/chamcong.js'));
        $leave = (string) file_get_contents(resource_path('js/frontend/nghiphep/nghiphep.js'));
        $salary = (string) file_get_contents(resource_path('js/frontend/luong/luong.js'));

        self::assertStringContainsString('setButtonLabel', $attendance);
        self::assertStringContainsString('setButtonLabel', $leave);
        self::assertStringContainsString('setButtonLabel', $salary);
        self::assertStringNotContainsString('elements.updateButton.textContent =', $attendance);
        self::assertStringNotContainsString('elements.exportButton.textContent =', $salary);
    }
}
