<?php

namespace Tests\Feature\Backend;

use App\Contracts\NhanVienServiceContract;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\NhanVien;
use App\Services\PermissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

final class ProfileFeatureTest extends TestCase
{
    public function test_profile_and_password_routes_are_auth_only_and_use_expected_methods(): void
    {
        $routes = [
            'backend.profile.edit' => ['GET'],
            'backend.profile.update' => ['PUT', 'PATCH'],
            'backend.profile.password.edit' => ['GET'],
            'backend.profile.password.update' => ['PUT', 'PATCH'],
        ];

        foreach ($routes as $name => $methods) {
            $route = Route::getRoutes()->getByName($name);
            self::assertInstanceOf(RoutingRoute::class, $route, $name.' must exist');
            self::assertSame([], array_diff($methods, $route->methods()), $name.' methods');
            self::assertContains('auth', $route->middleware(), $name.' must require auth');
            self::assertNotContains('can:', $route->middleware(), $name.' must not require HR permissions');
        }
    }

    public function test_topbar_uses_hydrated_identity_and_only_profile_password_logout_actions(): void
    {
        $source = file_get_contents(resource_path('views/backend/layouts/topbar.blade.php'));
        self::assertIsString($source);
        self::assertStringContainsString('ten_vt', $source);
        self::assertStringNotContainsString('Quản trị viên', $source);
        self::assertStringContainsString("route('backend.profile.edit')", $source);
        self::assertStringContainsString("route('backend.profile.password.edit')", $source);
        self::assertStringContainsString("route('logout')", $source);
        self::assertSame(3, substr_count($source, 'class="dropdown-item'));
        self::assertStringContainsString('method="POST"', $source);
    }

    public function test_topbar_renders_the_hydrated_role_name_instead_of_the_generic_fallback(): void
    {
        $employee = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'hidden',
            'ma_vt' => 42,
            'ma_tt' => 1,
            'ten_vt' => 'Quản trị hệ thống',
        ]);

        $this->actingAs($employee)
            ->view('backend.layouts.topbar')
            ->assertSee('Quản trị hệ thống', false)
            ->assertDontSee('Tài khoản', false);
    }

    public function test_profile_request_has_explicit_self_service_allowlist_and_date_contract(): void
    {
        $rules = (new UpdateProfileRequest)->rules();
        $allowed = [
            'ho_ten', 'ngay_sinh', 'gioi_tinh', 'sdt', 'email', 'dan_toc',
            'cccd', 'noi_cap_cccd', 'hoc_van', 'dia_chi_cu_the', 'phuong_xa',
            'quan_huyen', 'tinh_thanh', 'anh_dai_dien', 'xoa_anh_dai_dien',
        ];

        foreach ($allowed as $field) {
            self::assertArrayHasKey($field, $rules, $field.' must be editable');
        }

        $prohibited = [
            'ma_nv', 'ngay_vao_lam', 'ma_pb', 'ma_cv', 'ma_vt', 'ma_tt', 'ngay_nghi_viec',
            'mat_khau', 'mat_khau_hash', 'password', 'password_hash', 'current_password',
            'new_password', 'password_confirmation',
        ];

        foreach ($prohibited as $field) {
            self::assertArrayHasKey($field, $rules, $field.' must be rejected explicitly');
            self::assertContains('prohibited', $rules[$field]);
        }

        self::assertContains('date_format:Y-m-d', $rules['ngay_sinh']);

        $attributes = (new UpdateProfileRequest)->attributes();
        foreach ($prohibited as $field) {
            self::assertArrayHasKey($field, $attributes, $field.' must have a safe Vietnamese label');
            self::assertNotSame($field, $attributes[$field]);
            self::assertStringNotContainsString($field, $attributes[$field]);
        }
    }

    public function test_profile_get_renders_authenticated_employee_role_and_display_date(): void
    {
        $employee = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001', 'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test', 'mat_khau' => 'hidden',
            'ten_vt' => 'Nhân viên', 'ma_vt' => 5, 'ma_tt' => 1,
        ]);
        $employee->ngay_sinh = '2008-09-03';
        $service = Mockery::mock(NhanVienServiceContract::class);
        $service->shouldReceive('findOrFail')->once()->with('00001')->andReturn($employee);
        $this->app->instance(NhanVienServiceContract::class, $service);
        $permissions = Mockery::mock(PermissionService::class);
        $permissions->shouldReceive('canSeeModule')->andReturnFalse();
        $this->app->instance(PermissionService::class, $permissions);

        $this->actingAs($employee)
            ->get(route('backend.profile.edit'))
            ->assertOk()
            ->assertSee('Nhân viên', false)
            ->assertSee('03/09/2008', false)
            ->assertSee('Có thể nhập từng thành phần địa chỉ; các trường này không bắt buộc.')
            ->assertDontSee('Nhập đủ bốn thành phần hoặc để trống toàn bộ.')
            ->assertDontSee('2008-09-03', false);
    }

    public function test_profile_patch_uses_authenticated_actor_and_rejects_crafted_target_code(): void
    {
        $this->ensureProfileValidationTable();
        $employee = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001', 'ho_ten' => 'Nguyễn An', 'email' => 'an@example.test',
            'mat_khau' => 'hidden', 'ma_vt' => 5, 'ma_tt' => 1,
        ]);
        $service = Mockery::mock(NhanVienServiceContract::class);
        $service->shouldReceive('updateOwnProfile')->once()->withArgs(
            fn (string $maNv, array $profile): bool => $maNv === '00001'
                && ! array_key_exists('ma_nv', $profile)
                && $profile['ngay_sinh'] === '2008-09-03',
        );
        $this->app->instance(NhanVienServiceContract::class, $service);
        $this->actingAs($employee)
            ->patch(route('backend.profile.update'), $this->profilePayload())
            ->assertRedirect(route('backend.profile.edit'));

        $this->app->instance(NhanVienServiceContract::class, Mockery::mock(NhanVienServiceContract::class));
        $this->actingAs($employee)
            ->patch(route('backend.profile.update'), $this->profilePayload(['ma_nv' => '99999']))
            ->assertSessionHasErrors('ma_nv');
    }

    public function test_profile_patch_accepts_partial_address_and_normalizes_blank_part(): void
    {
        $this->ensureProfileValidationTable();
        $employee = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001', 'ho_ten' => 'Nguyễn An', 'email' => 'an@example.test',
            'mat_khau' => 'hidden', 'ma_vt' => 5, 'ma_tt' => 1,
        ]);
        $payload = $this->profilePayload([
            'dia_chi_cu_the' => '  1 Nguyễn Trãi  ',
            'phuong_xa' => '   ',
        ]);
        unset($payload['quan_huyen'], $payload['tinh_thanh']);

        $service = Mockery::mock(NhanVienServiceContract::class);
        $service->shouldReceive('updateOwnProfile')->once()->withArgs(
            fn (string $maNv, array $profile): bool => $maNv === '00001'
                && $profile['dia_chi_cu_the'] === '1 Nguyễn Trãi'
                && $profile['phuong_xa'] === null
                && ! array_key_exists('ma_pb', $profile),
        );
        $this->app->instance(NhanVienServiceContract::class, $service);

        $this->actingAs($employee)
            ->patch(route('backend.profile.update'), $payload)
            ->assertRedirect(route('backend.profile.edit'));
    }

    public function test_password_request_requires_current_and_confirmed_new_password(): void
    {
        $rules = (new ChangePasswordRequest)->rules();
        self::assertArrayHasKey('mat_khau_hien_tai', $rules);
        self::assertArrayHasKey('mat_khau_moi', $rules);
        self::assertContains('string', $rules['mat_khau_hien_tai']);
        self::assertContains('confirmed', $rules['mat_khau_moi']);
    }

    public function test_password_update_derives_employee_code_from_authenticated_actor_and_rotates_session(): void
    {
        $employee = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001', 'ho_ten' => 'Nguyễn An', 'email' => 'an@example.test',
            'mat_khau' => 'hidden', 'ma_vt' => 5, 'ma_tt' => 1,
        ]);
        $service = Mockery::mock(NhanVienServiceContract::class);
        $service->shouldReceive('changeOwnPassword')->once()->with('00001', 'current-secret', 'new-secret-123');
        $this->app->instance(NhanVienServiceContract::class, $service);

        $this->actingAs($employee);
        $oldSessionId = $this->app['session']->getId();
        $response = $this->patch(route('backend.profile.password.update'), [
            'mat_khau_hien_tai' => 'current-secret',
            'mat_khau_moi' => 'new-secret-123',
            'mat_khau_moi_confirmation' => 'new-secret-123',
        ]);

        $response->assertRedirect(route('backend.profile.password.edit'));
        self::assertNotSame($oldSessionId, $this->app['session']->getId());
        self::assertArrayNotHasKey('mat_khau_hien_tai', session('_old_input', []));
        self::assertArrayNotHasKey('mat_khau_moi', session('_old_input', []));
    }

    public function test_sidebar_contains_canonical_profile_navigation_and_salary_hash(): void
    {
        $source = file_get_contents(resource_path('views/backend/layouts/sidebar.blade.php'));
        self::assertIsString($source);
        self::assertMatchesRegularExpression('/data-toggle="submenu"[\s\S]{0,1200}Quản lý chức vụ/', $source);
        self::assertStringContainsString("route('backend.chucvu.index')", $source);
        self::assertStringContainsString("route('backend.chucvu.create')", $source);
        self::assertStringContainsString("route('backend.nghiphep.create')", $source);
        self::assertStringContainsString("route('backend.nghiphep.index')", $source);
        self::assertStringContainsString("NghiPhepPermission::Sua->value", $source);
        self::assertStringContainsString("#salary-coefficient-card", $source);
    }

    private function ensureProfileValidationTable(): void
    {
        Schema::dropIfExists('nhan_vien');
        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv')->primary();
            $table->string('email')->nullable();
            $table->string('cccd')->nullable();
        });
    }

    /** @return array<string, string> */
    private function profilePayload(array $overrides = []): array
    {
        return array_replace([
            'ho_ten' => 'Nguyễn An', 'ngay_sinh' => '03/09/2008', 'gioi_tinh' => 1,
            'sdt' => '0912345678', 'email' => 'an@example.test', 'dan_toc' => 'Kinh',
            'cccd' => '012345678901', 'noi_cap_cccd' => 'TP HCM', 'hoc_van' => 'Đại học',
            'dia_chi_cu_the' => '', 'phuong_xa' => '', 'quan_huyen' => '', 'tinh_thanh' => '',
        ], $overrides);
    }
}
