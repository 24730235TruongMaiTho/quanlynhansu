<?php

namespace Tests\Unit\Services;

use App\Contracts\NhanVienRepositoryContract;
use App\Enums\NhanVienRole;
use App\Exceptions\NhanVienDomainException;
use App\Services\NhanVienService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Filesystem\FilesystemManager;
use Mockery;
use Tests\TestCase;

final class NhanVienResetPasswordServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_reset_hashes_only_the_current_year_default_and_forwards_no_plaintext(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2028-01-02 10:00:00', 'Asia/Ho_Chi_Minh'));
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('00999')->andReturn($this->employee('00999', 2, 10));
        $repository->shouldReceive('find')->once()->with('00001')->andReturn($this->employee('00001', 5, 11));
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('make')->once()->with('nhom3@2028')->andReturn('laravel-hash');
        $repository->shouldReceive('resetPassword')->once()->with('00001', 'laravel-hash');

        $result = $this->service($repository, $hasher)->resetPassword('00001', '00999');

        self::assertNull($result);
        self::assertNotSame('nhom3@2028', 'laravel-hash');
    }

    public function test_self_reset_is_rejected_before_lookup_or_hashing(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->never();
        $repository->shouldReceive('resetPassword')->never();
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('make')->never();

        $this->expectExceptionObject(new NhanVienDomainException(
            'Không tìm thấy nhân viên.',
            'NV_RESET_SELF_FORBIDDEN',
        ));

        $this->service($repository, $hasher)->resetPassword('00999', '00999');
    }

    public function test_terminated_target_is_rejected_without_mutation(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('00999')->andReturn($this->employee('00999', 2, 10));
        $repository->shouldReceive('find')->once()->with('00001')->andReturn($this->employee('00001', 5, 11, 4));
        $repository->shouldReceive('resetPassword')->never();
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('make')->never();

        $this->expectExceptionObject(new NhanVienDomainException(
            'Không tìm thấy nhân viên.',
            'NV_RESET_NOT_FOUND',
        ));

        $this->service($repository, $hasher)->resetPassword('00001', '00999');
    }

    public function test_non_super_admin_cannot_reset_super_admin_target(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('00999')->andReturn($this->employee('00999', 2, 10));
        $repository->shouldReceive('find')->once()->with('00001')->andReturn($this->employee('00001', NhanVienRole::SuperAdmin->value, 11));
        $repository->shouldReceive('resetPassword')->never();

        $this->expectExceptionObject(new NhanVienDomainException(
            'Không tìm thấy nhân viên.',
            'NV_RESET_PROTECTED_TARGET',
        ));

        $this->service($repository)->resetPassword('00001', '00999');
    }

    public function test_department_manager_cannot_reset_employee_outside_their_department(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('00999')->andReturn($this->employee('00999', NhanVienRole::DepartmentManager->value, 10));
        $repository->shouldReceive('find')->once()->with('00001')->andReturn($this->employee('00001', 5, 11));
        $repository->shouldReceive('resetPassword')->never();

        $this->expectExceptionObject(new NhanVienDomainException(
            'Không tìm thấy nhân viên.',
            'NV_RESET_SCOPE_FORBIDDEN',
        ));

        $this->service($repository)->resetPassword('00001', '00999');
    }

    private function service(NhanVienRepositoryContract $repository, ?Hasher $hasher = null): NhanVienService
    {
        return new NhanVienService(
            app('db'),
            $repository,
            app(FilesystemManager::class),
            $hasher ?? app(Hasher::class),
        );
    }

    private function employee(string $maNv, int $role, int $department, int $status = 1): object
    {
        return (object) [
            'ma_nv' => $maNv,
            'ma_vt' => $role,
            'ma_pb' => $department,
            'ma_tt' => $status,
        ];
    }
}
