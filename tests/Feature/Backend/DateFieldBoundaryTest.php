<?php

namespace Tests\Feature\Backend;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DateFieldBoundaryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv')->primary();
        });
        Schema::create('loai_hop_dong', static function (Blueprint $table): void {
            $table->unsignedInteger('ma_lhd')->primary();
        });
        Schema::create('loai_phep', static function (Blueprint $table): void {
            $table->unsignedInteger('ma_lp')->primary();
        });

        DB::table('nhan_vien')->insert(['ma_nv' => '00001']);
        DB::table('loai_hop_dong')->insert([
            ['ma_lhd' => 1],
            ['ma_lhd' => 2],
        ]);
        DB::table('loai_phep')->insert(['ma_lp' => 1]);

        Route::post('/_tests/date-contract/hopdong', static function (\App\Http\Requests\StoreHopDongRequest $request) {
            return response()->json($request->validated());
        });
        Route::put('/_tests/date-contract/hopdong', static function (\App\Http\Requests\UpdateHopDongRequest $request) {
            return response()->json($request->validated());
        });
        Route::post('/_tests/date-contract/nghi-phep', static function (\App\Http\Requests\StoreNghiPhepRequest $request) {
            return response()->json($request->validated());
        });
        Route::put('/_tests/date-contract/nghi-phep', static function (\App\Http\Requests\UpdateNghiPhepRequest $request) {
            return response()->json($request->validated());
        });
        Route::post('/_tests/date-contract/he-so-luong', static function (\App\Http\Requests\StoreLuongHeSoLuongRequest $request) {
            return response()->json($request->validated());
        });
        Route::put('/_tests/date-contract/he-so-luong', static function (\App\Http\Requests\UpdateLuongHeSoLuongRequest $request) {
            return response()->json($request->validated());
        });
        Route::put('/_tests/date-contract/cham-cong-batch', static function (\App\Http\Requests\BatchSaveChamCongRequest $request) {
            return response()->json($request->validated());
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('loai_phep');
        Schema::dropIfExists('loai_hop_dong');
        Schema::dropIfExists('nhan_vien');

        parent::tearDown();
    }

    public function test_contract_store_and_update_normalize_display_dates_to_iso(): void
    {
        foreach (['post', 'put'] as $method) {
            $this->{$method}('/_tests/date-contract/hopdong', $this->contractPayload())
                ->assertOk()
                ->assertJsonPath('ngay_ky', '2026-09-03')
                ->assertJsonPath('ngay_het_han', '2027-09-03');
        }
    }

    public function test_leave_store_and_update_normalize_display_dates_to_iso(): void
    {
        foreach (['post', 'put'] as $method) {
            $this->{$method}('/_tests/date-contract/nghi-phep', $this->leavePayload())
                ->assertOk()
                ->assertJsonPath('tu_ngay', '2026-09-03')
                ->assertJsonPath('den_ngay', '2026-09-05');
        }
    }

    public function test_contract_and_leave_reject_iso_impossible_and_ambiguous_dates_with_labels(): void
    {
        foreach ([
            ['/_tests/date-contract/hopdong', 'ngay_ky', 'Ngày ký', $this->contractPayload(['ngay_ky' => '2026-09-03'])],
            ['/_tests/date-contract/hopdong', 'ngay_ky', 'Ngày ký', $this->contractPayload(['ngay_ky' => '31/02/2026'])],
            ['/_tests/date-contract/hopdong', 'ngay_ky', 'Ngày ký', $this->contractPayload(['ngay_ky' => '3/9/26'])],
            ['/_tests/date-contract/nghi-phep', 'tu_ngay', 'Từ ngày', $this->leavePayload(['tu_ngay' => '2026-09-03'])],
            ['/_tests/date-contract/nghi-phep', 'tu_ngay', 'Từ ngày', $this->leavePayload(['tu_ngay' => '31/02/2026'])],
            ['/_tests/date-contract/nghi-phep', 'tu_ngay', 'Từ ngày', $this->leavePayload(['tu_ngay' => '3/9/26'])],
        ] as [$uri, $field, $label, $payload]) {
            $response = $this->post($uri, $payload, ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);

            $this->assertStringContainsString($label, (string) data_get($response->json('errors'), $field.'.0'));
        }
    }

    public function test_leave_update_keeps_partial_update_semantics_for_omitted_dates(): void
    {
        $this->putJson('/_tests/date-contract/nghi-phep', [
            'ma_nv' => '00001',
            'ma_lp' => 1,
            'ly_do' => 'Cập nhật lý do',
        ])->assertOk();
    }

    public function test_date_order_errors_use_visible_vietnamese_field_labels(): void
    {
        $response = $this->postJson('/_tests/date-contract/hopdong', $this->contractPayload([
            'ngay_ky' => '05/09/2026',
            'ngay_het_han' => '03/09/2026',
        ]))->assertUnprocessable();

        $this->assertStringContainsString('Ngày hết hạn', (string) data_get($response->json('errors'), 'ngay_het_han.0'));
        $this->assertStringContainsString('Ngày ký', (string) data_get($response->json('errors'), 'ngay_het_han.0'));

        $response = $this->postJson('/_tests/date-contract/nghi-phep', $this->leavePayload([
            'tu_ngay' => '05/09/2026',
            'den_ngay' => '03/09/2026',
        ]))->assertUnprocessable();

        $this->assertStringContainsString('Đến ngày', (string) data_get($response->json('errors'), 'den_ngay.0'));
    }

    public function test_leave_json_api_accepts_canonical_iso_dates_from_converted_clients(): void
    {
        foreach (['postJson', 'putJson'] as $method) {
            $this->{$method}('/_tests/date-contract/nghi-phep', $this->leavePayload([
                'tu_ngay' => '2026-09-03',
                'den_ngay' => '2026-09-05',
            ]))->assertOk();
        }
    }

    public function test_coefficient_json_requests_accept_only_canonical_iso_dates(): void
    {
        foreach (['postJson', 'putJson'] as $method) {
            $this->{$method}('/_tests/date-contract/he-so-luong', $this->coefficientPayload())
                ->assertOk()
                ->assertJsonPath('tu_ngay', '2026-09-03')
                ->assertJsonPath('den_ngay', '2027-09-03');
        }

        foreach (['03/09/2026', '31/02/2026', '3/9/26'] as $invalidDate) {
            $response = $this->postJson('/_tests/date-contract/he-so-luong', $this->coefficientPayload([
                'tu_ngay' => $invalidDate,
            ]))->assertUnprocessable()->assertJsonValidationErrors('tu_ngay');

            $this->assertStringContainsString(
                'Từ ngày',
                (string) data_get($response->json('errors'), 'tu_ngay.0'),
            );
        }
    }

    public function test_attendance_json_requests_accept_only_canonical_iso_dates(): void
    {
        $this->putJson('/_tests/date-contract/cham-cong-batch', $this->attendancePayload())
            ->assertOk()
            ->assertJsonPath('rows.0.ngay_lam', '2026-09-03');

        foreach (['03/09/2026', '31/02/2026', '3/9/26'] as $invalidDate) {
            $response = $this->putJson('/_tests/date-contract/cham-cong-batch', $this->attendancePayload([
                'rows' => [[
                    'ngay_lam' => $invalidDate,
                    'so_gio_lam' => 8,
                    'vao_muon' => 0,
                    've_som' => 0,
                ]],
            ]))->assertUnprocessable()->assertJsonValidationErrors('rows.0.ngay_lam');

            $this->assertStringContainsString(
                'Ngày làm',
                (string) ($response->json('errors')['rows.0.ngay_lam'][0] ?? ''),
            );
        }
    }

    /** @param array<string, mixed> $overrides */
    private function attendancePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'ma_nv' => '00001',
            'thang' => 9,
            'nam' => 2026,
            'rows' => [[
                'ngay_lam' => '2026-09-03',
                'so_gio_lam' => 8,
                'vao_muon' => 0,
                've_som' => 0,
            ]],
        ], $overrides);
    }

    private function contractPayload(array $overrides = []): array
    {
        return array_replace([
            'ma_nv' => '00001',
            'ma_lhd' => 2,
            'ngay_ky' => '03/09/2026',
            'ngay_het_han' => '03/09/2027',
            'luong_co_ban' => 10000000,
        ], $overrides);
    }

    private function leavePayload(array $overrides = []): array
    {
        return array_replace([
            'ma_nv' => '00001',
            'tu_ngay' => '03/09/2026',
            'den_ngay' => '05/09/2026',
            'ma_lp' => 1,
            'ly_do' => 'Nghỉ phép',
        ], $overrides);
    }

    private function coefficientPayload(array $overrides = []): array
    {
        return array_replace([
            'ma_nv' => '00001',
            'he_so_luong' => 1.25,
            'tu_ngay' => '2026-09-03',
            'den_ngay' => '2027-09-03',
        ], $overrides);
    }
}
