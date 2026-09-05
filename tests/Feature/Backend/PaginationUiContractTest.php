<?php

namespace Tests\Feature\Backend;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

final class PaginationUiContractTest extends TestCase
{
    public function test_server_pagination_renders_the_shared_accessible_markup(): void
    {
        $paginator = new LengthAwarePaginator(
            range(11, 20),
            30,
            10,
            2,
            ['path' => '/nhan-vien'],
        );

        $rendered = (string) $this->view('backend.partials.pagination', [
            'paginator' => $paginator,
            'label' => 'nhân viên',
        ]);

        self::assertStringContainsString('<nav class="backend-pagination"', $rendered);
        self::assertStringContainsString('<ul class="pagination pagination-sm mb-0 flex-wrap justify-content-center">', $rendered);
        self::assertStringContainsString('aria-current="page">2</span>', $rendered);
        self::assertStringContainsString('aria-label="Trang trước"', $rendered);
        self::assertStringContainsString('aria-label="Trang sau"', $rendered);
        self::assertStringContainsString('bi-chevron-left', $rendered);
        self::assertStringContainsString('bi-chevron-right', $rendered);
    }

    public function test_dynamic_pagination_mounts_use_the_shared_backend_class(): void
    {
        $mounts = [
            'resources/views/backend/chamcong/index.blade.php' => ['employee-pagination', 'pagination'],
            'resources/views/backend/nghiphep/index.blade.php' => ['employee-pagination', 'pagination'],
            'resources/views/backend/nghiphep/duyet-nghi-phep.blade.php' => ['leave-pagination'],
            'resources/views/backend/luong/index.blade.php' => ['pagination', 'coefficient-pagination'],
            'resources/views/backend/vaitro/index.blade.php' => ['role-pagination'],
        ];

        foreach ($mounts as $path => $ids) {
            $source = file_get_contents(base_path($path));

            self::assertIsString($source, $path);
            foreach (array_unique($ids) as $id) {
                self::assertMatchesRegularExpression(
                    '/<nav[^>]*class="[^"]*\bbackend-pagination\b[^"]*"[^>]*id="' . preg_quote($id, '/') . '"|<nav[^>]*id="' . preg_quote($id, '/') . '"[^>]*class="[^"]*\bbackend-pagination\b[^"]*"/s',
                    $source,
                    $path . ' #' . $id . ' must mount the shared pagination contract',
                );
            }
        }
    }

    public function test_server_paginated_lists_delegate_to_the_shared_partial(): void
    {
        $paths = [
            'resources/views/backend/nhanvien/index.blade.php',
            'resources/views/backend/phongban/index.blade.php',
            'resources/views/backend/chucvu/index.blade.php',
            'resources/views/backend/hopdong/index.blade.php',
            'resources/views/backend/taikhoan/index.blade.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(base_path($path));

            self::assertIsString($source, $path);
            self::assertStringContainsString(
                "backend.partials.pagination",
                $source,
                $path . ' must use the shared server pagination partial',
            );
        }
    }

    public function test_shared_pagination_css_matches_the_role_visual_contract(): void
    {
        $css = file_get_contents(public_path('backend/css/style.css'));

        self::assertIsString($css);
        self::assertMatchesRegularExpression(
            '/\.backend-pagination\s+\.pagination\s*\{[^}]*display:\s*flex;[^}]*gap:\s*\.5rem;/s',
            $css,
        );
        self::assertMatchesRegularExpression(
            '/\.backend-pagination\s+\.page-item\s+\.page-link\s*\{[^}]*display:\s*inline-flex;[^}]*align-items:\s*center;[^}]*justify-content:\s*center;[^}]*width:\s*44px;[^}]*height:\s*44px;[^}]*border-radius:\s*10px;/s',
            $css,
        );
        self::assertMatchesRegularExpression(
            '/\.backend-pagination\s+\.pagination\s+\.page-item\s*\+\s*\.page-item\s*\{[^}]*margin-left:\s*0;/s',
            $css,
        );
        self::assertStringContainsString('#e94560', $css);
        self::assertMatchesRegularExpression('/@media\s*\(max-width:\s*575\.98px\)[\s\S]*?\.backend-pagination\s+\.page-item\s+\.page-link\s*\{[^}]*width:\s*40px;[^}]*height:\s*40px;/s', $css);
    }
}
