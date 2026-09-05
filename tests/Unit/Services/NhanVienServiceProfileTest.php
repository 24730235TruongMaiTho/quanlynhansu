<?php

namespace Tests\Unit\Services;

use App\Contracts\NhanVienRepositoryContract;
use App\Exceptions\NhanVienDomainException;
use App\Models\NhanVien;
use App\Services\NhanVienService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

final class NhanVienServiceProfileTest extends TestCase
{
    public function test_own_profile_update_forwards_only_self_service_fields_and_address(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('update')->once()->with('00001', [
            'ho_ten' => 'Nguyễn An',
            'ngay_sinh' => '1990-01-02',
            'email' => 'an@example.test',
        ]);
        $repository->shouldReceive('upsertAddress')->once()->with('00001', [
            'dia_chi_cu_the' => '1 Nguyễn Trãi',
            'phuong_xa' => 'Bến Thành',
            'quan_huyen' => 'Quận 1',
            'tinh_thanh' => 'TP HCM',
        ]);
        $repository->shouldReceive('find')->twice()->with('00001')->andReturn((object) [
            'ma_nv' => '00001',
            'ngay_vao_lam' => '2009-01-02',
        ]);

        $this->service($repository)->updateOwnProfile('00001', [
            'ho_ten' => 'Nguyễn An',
            'ngay_sinh' => '1990-01-02',
            'email' => 'an@example.test',
            'ma_nv' => '99999',
            'ma_pb' => 99,
            'ma_vt' => 99,
            'mat_khau' => 'crafted',
            'dia_chi_cu_the' => '1 Nguyễn Trãi',
            'phuong_xa' => 'Bến Thành',
            'quan_huyen' => 'Quận 1',
            'tinh_thanh' => 'TP HCM',
        ]);

        self::assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_own_profile_update_accepts_birth_date_on_exact_eighteenth_birthday(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->twice()->with('00001')->andReturn((object) [
            'ma_nv' => '00001',
            'ngay_vao_lam' => '2026-09-03',
        ]);
        $repository->shouldReceive('update')->once()->with('00001', [
            'ngay_sinh' => '2008-09-03',
        ]);
        $repository->shouldReceive('upsertAddress')->once()->with('00001', []);

        $this->service($repository)->updateOwnProfile('00001', [
            'ngay_sinh' => '2008-09-03',
        ]);
    }

    public function test_own_profile_update_rejects_birth_date_one_day_before_eighteenth_birthday_without_mutation(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('00001')->andReturn((object) [
            'ma_nv' => '00001',
            'ngay_vao_lam' => '2026-09-03',
        ]);
        $repository->shouldNotReceive('update');
        $repository->shouldNotReceive('upsertAddress');

        try {
            $this->service($repository)->updateOwnProfile('00001', [
                'ngay_sinh' => '2008-09-04',
            ]);
            self::fail('An under-age birth date must be rejected before mutation.');
        } catch (NhanVienDomainException $exception) {
            self::assertSame('Nhân viên phải đủ 18 tuổi tại ngày vào làm.', $exception->getMessage());
            self::assertSame('NV_PROFILE_BIRTH_DATE_TOO_YOUNG', $exception->domainCode);
            self::assertSame('ngay_sinh', $exception->field);
        }
    }

    public function test_own_profile_update_rejects_non_iso_birth_date_before_mutation(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->never();
        $repository->shouldNotReceive('update');
        $repository->shouldNotReceive('upsertAddress');

        try {
            $this->service($repository)->updateOwnProfile('00001', [
                'ngay_sinh' => '03/09/2008',
            ]);
            self::fail('A non-ISO birth date must be rejected before mutation.');
        } catch (NhanVienDomainException $exception) {
            self::assertSame('Ngày sinh không hợp lệ.', $exception->getMessage());
            self::assertSame('NV_PROFILE_BIRTH_DATE_INVALID', $exception->domainCode);
            self::assertSame('ngay_sinh', $exception->field);
        }
    }

    public function test_password_change_accepts_legacy_sha256_and_persists_new_laravel_hash(): void
    {
        $legacy = strtoupper(hash('sha256', 'legacy-secret'));
        $employee = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001', 'ho_ten' => 'Nguyễn An', 'email' => 'an@example.test',
            'mat_khau' => $legacy, 'ma_vt' => 5, 'ma_tt' => 1,
        ]);
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('00001')->andReturn($employee);
        $repository->shouldReceive('rehashAuthenticatedPassword')->once()->withArgs(
            fn (string $maNv, string $current, string $new): bool => $maNv === '00001'
                && $current === $legacy
                && password_get_info($new)['algo'] !== 0,
        );

        $this->service($repository, Hash::driver('bcrypt'))->changeOwnPassword(
            '00001',
            'legacy-secret',
            'new-secret-123',
        );
    }

    public function test_password_change_rejects_wrong_current_password_without_repository_mutation(): void
    {
        $employee = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001', 'ho_ten' => 'Nguyễn An', 'email' => 'an@example.test',
            'mat_khau' => Hash::make('correct'), 'ma_vt' => 5, 'ma_tt' => 1,
        ]);
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('00001')->andReturn($employee);
        $repository->shouldNotReceive('rehashAuthenticatedPassword');

        $this->expectException(NhanVienDomainException::class);
        $this->expectExceptionMessage('Mật khẩu hiện tại không đúng.');
        $this->service($repository)->changeOwnPassword('00001', 'wrong', 'new-secret-123');
    }

    public function test_password_change_rejects_reusing_current_password(): void
    {
        $employee = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001', 'ho_ten' => 'Nguyễn An', 'email' => 'an@example.test',
            'mat_khau' => Hash::make('correct'), 'ma_vt' => 5, 'ma_tt' => 1,
        ]);
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('findAccountByIdentifier')->once()->with('00001')->andReturn($employee);
        $repository->shouldNotReceive('rehashAuthenticatedPassword');

        $this->expectException(NhanVienDomainException::class);
        $this->expectExceptionMessage('Mật khẩu mới phải khác mật khẩu hiện tại.');
        $this->service($repository)->changeOwnPassword('00001', 'correct', 'correct');
    }

    private function service(NhanVienRepositoryContract $repository, ?Hasher $hasher = null): NhanVienService
    {
        return new NhanVienService(
            app('db'),
            $repository,
            app(FilesystemManager::class),
            $hasher ?? Hash::driver('bcrypt'),
        );
    }
}
