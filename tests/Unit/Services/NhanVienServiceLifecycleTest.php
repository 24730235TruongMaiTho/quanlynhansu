<?php

namespace Tests\Unit\Services;

use App\Contracts\NhanVienRepositoryContract;
use App\Enums\NhanVienRemovalAction;
use App\Services\NhanVienService;
use Carbon\Carbon;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class NhanVienServiceLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('nhanvien.avatar_prefix', 'nhan-vien/avatars');
        config()->set('app.timezone', 'America/Los_Angeles');
        Carbon::setTestNow(Carbon::parse('2026-08-20 00:30:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_lifecycle_date_uses_app_timezone_at_day_boundary(): void
    {
        Storage::fake('public');
        $oldPath = 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png';
        Storage::disk('public')->put($oldPath, 'old');
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('removeOrTerminate')->once()->withArgs(function (string $maNv, $date): bool {
            $this->assertSame('NV001', $maNv);
            $this->assertSame(2, DB::connection()->transactionLevel());
            $this->assertSame('2026-08-19', $date->toDateString());

            return true;
        })->andReturn([
            'action' => NhanVienRemovalAction::Deleted,
            'avatar_path' => $oldPath,
        ]);
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldNotReceive('make');
        $connection = DB::connection();
        $connection->beginTransaction();

        try {
            $result = $this->service($repository, $hasher)->removeOrTerminate('NV001');
            $this->assertSame(NhanVienRemovalAction::Deleted, $result);
            Storage::disk('public')->assertExists($oldPath);

            $connection->commit();

            Storage::disk('public')->assertMissing($oldPath);
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }
    }

    public function test_deleted_avatar_is_kept_when_an_outer_transaction_rolls_back(): void
    {
        Storage::fake('public');
        $oldPath = 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png';
        Storage::disk('public')->put($oldPath, 'old');
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('removeOrTerminate')->once()->andReturn([
            'action' => NhanVienRemovalAction::Deleted,
            'avatar_path' => $oldPath,
        ]);
        $hasher = Mockery::mock(Hasher::class);
        $connection = DB::connection();
        $connection->beginTransaction();

        try {
            $this->service($repository, $hasher)->removeOrTerminate('NV001');
            $connection->rollBack();

            Storage::disk('public')->assertExists($oldPath);
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }
    }

    public function test_terminated_employee_keeps_avatar(): void
    {
        Storage::fake('public');
        $oldPath = 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png';
        Storage::disk('public')->put($oldPath, 'old');
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('removeOrTerminate')->once()->andReturn([
            'action' => NhanVienRemovalAction::Terminated,
            'avatar_path' => $oldPath,
        ]);
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldNotReceive('make');

        $this->assertSame(
            NhanVienRemovalAction::Terminated,
            $this->service($repository, $hasher)->removeOrTerminate('NV001'),
        );
        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_invalid_old_avatar_path_is_not_deleted_or_logged_with_path(): void
    {
        foreach ([
            'C:\private\avatar.png',
            '../outside.png',
            'other-prefix/550e8400-e29b-41d4-a716-446655440000.png',
        ] as $oldPath) {
            Storage::fake('public');
            $repository = Mockery::mock(NhanVienRepositoryContract::class);
            $repository->shouldReceive('removeOrTerminate')->once()->andReturn([
                'action' => NhanVienRemovalAction::Deleted,
                'avatar_path' => $oldPath,
            ]);
            $hasher = Mockery::mock(Hasher::class);
            $hasher->shouldNotReceive('make');
            Log::shouldReceive('warning')->once()->with(
                'employee_avatar_cleanup_skipped',
                ['ma_nv' => 'NV001', 'reason' => 'UNOWNED_PATH'],
            );

            $this->service($repository, $hasher)->removeOrTerminate('NV001');
        }
    }

    public function test_delete_failure_and_logger_failure_do_not_change_deleted_result(): void
    {
        $oldPath = 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.jpg';
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('removeOrTerminate')->once()->andReturn([
            'action' => NhanVienRemovalAction::Deleted,
            'avatar_path' => $oldPath,
        ]);
        $hasher = Mockery::mock(Hasher::class);
        $disk = Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('delete')->once()->with($oldPath)->andReturnFalse();
        $files = Mockery::mock(FilesystemManager::class);
        $files->shouldReceive('disk')->once()->with('public')->andReturn($disk);
        Log::shouldReceive('warning')->once()->with(
            'employee_avatar_cleanup_failed',
            ['ma_nv' => 'NV001', 'reason' => 'DELETE_FALSE'],
        )->andThrow(new RuntimeException('logger unavailable'));

        $this->assertSame(
            NhanVienRemovalAction::Deleted,
            $this->service($repository, $hasher, $files)->removeOrTerminate('NV001'),
        );
    }

    public function test_reset_password_hashes_year_convention_and_never_returns_plaintext(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('resetPasswordHash')->once()->withArgs(function (string $maNv, string $hash): bool {
            return $maNv === 'NV001' && $hash === 'hashed-reset-password';
        });
        $hasher = Mockery::mock(Hasher::class);
        $hasher->shouldReceive('make')->once()->with('nhom3@2026')->andReturn('hashed-reset-password');

        $result = $this->service($repository, $hasher)->resetPassword('NV001');

        $this->assertNull($result);
    }

    private function service(
        MockInterface $repository,
        MockInterface $hasher,
        ?FilesystemManager $files = null,
    ): NhanVienService {
        return new NhanVienService(
            $this->app->make('db'),
            $repository,
            $files ?? $this->app->make(FilesystemManager::class),
            $hasher,
        );
    }
}
