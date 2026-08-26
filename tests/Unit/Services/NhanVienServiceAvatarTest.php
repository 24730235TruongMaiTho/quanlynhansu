<?php

namespace Tests\Unit\Services;

use App\Contracts\NhanVienRepositoryContract;
use App\Exceptions\NhanVienDomainException;
use App\Services\NhanVienService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class NhanVienServiceAvatarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('nhanvien.avatar_prefix', 'nhan-vien/avatars');
    }

    public function test_update_uses_one_default_connection_transaction_and_hydrates_before_commit(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('update')->once()->ordered()->withArgs(function (string $maNv, array $profile): bool {
            $this->assertSame('00001', $maNv);
            $this->assertSame(1, DB::connection()->transactionLevel());
            $this->assertSame($this->profile(), $profile);

            return true;
        });
        $repository->shouldReceive('upsertAddress')->once()->ordered()->withArgs(function (string $maNv, array $address): bool {
            $this->assertSame(1, DB::connection()->transactionLevel());
            $this->assertSame($this->address(), $address);

            return $maNv === '00001';
        });
        $repository->shouldNotReceive('replaceAvatarPath');
        $employee = (object) ['ma_nv' => '00001', 'ho_ten' => 'Nguyễn An'];
        $repository->shouldReceive('find')->once()->ordered()->with('00001')->andReturnUsing(function () use ($employee): object {
            $this->assertSame(1, DB::connection()->transactionLevel());

            return $employee;
        });
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldNotReceive('make');

        $result = $this->service($repository, $hasher)->update('00001', $this->payload([
            'ma_nv' => '99999',
            'ma_vt' => 999,
            'mat_khau' => 'crafted',
            'ngay_nghi_viec' => '2026-08-13',
        ]));

        $this->assertSame($employee, $result);
        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_hydration_failure_rolls_back_and_is_not_reported_after_commit(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('update')->once();
        $repository->shouldReceive('upsertAddress')->once();
        $repository->shouldNotReceive('replaceAvatarPath');
        $repository->shouldReceive('find')->once()->andThrow(new NhanVienDomainException(
            'Không thể tải lại nhân viên.',
            'NV_DATABASE_ERROR',
        ));
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldNotReceive('make');

        try {
            $this->service($repository, $hasher)->update('00001', $this->payload());
            $this->fail('Hydration failure must abort the transaction.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_DATABASE_ERROR', $exception->domainCode);
        }

        $this->assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_hydration_failure_after_avatar_replacement_compensates_new_file_and_keeps_old(): void
    {
        Storage::fake('public');
        $oldPath = 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png';
        Storage::disk('public')->put($oldPath, 'old');
        $newPath = null;
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('update')->once()->ordered();
        $repository->shouldReceive('upsertAddress')->once()->ordered();
        $repository->shouldReceive('replaceAvatarPath')->once()->ordered()->withArgs(
            function (string $maNv, ?string $path) use (&$newPath): bool {
                $newPath = $path;

                return $maNv === '00001' && is_string($path);
            }
        )->andReturn($oldPath);
        $repository->shouldReceive('find')->once()->ordered()->andThrow(new NhanVienDomainException(
            'Không thể tải lại nhân viên.',
            'NV_DATABASE_ERROR',
        ));
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldNotReceive('make');

        try {
            $this->service($repository, $hasher)->update('00001', $this->payload([
                'anh_dai_dien' => $this->fakePng(),
            ]));
            $this->fail('Hydration failure must abort and compensate the uploaded file.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_DATABASE_ERROR', $exception->domainCode);
        }

        $this->assertNotNull($newPath);
        Storage::disk('public')->assertExists($oldPath);
        Storage::disk('public')->assertMissing($newPath);
        $this->assertSame([$oldPath], Storage::disk('public')->allFiles('nhan-vien'));
    }

    public function test_database_failure_after_upload_deletes_new_owned_file_and_never_touches_old(): void
    {
        Storage::fake('public');
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('update')->once()->andThrow(new NhanVienDomainException(
            'Email đã được sử dụng.',
            'NV_EMAIL_DUPLICATE',
            'email',
        ));
        $repository->shouldNotReceive('upsertAddress');
        $repository->shouldNotReceive('replaceAvatarPath');
        $repository->shouldNotReceive('find');
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldNotReceive('make');
        Storage::disk('public')->put('nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png', 'old');

        try {
            $this->service($repository, $hasher)->update('00001', $this->payload([
                'anh_dai_dien' => $this->fakePng(),
            ]));
            $this->fail('Database failure must be rethrown.');
        } catch (NhanVienDomainException $exception) {
            $this->assertSame('NV_EMAIL_DUPLICATE', $exception->domainCode);
        }

        $this->assertSame(
            ['nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png'],
            Storage::disk('public')->allFiles('nhan-vien'),
        );
    }

    public function test_successful_replacement_keeps_new_file_and_deletes_different_owned_old_after_commit(): void
    {
        Storage::fake('public');
        $oldPath = 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png';
        Storage::disk('public')->put($oldPath, 'old');
        $newPath = null;
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('update')->once()->ordered();
        $repository->shouldReceive('upsertAddress')->once()->ordered();
        $repository->shouldReceive('replaceAvatarPath')->once()->ordered()->withArgs(
            function (string $maNv, ?string $path) use (&$newPath): bool {
                $this->assertSame(1, DB::connection()->transactionLevel());
                $this->assertMatchesRegularExpression('#\Anhan-vien/avatars/[0-9a-f-]{36}\.png\z#', (string) $path);
                $newPath = $path;

                return $maNv === '00001';
            }
        )->andReturn($oldPath);
        $employee = (object) ['ma_nv' => '00001'];
        $repository->shouldReceive('find')->once()->ordered()->andReturn($employee);
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldNotReceive('make');

        $this->assertSame($employee, $this->service($repository, $hasher)->update('00001', $this->payload([
            'anh_dai_dien' => $this->fakePng(),
        ])));

        $this->assertNotNull($newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_outer_rollback_keeps_old_avatar_and_compensates_the_new_upload(): void
    {
        Storage::fake('public');
        $oldPath = 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png';
        Storage::disk('public')->put($oldPath, 'old');
        $newPath = null;
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('update')->once()->ordered();
        $repository->shouldReceive('upsertAddress')->once()->ordered();
        $repository->shouldReceive('replaceAvatarPath')->once()->ordered()->withArgs(
            function (string $maNv, ?string $path) use (&$newPath): bool {
                $newPath = $path;

                return $maNv === '00001' && is_string($path);
            }
        )->andReturn($oldPath);
        $employee = (object) ['ma_nv' => '00001'];
        $repository->shouldReceive('find')->once()->ordered()->andReturn($employee);
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldNotReceive('make');
        $connection = DB::connection();
        $connection->beginTransaction();

        try {
            $this->assertSame($employee, $this->service($repository, $hasher)->update(
                '00001',
                $this->payload(['anh_dai_dien' => $this->fakePng()]),
            ));
            $this->assertSame(1, $connection->transactionLevel());
            $this->assertNotNull($newPath);
            Storage::disk('public')->assertExists($oldPath);
            Storage::disk('public')->assertExists($newPath);

            $connection->rollBack();

            Storage::disk('public')->assertExists($oldPath);
            Storage::disk('public')->assertMissing($newPath);
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }
    }

    public function test_outer_commit_deletes_old_avatar_only_after_the_root_commit(): void
    {
        Storage::fake('public');
        $oldPath = 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png';
        Storage::disk('public')->put($oldPath, 'old');
        $newPath = null;
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('update')->once()->ordered();
        $repository->shouldReceive('upsertAddress')->once()->ordered();
        $repository->shouldReceive('replaceAvatarPath')->once()->ordered()->withArgs(
            function (string $maNv, ?string $path) use (&$newPath): bool {
                $newPath = $path;

                return $maNv === '00001' && is_string($path);
            }
        )->andReturn($oldPath);
        $repository->shouldReceive('find')->once()->ordered()->andReturn((object) ['ma_nv' => '00001']);
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldNotReceive('make');
        $connection = DB::connection();
        $connection->beginTransaction();

        try {
            $this->service($repository, $hasher)->update('00001', $this->payload([
                'anh_dai_dien' => $this->fakePng(),
            ]));
            $this->assertNotNull($newPath);
            Storage::disk('public')->assertExists($oldPath);
            Storage::disk('public')->assertExists($newPath);

            $connection->commit();

            Storage::disk('public')->assertMissing($oldPath);
            Storage::disk('public')->assertExists($newPath);
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }
    }

    public function test_explicit_removal_writes_null_then_deletes_an_owned_old_path_after_commit(): void
    {
        Storage::fake('public');
        $oldPath = 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.webp';
        Storage::disk('public')->put($oldPath, 'old');
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('update')->once()->ordered();
        $repository->shouldReceive('upsertAddress')->once()->ordered();
        $repository->shouldReceive('replaceAvatarPath')->once()->ordered()->with('00001', null)->andReturn($oldPath);
        $repository->shouldReceive('find')->once()->ordered()->andReturn((object) ['ma_nv' => '00001']);
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldNotReceive('make');

        $this->service($repository, $hasher)->update('00001', $this->payload([
            'xoa_anh_dai_dien' => true,
        ]));

        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_unowned_old_paths_are_never_deleted_and_log_only_safe_context(): void
    {
        foreach ([
            'C:\\secret\\avatar.png',
            '../outside.png',
            'other-prefix/550e8400-e29b-41d4-a716-446655440000.png',
            'nhan-vien/avatars/not-a-uuid.png',
        ] as $oldPath) {
            Storage::fake('public');
            $repository = Mockery::mock(NhanVienRepositoryContract::class);
            $repository->shouldReceive('update')->once();
            $repository->shouldReceive('upsertAddress')->once();
            $repository->shouldReceive('replaceAvatarPath')->once()->with('00001', null)->andReturn($oldPath);
            $repository->shouldReceive('find')->once()->andReturn((object) ['ma_nv' => '00001']);
            $hasher = Mockery::mock(Hasher::class);
            $hasher->shouldNotReceive('make');
            Log::shouldReceive('warning')->once()->with(
                'employee_avatar_cleanup_skipped',
                ['ma_nv' => '00001', 'reason' => 'UNOWNED_PATH'],
            );

            $this->service($repository, $hasher)->update('00001', $this->payload([
                'xoa_anh_dai_dien' => true,
                'original_filename' => 'private-original.png',
                'mat_khau_hash' => 'secret-hash',
            ]));
        }
    }

    public function test_post_commit_delete_false_or_throw_is_best_effort_and_returns_the_employee(): void
    {
        foreach ([false, new RuntimeException('storage unavailable')] as $deleteResult) {
            $oldPath = 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.jpg';
            $repository = Mockery::mock(NhanVienRepositoryContract::class);
            $repository->shouldReceive('update')->once();
            $repository->shouldReceive('upsertAddress')->once();
            $repository->shouldReceive('replaceAvatarPath')->once()->andReturn($oldPath);
            $employee = (object) ['ma_nv' => '00001'];
            $repository->shouldReceive('find')->once()->andReturn($employee);
            $hasher = Mockery::mock(Hasher::class);
            $hasher->shouldNotReceive('make');
            $disk = Mockery::mock(FilesystemAdapter::class);
            if ($deleteResult instanceof RuntimeException) {
                $disk->shouldReceive('delete')->once()->with($oldPath)->andThrow($deleteResult);
            } else {
                $disk->shouldReceive('delete')->once()->with($oldPath)->andReturnFalse();
            }
            $files = Mockery::mock(FilesystemManager::class);
            $files->shouldReceive('disk')->once()->with('public')->andReturn($disk);
            Log::shouldReceive('warning')->once()->withArgs(function (string $event, array $context): bool {
                $this->assertSame('employee_avatar_cleanup_failed', $event);
                $this->assertSame('00001', $context['ma_nv'] ?? null);
                $this->assertContains($context['reason'] ?? null, ['DELETE_FALSE', RuntimeException::class]);
                $this->assertCount(2, $context);

                return true;
            });

            $result = (new NhanVienService(
                $this->app->make('db'),
                $repository,
                $files,
                $hasher,
            ))->update('00001', $this->payload(['xoa_anh_dai_dien' => true]));

            $this->assertSame($employee, $result);
        }
    }

    public function test_logger_failure_after_commit_never_replaces_the_successful_result(): void
    {
        $oldPath = 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.jpg';
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('update')->once();
        $repository->shouldReceive('upsertAddress')->once();
        $repository->shouldReceive('replaceAvatarPath')->once()->andReturn($oldPath);
        $employee = (object) ['ma_nv' => '00001'];
        $repository->shouldReceive('find')->once()->andReturn($employee);
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldNotReceive('make');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('delete')->once()->with($oldPath)->andReturnFalse();
        $files = Mockery::mock(FilesystemManager::class);
        $files->shouldReceive('disk')->once()->with('public')->andReturn($disk);
        Log::shouldReceive('warning')->once()->andThrow(new RuntimeException('logger unavailable'));

        $result = (new NhanVienService(
            $this->app->make('db'),
            $repository,
            $files,
            $hasher,
        ))->update('00001', $this->payload(['xoa_anh_dai_dien' => true]));

        $this->assertSame($employee, $result);
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

    private function payload(array $overrides = []): array
    {
        return array_replace($this->profile(), $this->address(), $overrides);
    }

    private function profile(): array
    {
        return [
            'ho_ten' => 'Nguyễn An',
            'ngay_sinh' => '1990-01-01',
            'gioi_tinh' => 1,
            'sdt' => '0901234567',
            'email' => 'an@example.test',
            'ngay_vao_lam' => '2020-01-01',
            'ma_pb' => 1,
            'ma_cv' => 1,
            'dan_toc' => 'Kinh',
            'cccd' => '001200000001',
            'noi_cap_cccd' => 'Cục CSQLHC',
            'hoc_van' => 'Đại học',
            'ma_tt' => 1,
        ];
    }

    private function address(): array
    {
        return [
            'dia_chi_cu_the' => '1 Nguyễn Trãi',
            'phuong_xa' => 'Bến Thành',
            'quan_huyen' => 'Quận 1',
            'tinh_thanh' => 'TP Hồ Chí Minh',
        ];
    }

    private function fakePng(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'private-original-name.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
        );
    }
}
