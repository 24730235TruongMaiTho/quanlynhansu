<?php

namespace Tests\Unit\Services;

use App\Services\NghiPhepService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class NghiPhepSafeErrorTest extends TestCase
{
    public function test_detail_failure_hides_database_details(): void
    {
        $result = $this->runWithDatabaseFailure(
            static fn (): array => app(NghiPhepService::class)->getById(1),
        );

        $this->assertSafeFailure($result, 'Không thể tải chi tiết nghỉ phép.');
    }

    public function test_create_failure_hides_database_details(): void
    {
        $result = $this->runWithDatabaseFailure(
            static fn (): array => app(NghiPhepService::class)->create([
                'ma_nv' => '00001',
                'ma_lp' => 1,
                'tu_ngay' => '2026-09-01',
                'den_ngay' => '2026-09-01',
            ]),
        );

        $this->assertSafeFailure($result, 'Không thể tạo đơn nghỉ phép.');
    }

    public function test_update_failure_hides_database_details(): void
    {
        $result = $this->runWithDatabaseFailure(
            static fn (): array => app(NghiPhepService::class)->update(1, [
                'ma_nv' => '00001',
                'ma_lp' => 1,
                'tu_ngay' => '2026-09-01',
                'den_ngay' => '2026-09-01',
            ]),
        );

        $this->assertSafeFailure($result, 'Không thể cập nhật đơn nghỉ phép.');
    }

    public function test_delete_failure_hides_database_details(): void
    {
        $result = $this->runWithDatabaseFailure(
            static fn (): array => app(NghiPhepService::class)->delete(1),
        );

        $this->assertSafeFailure($result, 'Không thể xóa đơn nghỉ phép.');
    }

    /**
     * @param callable(): array $operation
     */
    private function runWithDatabaseFailure(callable $operation): array
    {
        DB::shouldReceive('table')
            ->once()
            ->andThrow(new RuntimeException('SQLSTATE[HY000] internal leave table details'));

        return $operation();
    }

    private function assertSafeFailure(array $result, string $message): void
    {
        self::assertFalse($result['success'] ?? null);
        self::assertSame($message, $result['message'] ?? null);
        self::assertStringNotContainsString('SQLSTATE', json_encode($result, JSON_UNESCAPED_UNICODE));
    }
}
