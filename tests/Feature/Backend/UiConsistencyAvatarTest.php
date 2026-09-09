<?php

namespace Tests\Feature\Backend;

use App\Models\NhanVien;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class UiConsistencyAvatarTest extends TestCase
{
    public function test_public_storage_avatar_urls_are_relative_by_default(): void
    {
        self::assertSame('/storage', config('filesystems.disks.public.url'));
        self::assertSame('/storage/nhan-vien/avatars/an.jpg', Storage::disk('public')->url('nhan-vien/avatars/an.jpg'));
    }

    public function test_authenticated_topbar_renders_avatar_url_and_initials_fallback(): void
    {
        $withAvatar = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 1,
            'ma_pb' => null,
            'ma_tt' => 1,
            'ten_vt' => 'Quản trị',
            'anh_dai_dien' => 'nhan-vien/avatars/an.jpg',
        ]);
        $this->actingAs($withAvatar);

        $rendered = Blade::render('@include(\'backend.layouts.topbar\')');

        self::assertStringContainsString('/storage/nhan-vien/avatars/an.jpg', $rendered);
        self::assertStringContainsString('class="avatar"', $rendered);
        self::assertStringContainsString('alt=""', $rendered);

        $withoutAvatar = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00002',
            'ho_ten' => 'Trần Bình',
            'email' => 'binh@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 1,
            'ma_pb' => null,
            'ma_tt' => 1,
            'ten_vt' => 'Nhân sự',
            'anh_dai_dien' => null,
        ]);
        $this->actingAs($withoutAvatar);

        $fallback = Blade::render('@include(\'backend.layouts.topbar\')');

        self::assertStringContainsString('>TR</div>', $fallback);
        self::assertStringNotContainsString('<img', $fallback);
    }
}
