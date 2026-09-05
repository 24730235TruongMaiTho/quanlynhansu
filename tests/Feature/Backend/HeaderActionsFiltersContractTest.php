<?php

namespace Tests\Feature\Backend;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class HeaderActionsFiltersContractTest extends TestCase
{
    public function test_page_header_renders_an_icon_and_colored_breadcrumb_ancestors(): void
    {
        $rendered = Blade::render(<<<'BLADE'
            <x-backend.page-header
                title="Danh sách nhân viên"
                title-id="employees-title"
                icon="bi-people"
                :breadcrumbs="[
                    ['label' => 'Tổng quan', 'url' => '/tong-quan'],
                    ['label' => 'Nhân viên'],
                ]"
            />
        BLADE);

        self::assertStringContainsString(
            '<i class="bi bi-people page-header__icon" aria-hidden="true"></i>',
            $rendered,
        );
        self::assertStringContainsString(
            '<a class="breadcrumb-item__link" href="/tong-quan">Tổng quan</a>',
            $rendered,
        );
        self::assertStringContainsString('breadcrumb-item active', $rendered);
        self::assertStringContainsString('aria-current="page"', $rendered);
        self::assertStringContainsString('page-header__title-row', $rendered);
    }

    public function test_page_header_binds_description_id_to_the_description_paragraph(): void
    {
        $rendered = Blade::render(<<<'BLADE'
            <x-backend.page-header
                title="Đổi mật khẩu"
                icon="bi-key"
                description="Dùng mật khẩu hiện tại để đặt mật khẩu mới."
                description-id="password-form-help"
            />
        BLADE);

        self::assertMatchesRegularExpression(
            '/<p[^>]+id="password-form-help"[^>]*>Dùng mật khẩu hiện tại để đặt mật khẩu mới\.<\/p>/s',
            $rendered,
        );
        self::assertDoesNotMatchRegularExpression(
            '/<header[^>]*description-id="password-form-help"[^>]*>/s',
            $rendered,
        );
    }

    public function test_every_backend_page_header_declares_an_explicit_icon_and_raw_h1_is_sanctioned(): void
    {
        $missingIcon = [];
        $rawHeaders = [];

        foreach (File::allFiles(resource_path('views/backend')) as $file) {
            $source = file_get_contents($file->getPathname());
            if (! is_string($source)) {
                continue;
            }

            if (preg_match_all('/<x-backend\.page-header\b([\s\S]*?)\n\s*>/i', $source, $matches)) {
                foreach ($matches[1] as $attributes) {
                    if (! preg_match('/\b:?(?:icon)\s*=/', $attributes)) {
                        $missingIcon[] = $file->getRelativePathname();
                    }
                }
            }

            if (preg_match('/<h1\b/i', $source)) {
                $rawHeaders[] = $file->getRelativePathname();
            }
        }

        self::assertSame([], $missingIcon, 'Every backend page-header usage must provide an explicit icon.');
        self::assertSame([], $rawHeaders, 'Backend pages must use the shared page-header component for h1.');
    }

    public function test_edit_delete_actions_are_icon_only_but_other_actions_keep_text(): void
    {
        $rendered = (string) $this->view('backend.partials.action-buttons', [
            'viewUrl' => '/employees/1',
            'editUrl' => '/employees/1/edit',
            'deleteUrl' => '/employees/1',
            'resetUrl' => '/employees/1/reset-password',
            'permissionUrl' => '/roles/1/permissions',
            'editLabel' => 'Chỉnh sửa hồ sơ',
            'deleteLabel' => 'Xóa hồ sơ',
            'resetLabel' => 'Reset mật khẩu',
            'permissionLabel' => 'Phân quyền',
        ]);

        self::assertMatchesRegularExpression(
            '/<a[^>]+class="[^"]*btn-icon-action[^"]*"[^>]+aria-label="Chỉnh sửa hồ sơ"[^>]+title="Chỉnh sửa hồ sơ"[^>]*>\s*<i[^>]+aria-hidden="true"[^>]*><\/i>\s*<\/a>/s',
            $rendered,
        );
        self::assertMatchesRegularExpression(
            '/<button[^>]+class="[^"]*btn-icon-action[^"]*"[^>]+aria-label="Xóa hồ sơ"[^>]+title="Xóa hồ sơ"[^>]*>\s*<i[^>]+aria-hidden="true"[^>]*><\/i>\s*<\/button>/s',
            $rendered,
        );
        self::assertStringContainsString('>Xem</a>', $rendered);
        self::assertStringContainsString('>Reset mật khẩu</button>', $rendered);
        self::assertStringContainsString('>Phân quyền</a>', $rendered);
    }

    public function test_static_create_controls_show_icon_and_visible_text(): void
    {
        $expected = [
            'resources/views/backend/nhanvien/index.blade.php' => 'Thêm nhân viên',
            'resources/views/backend/phongban/index.blade.php' => 'Thêm phòng ban',
            'resources/views/backend/chucvu/index.blade.php' => 'Thêm chức vụ',
            'resources/views/backend/hopdong/index.blade.php' => 'Thêm hợp đồng',
            'resources/views/backend/nghiphep/index.blade.php' => 'Thêm nghỉ phép',
            'resources/views/backend/luong/index.blade.php' => 'Thêm thông tin lương',
            'resources/views/backend/vaitro/index.blade.php' => 'Thêm vai trò',
        ];

        foreach ($expected as $path => $label) {
            $source = file_get_contents(base_path($path));

            self::assertIsString($source, $path);
            $pattern = '/<(a|button)\b(?=[^>]*\baria-label="' . preg_quote($label, '/') . '")(?=[^>]*\btitle="' . preg_quote($label, '/') . '")[^>]*>([\s\S]*?)<\/\1>/u';
            self::assertMatchesRegularExpression($pattern, $source, $path . ' must expose a labeled create control');

            preg_match($pattern, $source, $matches);
            $markup = $matches[0] ?? '';

            self::assertDoesNotMatchRegularExpression('/\bbtn-icon-action\b/i', $markup, $path . ' create control must not be icon-only');
            self::assertMatchesRegularExpression('/<i\b[^>]*\bbi-plus-circle\b[^>]*\baria-hidden="true"[^>]*>\s*<\/i>/i', $markup, $path);
            self::assertMatchesRegularExpression('/>\s*<i\b[^>]*\bbi-plus-circle\b[^>]*>\s*<\/i>\s*' . preg_quote($label, '/') . '\s*</u', $markup, $path . ' create control must keep visible text');
        }

        $salary = file_get_contents(base_path('resources/views/backend/luong/index.blade.php'));
        self::assertIsString($salary);
        self::assertMatchesRegularExpression(
            '/id="add-coefficient-btn"[\s\S]*?aria-label="Thêm hệ số lương"[\s\S]*?<i\b[^>]*\bbi-plus-circle\b[^>]*>\s*<\/i>\s*Thêm hệ số lương\s*<\/button>/u',
            $salary,
        );
        self::assertDoesNotMatchRegularExpression(
            '/<button(?=[^>]*\bid="add-coefficient-btn"\b)(?=[^>]*\bbtn-icon-action\b)[^>]*>/u',
            $salary,
            'The salary coefficient create button must not use the icon-only class.',
        );
    }

    public function test_static_edit_and_delete_controls_keep_the_icon_only_contract(): void
    {
        $violations = [];

        foreach (File::allFiles(resource_path('views/backend')) as $file) {
            $source = file_get_contents($file->getPathname());
            if (! is_string($source)) {
                continue;
            }

            // Blade's object access arrow contains a literal `>`; hide it so
            // the lightweight markup contract can identify the full tag.
            $source = str_replace('->', '__BLADE_ARROW__', $source);

            preg_match_all(
                '/<(a|button)\b(?=[^>]*\bbtn-icon-action\b)[^>]*>[\s\S]*?<i\b[^>]*\bbi-(?:pencil-square|trash)\b[^>]*>[\s\S]*?<\/\1>/i',
                $source,
                $matches,
            );

            foreach ($matches[0] as $markup) {
                $openingTag = strstr($markup, '>', true) ?: $markup;
                if (! preg_match('/\baria-label="[^"]+"/i', $openingTag)
                    || ! preg_match('/\btitle="[^"]+"/i', $openingTag)
                    || preg_match('/>\s*(?:Thêm|Tạo|Sửa|Chỉnh sửa|Xóa)\s*</u', $markup)) {
                    $violations[] = $file->getRelativePathname();
                }
            }

        }

        self::assertSame([], array_values(array_unique($violations)));
    }

    public function test_action_controls_share_a_uniform_click_target_height(): void
    {
        $css = file_get_contents(public_path('backend/css/style.css'));

        self::assertIsString($css);
        self::assertMatchesRegularExpression(
            '/\.btn-icon-action\s*\{[^}]*width:\s*2\.375rem;[^}]*min-width:\s*2\.375rem;[^}]*height:\s*2\.375rem;[^}]*min-height:\s*2\.375rem;/s',
            $css,
            'Icon-only action controls must share width, height, and minimum height.',
        );
        self::assertMatchesRegularExpression(
            '/\.table-actions\s*>\s*\.btn,\s*\.table-actions\s*>\s*form\s*>\s*\.btn,\s*\.role-row-actions\s*>\s*\.btn,\s*\.role-row-actions\s*>\s*form\s*>\s*\.btn,\s*\.salary-row-actions\s*>\s*\.btn,\s*\.salary-row-actions\s*>\s*form\s*>\s*\.btn,\s*\.coefficient-row-actions\s*>\s*\.btn,\s*\.coefficient-row-actions\s*>\s*form\s*>\s*\.btn,\s*\.leave-log-row-actions\s*>\s*\.btn,\s*\.leave-log-row-actions\s*>\s*form\s*>\s*\.btn\s*\{[^}]*height:\s*2\.375rem;[^}]*min-height:\s*2\.375rem;/s',
            $css,
            'Buttons in action groups must share the standard control height.',
        );

        $salary = file_get_contents(base_path('resources/views/backend/luong/index.blade.php'));
        self::assertIsString($salary);
        self::assertDoesNotMatchRegularExpression('/\.salary-page \.salary-icon-action,[\s\S]*?min-height:\s*30px/i', $salary);
    }

    public function test_salary_page_keeps_the_canonical_container_width(): void
    {
        $view = file_get_contents(base_path('resources/views/backend/luong/index.blade.php'));
        $css = file_get_contents(resource_path('css/luong/salary-bootstrap.css'));

        self::assertIsString($view);
        self::assertIsString($css);
        self::assertMatchesRegularExpression('/class="[^"]*container-fluid[^"]*container-xxl[^"]*"/s', $view);
        self::assertDoesNotMatchRegularExpression(
            '/\.salary-page\.hr-page\s*\{[^}]*max-width\s*:/s',
            $css,
            'Salary-specific CSS must not override the shared container-xxl width.',
        );
    }

    public function test_text_action_controls_share_the_same_height_and_alignment_contract(): void
    {
        $css = file_get_contents(public_path('backend/css/style.css'));
        $partial = file_get_contents(base_path('resources/views/backend/nhanvien/partials/action-dialogs.blade.php'));

        self::assertIsString($css);
        self::assertIsString($partial);
        self::assertSame(1, preg_match('/\.table-actions\s*>\s*\.btn,[^{]+\{(?<body>[^}]*)\}/s', $css, $actionRule));
        foreach ([
            'height: 2.375rem;',
            'min-height: 2.375rem;',
            'display: inline-flex;',
            'align-items: center;',
            'justify-content: center;',
        ] as $declaration) {
            self::assertStringContainsString($declaration, $actionRule['body'], 'Shared action controls must declare ' . $declaration);
        }
        self::assertMatchesRegularExpression(
            '/class="[^"]*employee-action-dialogs[^"]*table-actions[^"]*"/s',
            $partial,
            'Reset password must participate in the shared action group.',
        );
        self::assertMatchesRegularExpression(
            '/data-reset-password-form[\s\S]*?<button[^>]*class="[^"]*btn-outline-warning[^"]*"[^>]*>[\s\S]*?Đặt lại mật khẩu/s',
            $partial,
        );
    }

    public function test_employee_reset_form_shares_the_single_table_action_row(): void
    {
        $index = file_get_contents(base_path('resources/views/backend/nhanvien/index.blade.php'));
        $partial = file_get_contents(base_path('resources/views/backend/nhanvien/partials/action-dialogs.blade.php'));

        self::assertIsString($index);
        self::assertIsString($partial);
        self::assertMatchesRegularExpression(
            '/<div class="table-actions[^>]*>[\s\S]*?@include\(\'backend\.nhanvien\.partials\.action-dialogs\',[\s\S]*?\'wrapActions\'\s*=>\s*false/s',
            $index,
            'The employee table must place reset inside its one action row.',
        );
        self::assertMatchesRegularExpression(
            '/\$wrapActions\s*=\s*\$wrapActions\s*\?\?\s*true;[\s\S]*?@if\s*\(\$wrapActions\)[\s\S]*?employee-action-dialogs[\s\S]*?@endif/s',
            $partial,
            'The dialog partial must support rendering its form without a second action wrapper.',
        );
        self::assertMatchesRegularExpression(
            '/<div class="table-actions[^">]*employee-table-actions[^">]*flex-nowrap[^">]*gap-1[^">]*"[^>]*>/s',
            $index,
            'Employee table actions must stay on one compact row so reset does not wrap below the other actions.',
        );
    }

    public function test_identifiers_use_semantic_text_while_status_badges_remain_available(): void
    {
        $identifierCases = [
            'resources/views/backend/chucvu/index.blade.php' => '/<th\s+scope="row">\s*<span class="identifier-text">\s*\{\{\s*\$position->ma_cv\s*\}\}/s',
            'resources/views/backend/hopdong/index.blade.php' => '/<th\s+scope="row">\s*<span class="identifier-text">\s*\{\{\s*\$contract->ma_hd\s*\}\}/s',
            'resources/views/backend/vaitro/permissions.blade.php' => '/<span class="identifier-text">\s*Mã vai trò:\s*\{\{\s*\$role->ma_vt\s*\}\}/s',
        ];

        foreach ($identifierCases as $path => $pattern) {
            $source = file_get_contents(base_path($path));

            self::assertIsString($source, $path);
            self::assertMatchesRegularExpression($pattern, $source, $path . ' must render its identifier as semantic text.');
            self::assertDoesNotMatchRegularExpression('/(?:Mã chức vụ|Mã hợp đồng|Mã vai trò)[^\n<]*(?:badge|bg-)/iu', $source, $path . ' must not style identifiers as badges.');
        }

        foreach ([
            'resources/views/backend/chamcong/index.blade.php' => 'selected-employee-badge',
            'resources/views/backend/nghiphep/index.blade.php' => 'selected-employee-badge',
            'resources/views/backend/luong/index.blade.php' => 'coefficient-selected-employee',
            'resources/views/backend/nghiphep/create.blade.php' => 'leave-create-log-employee',
        ] as $path => $id) {
            $source = file_get_contents(base_path($path));

            self::assertIsString($source, $path);
            self::assertMatchesRegularExpression('/<span[^>]*class="identifier-text"[^>]*id="' . preg_quote($id, '/') . '"/s', $source, $path . ' selected identity must use semantic text.');
            self::assertDoesNotMatchRegularExpression('/<span[^>]*class="[^"]*badge[^>]*"[^>]*id="' . preg_quote($id, '/') . '"/s', $source, $path . ' selected identity must not use a badge.');
        }

        self::assertStringContainsString('attendance-status-badge', file_get_contents(base_path('resources/js/frontend/chamcong/chamcong.js')));
        self::assertStringContainsString('salary-status-badge', file_get_contents(base_path('resources/js/frontend/luong/luong.js')));
        self::assertStringContainsString('leave-status-badge', file_get_contents(base_path('resources/js/frontend/nghiphep/nghiphep.js')));
        self::assertStringContainsString('text-bg-warning', file_get_contents(base_path('resources/views/backend/hopdong/index.blade.php')));
    }

    public function test_period_pickers_use_one_compact_segmented_shared_contract(): void
    {
        $layout = file_get_contents(resource_path('views/backend/layouts/app.blade.php'));
        $attendance = file_get_contents(base_path('resources/views/backend/chamcong/index.blade.php'));
        $salary = file_get_contents(base_path('resources/views/backend/luong/index.blade.php'));

        self::assertIsString($layout);
        self::assertIsString($attendance);
        self::assertIsString($salary);
        self::assertMatchesRegularExpression(
            '/\.filter-bar \.filter-bar__field--period\s*>\s*\.filter-period-controls\s*\{(?=[^}]*width:\s*min\(100%,\s*18rem\)\s*!important;)(?=[^}]*max-width:\s*18rem\s*!important;)(?=[^}]*gap:\s*0\s*!important;)(?=[^}]*overflow:\s*hidden;)[^}]*border-radius:\s*\.5rem;/s',
            $layout,
            'The shared period group must be compact and segmented on desktop.',
        );
        self::assertMatchesRegularExpression(
            '/\.filter-bar \.filter-bar__field--period\s*>\s*\.filter-period-controls\s*>\s*\.form-select[^,]*,\s*\.filter-bar \.filter-bar__field--period\s*>\s*\.filter-period-controls\s*>\s*\.form-control\s*\{[^}]*border:\s*0\s*!important;[^}]*border-radius:\s*0\s*!important;/s',
            $layout,
            'Period children must share one outer border without double borders.',
        );
        self::assertMatchesRegularExpression(
            '/\.filter-bar \.filter-bar__field--period\s*>\s*\.filter-period-controls\s*>\s*\.form-select\s*\{[^}]*flex:\s*1\s+1\s+auto;[\s\S]*?\.filter-bar \.filter-bar__field--period\s*>\s*\.filter-period-controls\s*>\s*\.form-control\s*\{[^}]*flex:\s*0\s+0\s+5\.25rem;/s',
            $layout,
            'Month must flex while year keeps a compact fixed width.',
        );
        self::assertMatchesRegularExpression(
            '/@media\s*\(max-width:\s*767\.98px\)[\s\S]*?\.filter-bar \.filter-bar__field--period\s*>\s*\.filter-period-controls\s*\{[^}]*width:\s*100%\s*!important;[^}]*max-width:\s*none\s*!important;/s',
            $layout,
            'Period groups must expand to the available width on mobile.',
        );
        self::assertDoesNotMatchRegularExpression('/filter-period-controls[^>]*\bgap-2\b/', $attendance . $salary);
        self::assertDoesNotMatchRegularExpression('/\.attendance-page \.attendance-period-picker\s*\{|\.salary-page \.salary-period-picker\s*\{/s', $attendance . $salary);
    }

    public function test_period_filters_put_label_above_a_shared_labeled_control_group(): void
    {
        foreach ([
            'resources/views/backend/chamcong/index.blade.php' => 'attendance-period-label',
            'resources/views/backend/luong/index.blade.php' => 'salary-period-label',
        ] as $path => $labelId) {
            $source = file_get_contents(base_path($path));

            self::assertIsString($source, $path);
            self::assertMatchesRegularExpression(
                '/<div\s+class="[^"]*filter-bar__field[^"]*filter-bar__field--period[^"]*"[\s\S]*?<label[^>]+class="form-label"[^>]+id="' . preg_quote($labelId, '/') . '"[^>]*>[\s\S]*?<div[^>]+class="[^"]*filter-period-controls[^"]*"[^>]+role="group"[^>]+aria-labelledby="' . preg_quote($labelId, '/') . '"/i',
                $source,
                $path . ' must expose a labeled period group',
            );
            self::assertStringNotContainsString('period-picker > label', $source, $path);
        }
    }

    public function test_period_controls_use_the_standard_filter_control_height(): void
    {
        $layout = file_get_contents(resource_path('views/backend/layouts/app.blade.php'));

        self::assertIsString($layout);
        self::assertMatchesRegularExpression(
            '/\.filter-bar \.filter-bar__field--period\s*>\s*\.filter-period-controls\s*\{[^}]*height:\s*2\.375rem\s*!important;[^}]*min-height:\s*2\.375rem\s*!important;/s',
            $layout,
            'The shared period wrapper must use the standard filter height.',
        );
        self::assertMatchesRegularExpression(
            '/\.filter-bar \.filter-bar__field--period\s*>\s*\.filter-period-controls\s*>\s*\.form-(?:select|control)[^{]*\{[^}]*height:\s*2\.375rem\s*!important;[^}]*min-height:\s*2\.375rem\s*!important;/s',
            $layout,
            'Period controls must use the same height as regular filter controls.',
        );

        foreach ([
            'resources/views/backend/chamcong/index.blade.php' => 'attendance',
            'resources/views/backend/luong/index.blade.php' => 'salary',
        ] as $path => $prefix) {
            $source = file_get_contents(base_path($path));

            self::assertIsString($source, $path);
            self::assertDoesNotMatchRegularExpression(
                '/\.' . preg_quote($prefix, '/') . '-(?:period-picker|month-select|year-input)[^{]*\{[^}]*height:\s*32px/i',
                $source,
                $path . ' must not reintroduce compact 32px period controls',
            );
            self::assertDoesNotMatchRegularExpression(
                '/\.' . preg_quote($prefix, '/') . '-period-picker[^}]*min-height:\s*38px/i',
                $source,
                $path . ' must not reintroduce a shorter period wrapper',
            );
        }
    }

    public function test_all_filter_controls_share_height_padding_and_label_alignment(): void
    {
        $layout = file_get_contents(resource_path('views/backend/layouts/app.blade.php'));

        self::assertIsString($layout);
        self::assertMatchesRegularExpression(
            '/\.filter-bar \.form-control\s*,\s*\.filter-bar \.form-select\s*\{[^}]*height:\s*2\.375rem\s*!important;[^}]*min-height:\s*2\.375rem\s*!important;[^}]*padding-block:\s*\.375rem\s*!important;/s',
            $layout,
            'All filter form controls must share the standard height and vertical padding.',
        );
        self::assertMatchesRegularExpression(
            '/\.filter-bar \.filter-bar__field\s*>\s*\.form-label\s*\{[^}]*display:\s*block;[^}]*margin-bottom:\s*\.5rem;/s',
            $layout,
            'Every direct filter label must share block layout and standard spacing.',
        );
        self::assertDoesNotMatchRegularExpression(
            '/\.filter-bar \.filter-bar__field--period\s*>\s*\.form-label\s*\{/s',
            $layout,
            'The period label must use the shared direct filter-label rule.',
        );
        self::assertMatchesRegularExpression(
            '/\.filter-bar \.input-group-text\s*\{[^}]*align-items:\s*center;[^}]*height:\s*2\.375rem\s*!important;[^}]*min-height:\s*2\.375rem\s*!important;/s',
            $layout,
            'Input-group adornments must remain aligned with filter controls.',
        );
    }

    public function test_tai_khoan_breadcrumb_links_the_dashboard_ancestor(): void
    {
        $source = file_get_contents(resource_path('views/backend/taikhoan/index.blade.php'));

        self::assertIsString($source);
        self::assertMatchesRegularExpression(
            "/\['label'\s*=>\s*'Hệ thống'\s*,\s*'url'\s*=>\s*route\('backend\.tongquan\.index'\)\s*\]/u",
            $source,
            'The Hệ thống breadcrumb must link to the dashboard.',
        );

        $rendered = Blade::render(<<<'BLADE'
            <x-backend.page-header
                title="Phân Quyền"
                icon="bi-person-lock"
                :breadcrumbs="[
                    ['label' => 'Hệ thống', 'url' => '/tong-quan'],
                    ['label' => 'Phân Quyền'],
                ]"
            />
        BLADE);

        self::assertSame(1, substr_count($rendered, 'aria-current="page"'));
        self::assertStringContainsString(
            '<a class="breadcrumb-item__link" href="/tong-quan">Hệ thống</a>',
            $rendered,
        );
    }

    public function test_simple_list_actions_are_direct_uniform_icon_buttons_and_filters_use_apply_label(): void
    {
        foreach ([
            'resources/views/backend/chucvu/index.blade.php',
            'resources/views/backend/phongban/index.blade.php',
            'resources/views/backend/hopdong/index.blade.php',
        ] as $path) {
            $source = file_get_contents(base_path($path));

            self::assertIsString($source, $path);
            self::assertStringNotContainsString('data-row-action-select', $source, $path . ' must not require a select action control.');
            self::assertMatchesRegularExpression(
                '/class="table-actions[^"]*"[\s\S]*?btn-icon-action[\s\S]*?aria-label="Sửa[^\"]*"[\s\S]*?title="Sửa[^\"]*"/u',
                $source,
                $path . ' must expose a direct edit action.',
            );
            self::assertMatchesRegularExpression(
                '/btn-icon-action[\s\S]*?aria-label="Xóa[^\"]*"[\s\S]*?title="Xóa[^\"]*"/u',
                $source,
                $path . ' must expose a direct delete action.',
            );
            self::assertStringContainsString('Áp dụng bộ lọc', $source, $path . ' must use the shared filter submit label.');
        }
    }

    public function test_role_actions_use_the_shared_38px_action_group(): void
    {
        $script = file_get_contents(base_path('resources/js/frontend/vaitro/vaitro.js'));
        $css = file_get_contents(public_path('backend/css/style.css'));

        self::assertIsString($script);
        self::assertIsString($css);
        self::assertMatchesRegularExpression('/<div class="table-actions[^\"]*role-row-actions[^\"]*">/s', $script);
        self::assertStringContainsString('.role-row-actions > .btn,', $css);
        self::assertStringContainsString('height: 2.375rem;', $css, 'Role actions must use the shared 38px centered control contract.');
        self::assertStringContainsString('display: inline-flex;', $css, 'Role actions must use the shared 38px centered control contract.');
        self::assertStringContainsString('align-items: center;', $css, 'Role actions must use the shared 38px centered control contract.');
        self::assertStringContainsString('justify-content: center;', $css, 'Role actions must use the shared 38px centered control contract.');
    }

    public function test_salary_action_controls_do_not_reintroduce_the_30px_local_override(): void
    {
        $view = file_get_contents(base_path('resources/views/backend/luong/index.blade.php'));
        $script = file_get_contents(base_path('resources/js/frontend/luong/luong.js'));

        self::assertIsString($view);
        self::assertIsString($script);
        self::assertDoesNotMatchRegularExpression(
            '/\.salary-page\s+\.salary-icon-action[^}]*height:\s*30px/s',
            $view,
            'Salary edit/delete controls must use the shared 38px action height.',
        );
        self::assertMatchesRegularExpression(
            '/\.salary-page\s+\.salary-icon-action[^}]*width:\s*2\.375rem[^}]*height:\s*2\.375rem/s',
            $view,
        );
        self::assertStringContainsString('salary-row-create-action', $script);
    }

    public function test_salary_coefficient_dates_use_shared_display_date_fields(): void
    {
        $view = file_get_contents(base_path('resources/views/backend/luong/index.blade.php'));
        $script = file_get_contents(base_path('resources/js/frontend/luong/luongHeSoCreateUpdate.js'));

        self::assertIsString($view);
        self::assertIsString($script);
        foreach (['coefficient-from-date', 'coefficient-to-date'] as $id) {
            $fieldStart = strpos($view, 'id="' . $id . '"');
            self::assertNotFalse($fieldStart, $id . ' must remain addressable.');
            $field = substr($view, $fieldStart - 250, 650);
            self::assertStringContainsString('type="text"', $field);
            self::assertStringContainsString('placeholder="dd/mm/yyyy"', $field);
            self::assertStringContainsString('inputmode="numeric"', $field);
            self::assertStringContainsString('maxlength="10"', $field);
        }
        self::assertStringContainsString("shared/date-field.js", $script);
        self::assertStringContainsString('toIsoDate', $script);
        self::assertStringContainsString('formatDisplayDate', $script);
    }
}
