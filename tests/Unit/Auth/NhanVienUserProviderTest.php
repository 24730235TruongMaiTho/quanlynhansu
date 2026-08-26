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
use Illuminate\Support\Facades\Hash;
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
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('00001')->andReturn($employee);
        $provider = new NhanVienUserProvider($repository, Mockery::mock(Hasher::class));

        $this->assertSame($employee, $provider->retrieveById('00001'));

        $terminated = $this->employee(['ma_tt' => 4]);
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('00001')->andReturn($terminated);
        $this->assertNull($provider->retrieveById('00001'));
    }

    public function test_unknown_restore_lookup_failure_fails_closed_as_missing_user(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('00001')->andThrow(new \RuntimeException('database down'));
        $provider = new NhanVienUserProvider($repository, Mockery::mock(Hasher::class));

        $this->assertNull($provider->retrieveById('00001'));
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

    public function test_all_terminal_statuses_are_rejected_during_restore_and_validation(): void
    {
        foreach ([4, 5, 6] as $status) {
            $employee = $this->employee(['ma_tt' => $status]);
            $repository = Mockery::mock(NhanVienRepositoryContract::class);
            $repository->shouldReceive('findAccountByIdentifier')->once()->with('00001')->andReturn($employee);
            $provider = new NhanVienUserProvider($repository, Mockery::mock(Hasher::class));

            $this->assertNull($provider->retrieveById('00001'));
            $this->assertFalse($provider->validateCredentials($employee, ['password' => 'secret']));
        }
    }

    public function test_unknown_status_is_rejected_during_restore_and_validation(): void
    {
        $employee = $this->employee(['ma_tt' => 99]);
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('00001')->andReturn($employee);
        $provider = new NhanVienUserProvider($repository, Mockery::mock(Hasher::class));

        $this->assertNull($provider->retrieveById('00001'));
        $this->assertFalse($provider->validateCredentials($employee, ['password' => 'secret']));
    }

    public function test_seed_sha256_password_matches_demo_password_123(): void
    {
        $employee = $this->employee([
            'mat_khau' => 'A665A45920422F9D417E4867EFDC4FB8A04A1F3FFF1FA07E998E86F7F7A27AE3',
        ]);
        $provider = new NhanVienUserProvider(
            Mockery::mock(NhanVienRepositoryContract::class),
            Hash::driver('bcrypt'),
        );

        $this->assertTrue($provider->validateCredentials($employee, ['password' => '123']));
        $this->assertFalse($provider->validateCredentials($employee, ['password' => 'nhom3@2026']));
    }

    public function test_legacy_sha256_password_is_checked_once_and_rehashed_to_bcrypt(): void
    {
        $legacyHash = strtoupper(hash('sha256', 'nhom3@2026'));
        $employee = $this->employee(['mat_khau' => $legacyHash]);
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('rehashAuthenticatedPassword')
            ->once()
            ->withArgs(function (string $maNv, string $currentHash, string $newHash) use ($legacyHash): bool {
                return $maNv === '00001'
                    && $currentHash === $legacyHash
                    && password_get_info($newHash)['algo'] !== 0
                    && $newHash !== $legacyHash;
            });
        $provider = new NhanVienUserProvider($repository, Hash::driver('bcrypt'));

        $this->assertTrue($provider->validateCredentials($employee, ['password' => 'nhom3@2026']));
        $provider->rehashPasswordIfRequired($employee, ['password' => 'nhom3@2026']);
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
                ->once()->with('00001', 'old-hash', 'new-hash');
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
                ->once()->with('00001', 'old-hash', 'forced-new-hash');
            $provider = new NhanVienUserProvider($repository, $hasher);

            $this->assertTrue($provider->validateCredentials($employee, ['password' => 'secret']));
            $provider->rehashPasswordIfRequired($employee, ['password' => 'secret'], true);
        }
    }

    public function test_stale_compare_and_swap_is_logged_without_sensitive_values_and_does_not_retry(): void
    {
        Log::shouldReceive('warning')->once()->with(
            'employee_auth_rehash_stale',
            ['event_code' => 'NV_AUTH_HASH_STALE', 'ma_nv' => '00001'],
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

        $this->assertNull($provider->retrieveByToken('00001', 'token'));
        $provider->updateRememberToken($this->employee(), 'token');
        $this->addToAssertionCount(1);
    }

    private function employee(array $overrides = []): NhanVien
    {
        return NhanVien::fromAuthRow((object) array_replace([
            'ma_nv' => '00001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'old-hash',
            'ma_vt' => 5,
            'ma_tt' => 1,
        ], $overrides));
    }
}
