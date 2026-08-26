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
use RuntimeException;
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
        })->andReturn('00001');
        $repository->shouldReceive('upsertAddress')->once()->withArgs(function (string $maNv, array $address): bool {
            $this->assertSame(1, DB::connection()->transactionLevel());
            $this->assertSame('00001', $maNv);
            $this->assertSame([
                'dia_chi_cu_the' => '1 Nguyễn Trãi',
                'phuong_xa' => 'Bến Thành',
                'quan_huyen' => 'Quận 1',
                'tinh_thanh' => 'TP Hồ Chí Minh',
            ], $address);

            return true;
        });

        $service = $this->service($repository, $hasher);

        $this->assertSame('00001', $service->create($this->validPayload([
            'ma_vt' => 99,
            'mat_khau' => 'crafted',
            'ngay_nghi_viec' => '2026-08-12',
        ])));
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_address_failure_rolls_back_and_never_returns_a_partial_code(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('create')->once()->andReturn('00001');
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

    public function test_outer_rollback_compensates_a_successful_create_avatar(): void
    {
        Storage::fake('public');
        $avatarPath = null;
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('create')->once()->withArgs(
            function (array $profile, string $passwordHash, ?string $path) use (&$avatarPath): bool {
                $avatarPath = $path;

                return is_string($path);
            },
        )->andReturn('00001');
        $repository->shouldReceive('upsertAddress')->once();
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('make')->once()->andReturn('laravel-hash');
        $connection = DB::connection();
        $connection->beginTransaction();

        try {
            $this->assertSame('00001', $this->service($repository, $hasher)->create($this->validPayload([
                'anh_dai_dien' => $this->fakePng(),
            ])));
            $this->assertNotNull($avatarPath);
            Storage::disk('public')->assertExists($avatarPath);

            $connection->rollBack();

            Storage::disk('public')->assertMissing($avatarPath);
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }
    }

    public function test_move_failure_deletes_generated_owned_temporary_and_final_paths(): void
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
        $disk->shouldReceive('delete')->twice()->withArgs(function (string $path) use (&$temporaryPath): bool {
            $this->assertTrue(
                $path === $temporaryPath || preg_match('#\Anhan-vien/avatars/[0-9a-f-]{36}\.png\z#', $path) === 1,
            );

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

    public function test_unexpected_owned_put_path_cleans_both_returned_and_generated_temporary_files(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldNotReceive('create');
        $repository->shouldNotReceive('upsertAddress');
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('make')->once()->andReturn('laravel-hash');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $generatedPath = null;
        $returnedPath = 'nhan-vien/avatars/tmp/550e8400-e29b-41d4-a716-446655440000.png';
        $deleted = [];
        $disk->shouldReceive('putFileAs')->once()->andReturnUsing(function (
            string $directory,
            UploadedFile $file,
            string $name,
        ) use (&$generatedPath, $returnedPath): string {
            $generatedPath = $directory.'/'.$name;

            return $returnedPath;
        });
        $disk->shouldNotReceive('move');
        $disk->shouldReceive('delete')->times(3)->andReturnUsing(function (string $path) use (&$deleted): bool {
            $deleted[] = $path;

            return false;
        });
        $files = Mockery::mock(FilesystemManager::class);
        $files->shouldReceive('disk')->once()->with('public')->andReturn($disk);

        try {
            (new NhanVienService($this->app->make('db'), $repository, $files, $hasher))
                ->create($this->validPayload(['anh_dai_dien' => $this->fakePng()]));
            $this->fail('Unexpected put path must fail closed.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_AVATAR_WRITE_FAILED', $exception->domainCode);
        }

        $this->assertContains($generatedPath, $deleted);
        $this->assertContains($returnedPath, $deleted);
        $this->assertCount(3, $deleted);
        $this->assertSame(1, count(array_filter(
            $deleted,
            fn (string $path): bool => preg_match('#\Anhan-vien/avatars/[0-9a-f-]{36}\.png\z#', $path) === 1,
        )));
    }

    public function test_move_failure_attempts_cleanup_of_temporary_and_possible_partial_final_without_masking_error(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldNotReceive('create');
        $repository->shouldNotReceive('upsertAddress');
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('make')->once()->andReturn('laravel-hash');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $temporaryPath = null;
        $finalPath = null;
        $deleted = [];
        $disk->shouldReceive('putFileAs')->once()->andReturnUsing(function (
            string $directory,
            UploadedFile $file,
            string $name,
        ) use (&$temporaryPath): string {
            return $temporaryPath = $directory.'/'.$name;
        });
        $disk->shouldReceive('move')->once()->andReturnUsing(function (string $from, string $to) use (&$finalPath): bool {
            $finalPath = $to;

            return false;
        });
        $disk->shouldReceive('delete')->twice()->andReturnUsing(function (string $path) use (&$deleted): bool {
            $deleted[] = $path;

            return false;
        });
        $files = Mockery::mock(FilesystemManager::class);
        $files->shouldReceive('disk')->once()->with('public')->andReturn($disk);

        try {
            (new NhanVienService($this->app->make('db'), $repository, $files, $hasher))
                ->create($this->validPayload(['anh_dai_dien' => $this->fakePng()]));
            $this->fail('Move failure must abort employee creation.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_AVATAR_MOVE_FAILED', $exception->domainCode);
        }

        $this->assertEqualsCanonicalizing([$temporaryPath, $finalPath], $deleted);
    }

    public function test_cleanup_delete_throw_never_masks_the_original_database_exception(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $original = new NhanVienDomainException('Email đã được sử dụng.', 'NV_EMAIL_DUPLICATE', 'email');
        $repository->shouldReceive('create')->once()->andThrow($original);
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
            return $temporaryPath = $directory.'/'.$name;
        });
        $disk->shouldReceive('move')->once()->andReturnTrue();
        $disk->shouldReceive('delete')->once()->andThrow(new RuntimeException('storage unavailable'));
        $files = Mockery::mock(FilesystemManager::class);
        $files->shouldReceive('disk')->once()->with('public')->andReturn($disk);

        try {
            (new NhanVienService($this->app->make('db'), $repository, $files, $hasher))
                ->create($this->validPayload(['anh_dai_dien' => $this->fakePng()]));
            $this->fail('Database failure must be rethrown.');
        } catch (\Throwable $exception) {
            $this->assertSame($original, $exception);
        }
    }

    public function test_move_throw_still_attempts_all_cleanup_and_preserves_the_safe_move_error(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldNotReceive('create');
        $repository->shouldNotReceive('upsertAddress');
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('make')->once()->andReturn('laravel-hash');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $temporaryPath = null;
        $finalPath = null;
        $deleted = [];
        $disk->shouldReceive('putFileAs')->once()->andReturnUsing(function (
            string $directory,
            UploadedFile $file,
            string $name,
        ) use (&$temporaryPath): string {
            return $temporaryPath = $directory.'/'.$name;
        });
        $disk->shouldReceive('move')->once()->andReturnUsing(function (
            string $from,
            string $to,
        ) use (&$finalPath): never {
            $finalPath = $to;

            throw new RuntimeException('move unavailable');
        });
        $disk->shouldReceive('delete')->twice()->andReturnUsing(function (string $path) use (&$deleted): bool {
            $deleted[] = $path;
            if (count($deleted) === 1) {
                throw new RuntimeException('delete unavailable');
            }

            return false;
        });
        $files = Mockery::mock(FilesystemManager::class);
        $files->shouldReceive('disk')->once()->with('public')->andReturn($disk);

        try {
            (new NhanVienService($this->app->make('db'), $repository, $files, $hasher))
                ->create($this->validPayload(['anh_dai_dien' => $this->fakePng()]));
            $this->fail('Move exception must abort employee creation.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_AVATAR_MOVE_FAILED', $exception->domainCode);
        }

        $this->assertEqualsCanonicalizing([$temporaryPath, $finalPath], $deleted);
    }

    public function test_unowned_returned_put_path_is_never_deleted(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldNotReceive('create');
        $repository->shouldNotReceive('upsertAddress');
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('make')->once()->andReturn('laravel-hash');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $deleted = [];
        $disk->shouldReceive('putFileAs')->once()->andReturn('https://evil.example/avatar.png');
        $disk->shouldNotReceive('move');
        $disk->shouldReceive('delete')->twice()->andReturnUsing(function (string $path) use (&$deleted): bool {
            $deleted[] = $path;

            return true;
        });
        $files = Mockery::mock(FilesystemManager::class);
        $files->shouldReceive('disk')->once()->with('public')->andReturn($disk);

        try {
            (new NhanVienService($this->app->make('db'), $repository, $files, $hasher))
                ->create($this->validPayload(['anh_dai_dien' => $this->fakePng()]));
            $this->fail('Unowned put path must fail closed.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_AVATAR_WRITE_FAILED', $exception->domainCode);
        }

        $this->assertNotContains('https://evil.example/avatar.png', $deleted);
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
