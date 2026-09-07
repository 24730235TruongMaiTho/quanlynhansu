<?php

namespace Tests\Feature\Backend;

use App\Enums\HeSoLuongPermission;
use App\Enums\LuongPermission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

final class HeSoLuongAuthorizationTest extends TestCase
{
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
        });
        Schema::create('lich_su_he_so_luong', static function (Blueprint $table): void {
            $table->increments('ma_ls');
            $table->string('ma_nv', 5);
            $table->decimal('he_so_luong', 5, 2);
            $table->date('tu_ngay');
            $table->date('den_ngay');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('lich_su_he_so_luong');
        Schema::dropIfExists('nhan_vien');
        parent::tearDown();
    }

    public function test_salary_read_does_not_grant_coefficient_read(): void
    {
        $this->actingAsEmployeeWithPermissions([LuongPermission::Xem]);

        $this->getJson('/api/v1/luong/he-so-luong')
            ->assertForbidden();

        $this->actingAsEmployeeWithPermissions([HeSoLuongPermission::Xem]);

        $this->getJson('/api/v1/luong/he-so-luong')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_coefficient_insert_and_update_use_their_own_permissions(): void
    {
        $this->actingAsEmployeeWithPermissions([LuongPermission::Tao]);
        $this->postJson('/api/v1/luong/he-so-luong', [])->assertForbidden();

        $this->actingAsEmployeeWithPermissions([HeSoLuongPermission::Tao]);
        $this->postJson('/api/v1/luong/he-so-luong', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ma_nv', 'he_so_luong', 'tu_ngay', 'den_ngay']);

        $this->actingAsEmployeeWithPermissions([LuongPermission::Sua]);
        $this->putJson('/api/v1/luong/he-so-luong/1', [])->assertForbidden();

        $this->actingAsEmployeeWithPermissions([HeSoLuongPermission::Sua]);
        $this->putJson('/api/v1/luong/he-so-luong/1', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ma_nv', 'he_so_luong', 'tu_ngay', 'den_ngay']);
    }

    public function test_coefficient_delete_remains_separate_from_salary_delete(): void
    {
        $this->actingAsEmployeeWithPermissions([LuongPermission::Xoa]);
        $this->deleteJson('/api/v1/luong/he-so-luong/1')->assertForbidden();

        $this->actingAsEmployeeWithPermissions([HeSoLuongPermission::Xoa]);
        $this->deleteJson('/api/v1/luong/he-so-luong/1')
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }
}
