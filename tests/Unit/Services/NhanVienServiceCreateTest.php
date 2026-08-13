<?php

namespace Tests\Unit\Services;

use App\Contracts\NhanVienRepositoryContract;
use App\Exceptions\NhanVienDomainException;
use App\Services\NhanVienService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class NhanVienServiceCreateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.timezone', 'Asia/Ho_Chi_Minh');
        config()->set('nhanvien.avatar_prefix', 'nhan-vien/avatars');
        Carbon::setTestNow(Carbon::parse('2026-08-12 10:00:00', 'Asia/Ho_Chi_Minh'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_create_hashes_the_local_demo_password_and_uses_one_default_connection_transaction(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('make')->once()->with('nhom3@2026')->andReturn('laravel-hash');
        $repository->shouldReceive('create')->once()->withArgs(function (
            array $profile,
            string $passwordHash,
            ?string $avatarPath,
        ): bool {
            $this->assertSame(1, DB::connection()->transactionLevel());
            $this->assertSame('laravel-hash', $passwordHash);
            $this->assertNull($avatarPath);
            $this->assertSame([
                'ho_ten', 'ngay_sinh', 'gioi_tinh', 'sdt', 'email', 'ngay_vao_lam',
                'ma_pb', 'ma_cv', 'dan_toc', 'cccd', 'noi_cap_cccd', 'hoc_van', 'ma_tt',
            ], array_keys($profile));
            $this->assertArrayNotHasKey('ma_vt', $profile);
            $this->assertArrayNotHasKey('mat_khau', $profile);

            return true;
        })->andReturn('NV001');
        $repository->shouldReceive('upsertAddress')->once()->withArgs(function (string $maNv, array $address): bool {
            $this->assertSame(1, DB::connection()->transactionLevel());
            $this->assertSame('NV001', $maNv);
            $this->assertSame([
                'dia_chi_cu_the' => '1 Nguyễn Trãi',
                'phuong_xa' => 'Bến Thành',
                'quan_huyen' => 'Quận 1',
                'tinh_thanh' => 'TP Hồ Chí Minh',
            ], $address);

            return true;
        });

        $service = $this->service($repository, $hasher);

        $this->assertSame('NV001', $service->create($this->validPayload([
            'ma_vt' => 99,
            'mat_khau' => 'crafted',
            'ngay_nghi_viec' => '2026-08-12',
        ])));
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_address_failure_rolls_back_and_never_returns_a_partial_code(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('create')->once()->andReturn('NV001');
        $repository->shouldReceive('upsertAddress')->once()->andThrow(new NhanVienDomainException(
            'Dữ liệu tham chiếu không hợp lệ.',
            'NV_REFERENCE_INVALID',
        ));
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('make')->once()->andReturn('laravel-hash');

        try {
            $this->service($repository, $hasher)->create($this->validPayload());
            $this->fail('Address failure should abort employee creation.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_REFERENCE_INVALID', $exception->domainCode);
        }

        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_avatar_moves_from_temporary_to_final_and_is_removed_when_database_fails(): void
    {
        Storage::fake('public');
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('create')->once()->withArgs(function (
            array $profile,
            string $hash,
            ?string $avatarPath,
        ): bool {
            $this->assertMatchesRegularExpression(
                '#\Anhan-vien/avatars/[0-9a-f-]{36}\.png\z#',
                (string) $avatarPath,
            );

            return true;
        })->andThrow(new NhanVienDomainException(
            'Email đã được sử dụng.',
            'NV_EMAIL_DUPLICATE',
            'email',
        ));
        $repository->shouldNotReceive('upsertAddress');
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('make')->once()->andReturn('laravel-hash');

        try {
            $this->service($repository, $hasher)->create($this->validPayload([
                'anh_dai_dien' => $this->fakePng(),
            ]));
            $this->fail('Database failure should be rethrown.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_EMAIL_DUPLICATE', $exception->domainCode);
        }

        $this->assertSame([], Storage::disk('public')->allFiles('nhan-vien'));
    }

    public function test_move_failure_deletes_only_the_generated_owned_temporary_path(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldNotReceive('create');
        $repository->shouldNotReceive('upsertAddress');
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('make')->once()->andReturn('laravel-hash');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $temporaryPath = null;
        $disk->shouldReceive('putFileAs')->once()->andReturnUsing(function (
            string $directory,
            UploadedFile $file,
            string $name,
        ) use (&$temporaryPath): string {
            $temporaryPath = $directory.'/'.$name;

            return $temporaryPath;
        });
        $disk->shouldReceive('move')->once()->withArgs(function (string $from, string $to) use (&$temporaryPath): bool {
            $this->assertSame($temporaryPath, $from);
            $this->assertMatchesRegularExpression('#\Anhan-vien/avatars/[0-9a-f-]{36}\.png\z#', $to);

            return true;
        })->andReturnFalse();
        $disk->shouldReceive('delete')->once()->withArgs(function (string $path) use (&$temporaryPath): bool {
            $this->assertSame($temporaryPath, $path);
            $this->assertStringContainsString('/tmp/', $path);

            return true;
        })->andReturnTrue();
        $files = Mockery::mock(FilesystemManager::class);
        $files->shouldReceive('disk')->once()->with('public')->andReturn($disk);

        $service = new NhanVienService(
            $this->app->make('db'),
            $repository,
            $files,
            $hasher,
        );

        $this->expectException(NhanVienDomainException::class);
        $this->expectExceptionMessage('Không thể lưu ảnh đại diện. Vui lòng thử lại.');
        $service->create($this->validPayload(['anh_dai_dien' => $this->fakePng()]));
    }

    private function service(MockInterface $repository, MockInterface $hasher): NhanVienService
    {
        return new NhanVienService(
            $this->app->make('db'),
            $repository,
            $this->app->make(FilesystemManager::class),
            $hasher,
        );
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'ho_ten' => 'Nguyễn An',
            'ngay_sinh' => '1990-01-01',
            'gioi_tinh' => 1,
            'sdt' => '0901234567',
            'email' => 'an@example.test',
            'ngay_vao_lam' => '2026-08-12',
            'ma_pb' => 1,
            'ma_cv' => 1,
            'dan_toc' => 'Kinh',
            'cccd' => '001200000001',
            'noi_cap_cccd' => 'Cục CSQLHC',
            'hoc_van' => 'Đại học',
            'ma_tt' => 1,
            'dia_chi_cu_the' => '1 Nguyễn Trãi',
            'phuong_xa' => 'Bến Thành',
            'quan_huyen' => 'Quận 1',
            'tinh_thanh' => 'TP Hồ Chí Minh',
        ], $overrides);
    }

    private function fakePng(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'avatar.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        );
    }
}
