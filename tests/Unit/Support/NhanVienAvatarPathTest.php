<?php

namespace Tests\Unit\Support;

use App\Support\NhanVienAvatarPath;
use InvalidArgumentException;
use Tests\TestCase;

class NhanVienAvatarPathTest extends TestCase
{
    public function test_it_generates_owned_uuid_paths_for_each_allowed_extension(): void
    {
        config()->set('nhanvien.avatar_prefix', 'nhan-vien/avatars');
        $paths = new NhanVienAvatarPath;

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $final = $paths->newPath($extension);
            $temporary = $paths->newTemporaryPath(strtoupper($extension));

            $this->assertMatchesRegularExpression(
                '#\Anhan-vien/avatars/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.'.$extension.'\z#',
                $final,
            );
            $this->assertMatchesRegularExpression(
                '#\Anhan-vien/avatars/tmp/[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.'.$extension.'\z#',
                $temporary,
            );
            $this->assertSame($final, $paths->assertOwnedFile($final));
            $this->assertSame($temporary, $paths->assertOwnedTemporaryFile($temporary));
        }

        $this->assertNull($paths->assertOwnedFile(null));
        $this->assertNull($paths->assertOwnedTemporaryFile(null));
    }

    public function test_invalid_prefixes_fail_closed(): void
    {
        foreach ([
            '', '/', '/absolute', '\\absolute', 'C:/avatars', 'nhan-vien\\avatars',
            'nhan-vien//avatars', 'nhan-vien/./avatars', 'nhan-vien/../avatars',
            'nhan vien/avatars', 'nhan-vien/avatars.jpg',
        ] as $prefix) {
            config()->set('nhanvien.avatar_prefix', $prefix);

            try {
                new NhanVienAvatarPath;
                $this->fail("Prefix [{$prefix}] should be rejected.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_invalid_extensions_and_unowned_paths_fail_closed(): void
    {
        config()->set('nhanvien.avatar_prefix', 'nhan-vien/avatars');
        $paths = new NhanVienAvatarPath;
        $uuid = '123e4567-e89b-42d3-a456-426614174000';

        foreach (['gif', 'jpg.exe', '../jpg', '', '.jpg'] as $extension) {
            $this->assertInvalid(fn (): string => $paths->newPath($extension));
            $this->assertInvalid(fn (): string => $paths->newTemporaryPath($extension));
        }

        foreach ([
            '/nhan-vien/avatars/'.$uuid.'.jpg',
            'C:/nhan-vien/avatars/'.$uuid.'.jpg',
            'nhan-vien\\avatars\\'.$uuid.'.jpg',
            '../nhan-vien/avatars/'.$uuid.'.jpg',
            'other/avatars/'.$uuid.'.jpg',
            'nhan-vien/avatars/nested/'.$uuid.'.jpg',
            'nhan-vien/avatars/tmp/'.$uuid.'.jpg',
            'nhan-vien/avatars/not-a-uuid.jpg',
            'nhan-vien/avatars/'.$uuid.'.gif',
            'nhan-vien/avatars/'.$uuid.'.jpg.lnk',
            'nhan-vien/avatars/'.$uuid.'.jpg/child',
        ] as $path) {
            $this->assertInvalid(fn (): ?string => $paths->assertOwnedFile($path));
        }

        foreach ([
            'nhan-vien/avatars/'.$uuid.'.jpg',
            'nhan-vien/avatars/tmp/nested/'.$uuid.'.jpg',
            'nhan-vien/avatars/tmp/not-a-uuid.jpg',
            'nhan-vien/avatars/tmp/'.$uuid.'.webp.lnk',
            'other/tmp/'.$uuid.'.png',
        ] as $path) {
            $this->assertInvalid(fn (): ?string => $paths->assertOwnedTemporaryFile($path));
        }
    }

    private function assertInvalid(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Unowned avatar path should be rejected.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }
}
