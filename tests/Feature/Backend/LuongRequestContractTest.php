<?php

namespace Tests\Feature\Backend;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LuongRequestContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
        });
        DB::table('nhan_vien')->insert(['ma_nv' => '00001']);

        Route::post('/_tests/luong/request', static fn (\App\Http\Requests\StoreLuongRequest $request) => response()->json($request->validated()));
        Route::patch('/_tests/luong/request', static fn (\App\Http\Requests\UpdateLuongRequest $request) => response()->json($request->validated()));
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('nhan_vien');
        parent::tearDown();
    }

    public function test_period_is_canonicalized_to_first_day_and_integer_money_is_accepted(): void
    {
        $response = $this->postJson('/_tests/luong/request', [
            'ma_nv' => '00001',
            'ky_luong' => '2026-09',
            'thuong' => '13000000',
            'phat' => 0,
        ])->assertOk();

        self::assertSame('2026-09-01', $response->json('ky_luong'));
        self::assertSame('13000000', (string) $response->json('thuong'));
    }

    public function test_money_rejects_decimal_negative_and_decimal18_overflow(): void
    {
        foreach (['13.5', '-1', '1000000000000000000'] as $value) {
            $this->postJson('/_tests/luong/request', [
                'ma_nv' => '00001',
                'ky_luong' => '2026-09-01',
                'thuong' => $value,
            ])->assertUnprocessable()->assertJsonValidationErrors('thuong');
        }
    }
}
