<?php

namespace Tests\Feature\Auth;

use App\Contracts\NhanVienRepositoryContract;
use App\Exceptions\NhanVienDomainException;
use App\Models\NhanVien;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class EmployeeAuthenticationTest extends TestCase
{
    protected function tearDown(): void
    {
        Auth::forgetGuards();
        parent::tearDown();
    }

    public function test_login_form_has_expected_fields_and_no_remember_control(): void
    {
        $this->get('/dang-nhap')->assertOk()
            ->assertSee('name="dinh_danh"', false)
            ->assertSee('name="mat_khau"', false)
            ->assertSee('autocomplete="username"', false)
            ->assertSee('autocomplete="current-password"', false)
            ->assertDontSee('remember', false);
    }

    public function test_invalid_password_payload_never_flashes_or_renders_plaintext(): void
    {
        $response = $this->from('/dang-nhap')->post('/dang-nhap', [
            'dinh_danh' => ' NV001 ',
            'mat_khau' => ['secret'],
        ]);

        $response->assertRedirect('/dang-nhap');
        $oldInput = session('_old_input', []);
        $this->assertSame('NV001', $oldInput['dinh_danh'] ?? null);
        $this->assertArrayNotHasKey('mat_khau', $oldInput);
        $this->assertStringNotContainsString('secret', json_encode($oldInput, JSON_THROW_ON_ERROR));

        $this->get('/dang-nhap')->assertDontSee('secret', false);
    }

    public function test_login_by_code_regenerates_session_and_redirects_to_default_dashboard(): void
    {
        $this->bindRepository(return: $this->employee());
        $this->get('/dang-nhap');
        $oldSessionId = $this->app['session']->getId();

        $response = $this->post('/dang-nhap', [
            'dinh_danh' => ' NV001 ',
            'mat_khau' => 'secret',
        ]);

        $response->assertRedirect(route('backend.bangdieukhien.index'));
        $this->assertTrue(Auth::check());
        $this->assertNotSame($oldSessionId, $this->app['session']->getId());
        $this->assertSame('NV001', Auth::id());
    }

    public function test_login_by_email_normalizes_identifier_and_honors_intended_url(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('an@example.test')->andReturn($this->employee());
        $this->app->instance(NhanVienRepositoryContract::class, $repository);

        $this->get('/_test/employee-authenticated')->assertRedirect(route('login'));
        $response = $this->post('/dang-nhap', [
            'dinh_danh' => '  AN@EXAMPLE.TEST ',
            'mat_khau' => 'secret',
        ]);

        $response->assertRedirect('/_test/employee-authenticated');
    }

    public function test_invalid_identifier_password_terminated_and_database_errors_are_generic(): void
    {
        $cases = [
            'missing' => null,
            'wrong-password' => $this->employee(['mat_khau' => Hash::make('different')]),
            'terminated' => $this->employee(['ky_hieu' => 'DA_NGHI']),
            'database' => new NhanVienDomainException('internal sql text', 'NV_DATABASE_ERROR'),
        ];

        foreach ($cases as $case => $result) {
            RateLimiter::clear($this->throttleKey('NV001'));
            if ($result instanceof NhanVienDomainException) {
                $repository = Mockery::mock(NhanVienRepositoryContract::class);
                $repository->shouldReceive('findAccountByIdentifier')->once()->with('NV001')->andThrow($result);
            } else {
                $repository = Mockery::mock(NhanVienRepositoryContract::class);
                $repository->shouldReceive('findAccountByIdentifier')->andReturn($result);
            }
            $this->app->instance(NhanVienRepositoryContract::class, $repository);
            Auth::forgetGuards();

            $response = $this->from('/dang-nhap')->post('/dang-nhap', [
                'dinh_danh' => 'NV001',
                'mat_khau' => $case === 'wrong-password' ? 'secret' : 'wrong',
            ]);

            $response->assertRedirect('/dang-nhap')
                ->assertSessionHasErrors(['dinh_danh' => 'Thông tin đăng nhập không hợp lệ.'])
                ->assertDontSee('internal sql text');
            $this->assertFalse(Auth::check());
        }
    }

    public function test_login_is_limited_to_five_attempts_per_identifier_and_ip(): void
    {
        $this->bindRepository(return: null);
        $key = $this->throttleKey('NV001');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/dang-nhap', ['dinh_danh' => 'NV001', 'mat_khau' => 'bad'])
                ->assertRedirect('/dang-nhap')
                ->assertSessionHasErrors('dinh_danh');
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
        $this->post('/dang-nhap', ['dinh_danh' => 'NV001', 'mat_khau' => 'bad'])
            ->assertRedirect('/dang-nhap')
            ->assertSessionHasErrors(['dinh_danh' => 'Thông tin đăng nhập không hợp lệ.']);
    }

    public function test_success_clears_rate_limit_and_logout_invalidates_session_and_token(): void
    {
        $this->bindRepository(return: $this->employee());
        $key = $this->throttleKey('NV001');
        RateLimiter::hit($key, 60);
        $this->post('/dang-nhap', ['dinh_danh' => 'NV001', 'mat_khau' => 'secret'])->assertRedirect();
        $this->assertFalse(RateLimiter::tooManyAttempts($key, 5));
        $oldSessionId = $this->app['session']->getId();
        $oldToken = csrf_token();

        $this->post('/dang-xuat')->assertRedirect(route('login'));

        $this->assertFalse(Auth::check());
        $this->assertNotSame($oldSessionId, $this->app['session']->getId());
        $this->assertNotSame($oldToken, csrf_token());
    }

    public function test_admin_rehashes_with_cas_and_stale_cas_still_allows_valid_login(): void
    {
        $employee = $this->employee(['ma_vt' => 9, 'mat_khau' => 'old-hash']);
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('NV001')->andReturn($employee);
        $repository->shouldReceive('rehashAuthenticatedPassword')->once()->with('NV001', 'old-hash', 'new-hash')->andThrow(
            new NhanVienDomainException('Thông tin đăng nhập không hợp lệ.', 'NV_AUTH_HASH_STALE'),
        );
        $this->app->instance(NhanVienRepositoryContract::class, $repository);
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('check')->once()->with('secret', 'old-hash')->andReturnTrue();
        $hasher->shouldReceive('needsRehash')->once()->with('old-hash')->andReturnTrue();
        $hasher->shouldReceive('make')->once()->with('secret')->andReturn('new-hash');
        $this->app->instance(Hasher::class, $hasher);
        Log::shouldReceive('warning')->once()->with(
            'employee_auth_rehash_stale',
            ['event_code' => 'NV_AUTH_HASH_STALE', 'ma_nv' => 'NV001'],
        );

        $this->post('/dang-nhap', ['dinh_danh' => 'NV001', 'mat_khau' => 'secret'])
            ->assertRedirect(route('backend.bangdieukhien.index'));
        $this->assertTrue(Auth::check());
    }

    public function test_unknown_rehash_failure_is_generic_and_does_not_create_session(): void
    {
        $employee = $this->employee(['mat_khau' => 'old-hash']);
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('NV001')->andReturn($employee);
        $repository->shouldReceive('rehashAuthenticatedPassword')->once()->andThrow(
            new NhanVienDomainException('raw database details', 'NV_DATABASE_ERROR'),
        );
        $this->app->instance(NhanVienRepositoryContract::class, $repository);
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('check')->once()->with('secret', 'old-hash')->andReturnTrue();
        $hasher->shouldReceive('needsRehash')->once()->with('old-hash')->andReturnTrue();
        $hasher->shouldReceive('make')->once()->with('secret')->andReturn('new-hash');
        $this->app->instance(Hasher::class, $hasher);

        $this->from('/dang-nhap')->post('/dang-nhap', ['dinh_danh' => 'NV001', 'mat_khau' => 'secret'])
            ->assertRedirect('/dang-nhap')
            ->assertSessionHasErrors(['dinh_danh' => 'Thông tin đăng nhập không hợp lệ.'])
            ->assertDontSee('raw database details');
        $this->assertFalse(Auth::check());
    }

    public function test_session_restore_rejects_employee_that_becomes_terminated(): void
    {
        $active = $this->employee();
        $terminated = $this->employee(['ky_hieu' => 'DA_NGHI']);
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('findAccountByIdentifier')->with('NV001')->andReturn($active, $terminated);
        $this->app->instance(NhanVienRepositoryContract::class, $repository);

        $this->post('/dang-nhap', ['dinh_danh' => 'NV001', 'mat_khau' => 'secret'])->assertRedirect();
        Auth::forgetGuards();
        $this->get('/_test/employee-authenticated')->assertRedirect(route('login'));
    }

    public function test_login_does_not_use_remember_behavior(): void
    {
        $this->bindRepository(return: $this->employee());
        $response = $this->post('/dang-nhap', [
            'dinh_danh' => 'NV001',
            'mat_khau' => 'secret',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('backend.bangdieukhien.index'));
        $this->assertNull($response->getCookie('remember_web'));
    }

    private function bindRepository(?NhanVien $return): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('findAccountByIdentifier')->withAnyArgs()->andReturn($return);
        $this->app->instance(NhanVienRepositoryContract::class, $repository);
        Auth::forgetGuards();
    }

    private function employee(array $overrides = []): NhanVien
    {
        return NhanVien::fromAuthProcedureRow((object) array_replace([
            'ma_nv' => 'NV001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => Hash::make('secret'),
            'ma_vt' => 1,
            'ky_hieu' => 'DANG_LAM',
        ], $overrides));
    }

    private function throttleKey(string $identifier): string
    {
        return hash('sha256', strtolower($identifier).'|127.0.0.1');
    }
}
