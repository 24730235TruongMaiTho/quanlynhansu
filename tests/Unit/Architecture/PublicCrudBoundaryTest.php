<?php

namespace Tests\Unit\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PublicCrudBoundaryTest extends TestCase
{
    public function test_runtime_boundary_has_no_authentication_or_permission_dependencies(): void
    {
        $forbidden = [
            'AuthenticatedSessionController',
            'NhanVienUserProvider',
            'PermissionService',
            'PermissionRegistry',
            "middleware('auth')",
            "'can:",
            '@can',
            'Gate::',
        ];

        $runtimeFiles = array_merge(
            File::allFiles(base_path('app')),
            File::allFiles(base_path('routes')),
            File::allFiles(base_path('resources/views')),
        );

        foreach ($runtimeFiles as $file) {
            $contents = File::get($file->getPathname());

            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $contents,
                    "Runtime còn tham chiếu {$needle}: {$file->getRelativePathname()}.",
                );
            }
        }
    }
}
