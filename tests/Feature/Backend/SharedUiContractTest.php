<?php

namespace Tests\Feature\Backend;

use Tests\TestCase;

final class SharedUiContractTest extends TestCase
{
    public function test_shared_actions_render_the_canonical_icons(): void
    {
        $rendered = (string) $this->view('backend.partials.action-buttons', [
            'viewUrl' => '/employees/1',
            'editUrl' => '/employees/1/edit',
            'deleteUrl' => '/employees/1',
            'resetUrl' => '/employees/1/reset-password',
            'permissionUrl' => '/roles/1/permissions',
        ]);

        self::assertStringContainsString('bi-eye', $rendered);
        self::assertStringContainsString('bi-pencil-square', $rendered);
        self::assertStringContainsString('bi-trash', $rendered);
        self::assertStringContainsString('bi-key', $rendered);
        self::assertStringContainsString('bi-shield-lock', $rendered);
    }

    public function test_filter_actions_render_canonical_icons(): void
    {
        $rendered = (string) $this->view('backend.partials.filter-actions', [
            'action' => '/employees',
            'resetUrl' => '/employees',
            'filters' => [['name' => 'search', 'label' => 'Tìm kiếm', 'type' => 'search']],
        ]);

        self::assertStringContainsString('bi-funnel', $rendered);
        self::assertStringContainsString('bi-arrow-counterclockwise', $rendered);
    }

    public function test_page_header_component_exposes_shared_layout_contract(): void
    {
        $source = file_get_contents(resource_path('views/components/backend/page-header.blade.php'));

        self::assertIsString($source);
        self::assertStringContainsString('class="left"', $source);
        self::assertStringContainsString('page-header__actions', $source);
        self::assertStringContainsString('id="{{ $titleId }}"', $source);
        self::assertStringContainsString('{{ $description }}', $source);
    }

    public function test_filter_actions_render_labels_and_exact_actions(): void
    {
        $this->view('backend.partials.filter-actions', [
            'action' => '/nhan-vien',
            'resetUrl' => '/nhan-vien',
            'filters' => [
                ['name' => 'search', 'label' => 'Tìm kiếm', 'type' => 'search'],
            ],
        ])
            ->assertSee('Tìm kiếm')
            ->assertSee('Áp dụng bộ lọc')
            ->assertSee('Đặt lại');
    }

    public function test_action_buttons_render_distinct_controls_without_dropdown(): void
    {
        $rendered = $this->view('backend.partials.action-buttons', [
            'viewUrl' => '/nhan-vien/00001',
            'editUrl' => '/nhan-vien/00001/edit',
            'deleteUrl' => '/nhan-vien/00001',
            'resetUrl' => '/nhan-vien/00001/reset-password',
            'permissionUrl' => '/nhan-vien/00001/permissions',
            'viewLabel' => 'Xem',
            'editLabel' => 'Sửa',
            'deleteLabel' => 'Xóa',
            'resetLabel' => 'Reset mật khẩu',
            'permissionLabel' => 'Phân quyền',
            'deleteConfirmMessage' => 'Bạn có chắc muốn xóa nhân viên này?',
            'resetConfirmMessage' => 'Xác nhận reset mật khẩu?',
        ]);

        $rendered
            ->assertSee('Xem')
            ->assertSee('Sửa')
            ->assertSee('Xóa')
            ->assertSee('Reset mật khẩu')
            ->assertSee('Phân quyền');

        $content = (string) $rendered;
        self::assertStringNotContainsString('<select', $content);
        self::assertSame(2, substr_count($content, '<form method="POST"'));
        self::assertStringContainsString('name="_token"', $content);
        self::assertStringContainsString('type="submit"', $content);
        self::assertStringContainsString('data-confirm-message="Bạn có chắc muốn xóa nhân viên này?"', $content);
        self::assertStringContainsString('data-confirm-message="Xác nhận reset mật khẩu?"', $content);
        self::assertStringNotContainsString('href="/nhan-vien/00001/reset-password"', $content);
    }

    public function test_table_state_exposes_status_and_alert_roles(): void
    {
        $this->view('backend.partials.table-state', [
            'state' => 'loading',
            'colspan' => 4,
        ])->assertSee('role="status"', false);

        $this->view('backend.partials.table-state', [
            'state' => 'error',
            'colspan' => 4,
        ])->assertSee('role="alert"', false);
    }

    public function test_layout_contains_scoped_shared_ui_css_contract(): void
    {
        $source = file_get_contents(resource_path('views/backend/layouts/app.blade.php'));

        self::assertIsString($source);
        self::assertStringContainsString('.filter-bar', $source);
        self::assertStringContainsString('.table-actions', $source);
        self::assertStringContainsString('.table-state', $source);
        self::assertStringContainsString('.date-field', $source);
        self::assertStringContainsString('@media', $source);
    }

    public function test_sidebar_contains_stable_groups_and_active_link_contract(): void
    {
        $source = file_get_contents(resource_path('views/backend/layouts/sidebar.blade.php'));

        self::assertIsString($source);
        foreach (['employees', 'departments', 'positions', 'contracts', 'attendance', 'leave', 'salary', 'authorization'] as $group) {
            self::assertStringContainsString('data-sidebar-group="' . $group . '"', $source);
        }
        self::assertStringContainsString('data-route-active=', $source);
        self::assertStringContainsString('aria-current="page"', $source);
    }

    public function test_backend_list_pages_use_shared_header_and_filter_width_contract(): void
    {
        $paths = [
            'resources/views/backend/nhanvien/index.blade.php',
            'resources/views/backend/phongban/index.blade.php',
            'resources/views/backend/chucvu/index.blade.php',
            'resources/views/backend/hopdong/index.blade.php',
            'resources/views/backend/chamcong/index.blade.php',
            'resources/views/backend/nghiphep/index.blade.php',
            'resources/views/backend/nghiphep/duyet-nghi-phep.blade.php',
            'resources/views/backend/luong/index.blade.php',
            'resources/views/backend/vaitro/index.blade.php',
            'resources/views/backend/taikhoan/index.blade.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(base_path($path));

            self::assertIsString($source, $path);
            self::assertStringContainsString('<x-backend.page-header', $source, $path);
            self::assertDoesNotMatchRegularExpression('/filter-bar__field[^>]*col-lg-7/', $source, $path);
        }

        $roleSource = file_get_contents(base_path('resources/views/backend/vaitro/index.blade.php'));
        self::assertIsString($roleSource);
        self::assertMatchesRegularExpression('/<section[^>]+aria-labelledby="role-filter-title"/', $roleSource);
        self::assertStringContainsString('id="role-search-form"', $roleSource);
    }

    public function test_backend_filters_use_the_shared_card_and_control_contract(): void
    {
        $paths = [
            'resources/views/backend/nhanvien/index.blade.php',
            'resources/views/backend/phongban/index.blade.php',
            'resources/views/backend/chucvu/index.blade.php',
            'resources/views/backend/hopdong/index.blade.php',
            'resources/views/backend/chamcong/index.blade.php',
            'resources/views/backend/nghiphep/index.blade.php',
            'resources/views/backend/nghiphep/duyet-nghi-phep.blade.php',
            'resources/views/backend/luong/index.blade.php',
            'resources/views/backend/vaitro/index.blade.php',
            'resources/views/backend/taikhoan/index.blade.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(base_path($path));

            self::assertIsString($source, $path);
            self::assertMatchesRegularExpression(
                '/<section\\b[^>]*\\bfilter-card\\b[^>]*>[\\s\\S]*?<\\/section>/i',
                $source,
                $path . ' must expose a filter card',
            );
            preg_match('/<section\\b[^>]*\\bfilter-card\\b[^>]*>[\\s\\S]*?<\\/section>/i', $source, $matches);
            $filterCard = $matches[0] ?? '';

            self::assertStringContainsString('card-header bg-white py-3', $filterCard, $path);
            self::assertStringContainsString('card-body', $filterCard, $path);
            self::assertStringContainsString('filter-bar', $filterCard, $path);
            self::assertDoesNotMatchRegularExpression('/\\b(?:form-control|form-select|input-group|btn)-(?:sm)\\b/i', $filterCard, $path);
            self::assertDoesNotMatchRegularExpression('/\\bvisually-hidden\\b/i', $filterCard, $path);
            self::assertStringContainsString('btn-primary', $filterCard, $path);
            self::assertStringContainsString('btn-outline-secondary', $filterCard, $path);
        }
    }

    public function test_leave_create_and_role_list_use_the_standard_content_width(): void
    {
        $leaveCreate = file_get_contents(base_path('resources/views/backend/nghiphep/create.blade.php'));
        $roleList = file_get_contents(base_path('resources/views/backend/vaitro/index.blade.php'));

        self::assertIsString($leaveCreate);
        self::assertIsString($roleList);
        self::assertStringContainsString('id="leave-create-content"', $leaveCreate);
        self::assertDoesNotMatchRegularExpression('/max-width\\s*:\s*(?:1040|760)px/i', $leaveCreate);
        self::assertStringContainsString('container-fluid container-xxl py-4', $roleList);
        self::assertStringNotContainsString('<main class="content-area"', $roleList);
        self::assertDoesNotMatchRegularExpression('/<main\b[^>]*\bcontent-area\b/i', $roleList);
    }

    public function test_paginated_footers_use_the_shared_centered_layout_contract(): void
    {
        $paths = [
            'resources/views/backend/nhanvien/index.blade.php',
            'resources/views/backend/phongban/index.blade.php',
            'resources/views/backend/chucvu/index.blade.php',
            'resources/views/backend/hopdong/index.blade.php',
            'resources/views/backend/chamcong/index.blade.php',
            'resources/views/backend/nghiphep/index.blade.php',
            'resources/views/backend/nghiphep/duyet-nghi-phep.blade.php',
            'resources/views/backend/luong/index.blade.php',
            'resources/views/backend/vaitro/index.blade.php',
            'resources/views/backend/taikhoan/index.blade.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(base_path($path));
            self::assertIsString($source, $path);
            if (str_contains($source, 'backend-pagination')) {
                self::assertStringContainsString('pagination-footer', $source, $path);
            }
        }

        $css = file_get_contents(public_path('backend/css/style.css'));
        self::assertIsString($css);
        self::assertMatchesRegularExpression(
            '/\.pagination-footer\s*\{[^}]*display:\s*grid\s*!important;[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)\s+auto\s+minmax\(0,\s*1fr\)/s',
            $css,
        );
        self::assertMatchesRegularExpression(
            '/\.pagination-footer\s*>\s*\.backend-pagination\s*\{[^}]*grid-column:\s*2;[^}]*justify-self:\s*center;/s',
            $css,
        );
    }

    public function test_contract_filter_toggle_uses_the_shared_alignment_modifier(): void
    {
        $source = file_get_contents(base_path('resources/views/backend/hopdong/index.blade.php'));

        self::assertIsString($source);
        self::assertStringContainsString('filter-bar__field--toggle', $source);
        self::assertStringContainsString('for="sap_het_han"', $source);
    }

    public function test_period_filters_use_a_responsive_period_modifier(): void
    {
        $periods = [
            [
                'path' => 'resources/views/backend/chamcong/index.blade.php',
                'picker' => 'attendance-period-picker',
            ],
            [
                'path' => 'resources/views/backend/luong/index.blade.php',
                'picker' => 'salary-period-picker',
            ],
        ];

        foreach ($periods as $period) {
            $source = file_get_contents(base_path($period['path']));
            $pickerPattern = preg_quote($period['picker'], '/');
            $periodFieldPattern = '/<div\s+class="(?<field>[^\"]*\bfilter-bar__field\b[^\"]*)"[^>]*>(?:(?!<div\s+class="[^\"]*\bfilter-bar__field\b)[\s\S])*?\b' . $pickerPattern . '\b/i';

            self::assertIsString($source, $period['path']);
            self::assertSame(
                1,
                preg_match($periodFieldPattern, $source, $matches),
                $period['path'] . ' must expose a field containing the period picker',
            );
            $fieldClass = $matches['field'] ?? '';
            self::assertStringContainsString('filter-bar__field--period', $fieldClass, $period['path']);
            self::assertStringNotContainsString('filter-bar__field--compact', $fieldClass, $period['path']);
        }

        $layout = file_get_contents(base_path('resources/views/backend/layouts/app.blade.php'));

        self::assertIsString($layout);
        self::assertMatchesRegularExpression(
            '/\.filter-bar \.filter-bar__field--period\s*\{[^}]*grid-column:\s*span\s+2;[^}]*max-width:\s*32rem;/s',
            $layout,
        );
        self::assertMatchesRegularExpression(
            '/@media\s*\(max-width:\s*767\.98px\)[\s\S]*?\.filter-bar \.filter-bar__field--period\s*\{[^}]*grid-column:\s*1\s*\/\s*-1;[^}]*max-width:\s*none;/s',
            $layout,
        );
        self::assertMatchesRegularExpression(
            '/@media\s*\(max-width:\s*575\.98px\)[\s\S]*?\.filter-bar \.filter-bar__field--period\s*\{[^}]*width:\s*100%;/s',
            $layout,
        );
        self::assertStringContainsString('.filter-bar__field--period .attendance-period-picker', $layout);
        self::assertStringContainsString('.filter-bar__field--period .salary-period-picker', $layout);
        self::assertStringContainsString('flex-wrap: wrap;', $layout);
    }

    public function test_backend_action_buttons_have_bootstrap_icons(): void
    {
        $files = \Illuminate\Support\Facades\File::allFiles(resource_path('views/backend'));
        $missingIcons = [];

        foreach ($files as $file) {
            $source = file_get_contents($file);
            if (! is_string($source)) {
                continue;
            }

            preg_match_all('/<(button|a)\\b[^>]*\\bclass=["\\\'][^"\\\']*\\bbtn(?:[\\s"\\\'])[^"\\\']*["\\\'][^>]*>([\\s\\S]*?)<\\/\\1>/i', $source, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $attributes = $match[0];
                if (str_contains($attributes, 'btn-close')) {
                    continue;
                }
                if (! preg_match('/<i\\b[^>]*\\bclass=["\\\'][^"\\\']*\\bbi(?:-|["\\\'])/i', $match[2])) {
                    $missingIcons[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
                }
            }
        }

        self::assertSame([], array_values(array_unique($missingIcons)), "Action controls missing Bootstrap Icons: \n" . implode("\n", array_unique($missingIcons)));
    }
}
