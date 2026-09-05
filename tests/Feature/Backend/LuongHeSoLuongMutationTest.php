<?php

namespace Tests\Feature\Backend;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LuongHeSoLuongMutationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
        });
        Schema::create('lich_su_he_so_luong', static function (Blueprint $table): void {
            $table->increments('ma_ls');
            $table->string('ma_nv', 5);
            $table->decimal('he_so_luong', 5, 2);
            $table->date('tu_ngay');
            $table->date('den_ngay');
        });

        DB::table('nhan_vien')->insert([['ma_nv' => '00001'], ['ma_nv' => '00002']]);

        Route::post('/_tests/luong-he-so/mutation', [\App\Http\Controllers\Backend\LuongHeSoLuongController::class, 'store']);
        Route::put('/_tests/luong-he-so/mutation/{ma_ls}', [\App\Http\Controllers\Backend\LuongHeSoLuongController::class, 'update']);
        Route::delete('/_tests/luong-he-so/mutation/{ma_ls}', [\App\Http\Controllers\Backend\LuongHeSoLuongController::class, 'destroy']);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('lich_su_he_so_luong');
        Schema::dropIfExists('nhan_vien');
        parent::tearDown();
    }

    public function test_create_rejects_invalid_ranges_and_overlapping_periods_atomically(): void
    {
        $payload = $this->payload();

        $this->postJson('/_tests/luong-he-so/mutation', array_replace($payload, [
            'he_so_luong' => '1000.00',
        ]))->assertUnprocessable()->assertJsonValidationErrors('he_so_luong');

        $this->postJson('/_tests/luong-he-so/mutation', $payload)->assertCreated();

        $this->postJson('/_tests/luong-he-so/mutation', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tu_ngay');

        self::assertSame(1, DB::table('lich_su_he_so_luong')->count());
    }

    public function test_update_and_delete_use_exact_locked_row_and_missing_is_safe(): void
    {
        $this->postJson('/_tests/luong-he-so/mutation', $this->payload())->assertCreated();

        $response = $this->putJson('/_tests/luong-he-so/mutation/1', array_replace($this->payload(), [
            'he_so_luong' => '999.99',
        ]))->assertOk();
        self::assertEquals(999.99, $response->json('data.he_so_luong'));

        $this->deleteJson('/_tests/luong-he-so/mutation/1')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->deleteJson('/_tests/luong-he-so/mutation/1')
            ->assertNotFound()
            ->assertJsonPath('message', 'Không tìm thấy bản ghi hệ số lương.');
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'ma_nv' => '00001',
            'he_so_luong' => '2.34',
            'tu_ngay' => '2026-01-01',
            'den_ngay' => '2026-12-31',
        ];
    }
}
