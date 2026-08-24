<?php

namespace Tests\Unit\Auth;

use App\Auth\NhanVienUserProvider;
use App\Contracts\NhanVienRepositoryContract;
use App\Exceptions\NhanVienDomainException;
use App\Models\NhanVien;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class NhanVienUserProviderTest extends TestCase
{
    public function test_provider_implements_laravel_user_provider_and_is_resolved_by_web_guard(): void
    {
        $interfaces = class_implements(NhanVienUserProvider::class);

        $this->assertContains('Illuminate\\Contracts\\Auth\\UserProvider', $interfaces);
        $provider = Auth::guard('web')->getProvider();

        $this->assertInstanceOf(NhanVienUserProvider::class, $provider);
        $this->assertSame([], (new ReflectionMethod($provider, 'retrieveById'))->getParameters()[0]->getType() ? ['typed'] : []);
        $this->assertSame([], (new ReflectionMethod($provider, 'retrieveByToken'))->getParameters()[0]->getType() ? ['typed'] : []);
        $this->assertSame([], (new ReflectionMethod($provider, 'updateRememberToken'))->getParameters()[1]->getType() ? ['typed'] : []);
    }

    public function test_retrieve_by_id_uses_repository_and_rejects_terminated_employee(): void
    {
        $employee = $this->employee();
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('NV001')->andReturn($employee);
        $provider = new NhanVienUserProvider($repository, Mockery::mock(Hasher::class));

        $this->assertSame($employee, $provider->retrieveById('NV001'));

        $terminated = $this->employee(['ma_tt' => 4]);
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('NV001')->andReturn($terminated);
        $this->assertNull($provider->retrieveById('NV001'));
    }

    public function test_unknown_restore_lookup_failure_fails_closed_as_missing_user(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('NV001')->andThrow(new \RuntimeException('database down'));
        $provider = new NhanVienUserProvider($repository, Mockery::mock(Hasher::class));

        $this->assertNull($provider->retrieveById('NV001'));
    }

    public function test_credentials_lookup_ignores_password_and_rejects_terminated_employee(): void
    {
        $employee = $this->employee();
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('an@example.test')->andReturn($employee);
        $provider = new NhanVienUserProvider($repository, Mockery::mock(Hasher::class));

        $this->assertSame($employee, $provider->retrieveByCredentials([
            'dinh_danh' => 'an@example.test',
            'password' => 'a-secret-that-is-not-used-for-lookup',
        ]));

        $repository->shouldReceive('findAccountByIdentifier')->once()->with('an@example.test')->andReturn(
            $this->employee(['ma_tt' => 4]),
        );
        $this->assertNull($provider->retrieveByCredentials(['dinh_danh' => 'an@example.test']));
    }

    public function test_validate_credentials_uses_injected_hasher_and_records_success_for_rehash(): void
    {
        $employee = $this->employee();
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('check')->once()->with('secret', 'old-hash')->andReturnTrue();
        $provider = new NhanVienUserProvider($repository, $hasher);

        $this->assertTrue($provider->validateCredentials($employee, ['password' => 'secret']));

        $hasher->shouldReceive('check')->once()->with('wrong', 'old-hash')->andReturnFalse();
        $this->assertFalse($provider->validateCredentials($employee, ['password' => 'wrong']));

        $terminated = $this->employee(['ma_tt' => 4]);
        $this->assertFalse($provider->validateCredentials($terminated, ['password' => 'secret']));
    }

    public function test_rehashes_baseline_and_admin_accounts_with_compare_and_swap(): void
    {
        foreach ([1, 2] as $role) {
            $employee = $this->employee(['ma_vt' => $role]);
            $repository = Mockery::mock(NhanVienRepositoryContract::class);
            $hasher = Mockery::mock(Hasher::class);
            $hasher->shouldReceive('check')->once()->with('secret', 'old-hash')->andReturnTrue();
            $hasher->shouldReceive('needsRehash')->once()->with('old-hash')->andReturnTrue();
            $hasher->shouldReceive('make')->once()->with('secret')->andReturn('new-hash');
            $repository->shouldReceive('rehashAuthenticatedPassword')
                ->once()->with('NV001', 'old-hash', 'new-hash');
            $provider = new NhanVienUserProvider($repository, $hasher);

            $this->assertTrue($provider->validateCredentials($employee, ['password' => 'secret']));
            $provider->rehashPasswordIfRequired($employee, ['password' => 'secret']);
        }
    }

    public function test_forced_rehash_only_runs_after_successful_validation(): void
    {
        $employee = $this->employee();
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('check')->once()->with('bad', 'old-hash')->andReturnFalse();
        $provider = new NhanVienUserProvider($repository, $hasher);

        $this->assertFalse($provider->validateCredentials($employee, ['password' => 'bad']));
        $provider->rehashPasswordIfRequired($employee, ['password' => 'bad'], true);
    }

    public function test_forced_rehash_updates_baseline_and_admin_after_valid_validation(): void
    {
        foreach ([1, 2] as $role) {
            $employee = $this->employee(['ma_vt' => $role]);
            $repository = Mockery::mock(NhanVienRepositoryContract::class);
            $hasher = Mockery::mock(Hasher::class);
            $hasher->shouldReceive('check')->once()->with('secret', 'old-hash')->andReturnTrue();
            $hasher->shouldReceive('make')->once()->with('secret')->andReturn('forced-new-hash');
            $repository->shouldReceive('rehashAuthenticatedPassword')
                ->once()->with('NV001', 'old-hash', 'forced-new-hash');
            $provider = new NhanVienUserProvider($repository, $hasher);

            $this->assertTrue($provider->validateCredentials($employee, ['password' => 'secret']));
            $provider->rehashPasswordIfRequired($employee, ['password' => 'secret'], true);
        }
    }

    public function test_stale_compare_and_swap_is_logged_without_sensitive_values_and_does_not_retry(): void
    {
        Log::shouldReceive('warning')->once()->with(
            'employee_auth_rehash_stale',
            ['event_code' => 'NV_AUTH_HASH_STALE', 'ma_nv' => 'NV001'],
        );
        $employee = $this->employee();
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('rehashAuthenticatedPassword')->once()->andThrow(
            new NhanVienDomainException('Thông tin đăng nhập không hợp lệ.', 'NV_AUTH_HASH_STALE'),
        );
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('check')->once()->with('secret', 'old-hash')->andReturnTrue();
        $hasher->shouldReceive('needsRehash')->once()->with('old-hash')->andReturnTrue();
        $hasher->shouldReceive('make')->once()->with('secret')->andReturn('new-hash');
        $provider = new NhanVienUserProvider($repository, $hasher);

        $this->assertTrue($provider->validateCredentials($employee, ['password' => 'secret']));
        $provider->rehashPasswordIfRequired($employee, ['password' => 'secret']);
    }

    public function test_unknown_rehash_failure_propagates_and_tokens_are_never_supported(): void
    {
        $employee = $this->employee();
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('rehashAuthenticatedPassword')->once()->andThrow(
            new NhanVienDomainException('database failure', 'NV_DATABASE_ERROR'),
        );
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('check')->once()->with('secret', 'old-hash')->andReturnTrue();
        $hasher->shouldReceive('needsRehash')->once()->with('old-hash')->andReturnTrue();
        $hasher->shouldReceive('make')->once()->with('secret')->andReturn('new-hash');
        $provider = new NhanVienUserProvider($repository, $hasher);

        $this->assertTrue($provider->validateCredentials($employee, ['password' => 'secret']));
        $this->expectException(NhanVienDomainException::class);
        $provider->rehashPasswordIfRequired($employee, ['password' => 'secret']);
    }

    public function test_token_retrieval_is_null_and_remember_update_is_noop(): void
    {
        $provider = new NhanVienUserProvider(
            Mockery::mock(NhanVienRepositoryContract::class),
            Mockery::mock(Hasher::class),
        );

        $this->assertNull($provider->retrieveByToken('NV001', 'token'));
        $provider->updateRememberToken($this->employee(), 'token');
        $this->addToAssertionCount(1);
    }

    private function employee(array $overrides = []): NhanVien
    {
        return NhanVien::fromAuthRow((object) array_replace([
            'ma_nv' => 'NV001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'old-hash',
            'ma_vt' => 5,
            'ma_tt' => 2,
        ], $overrides));
    }
}
