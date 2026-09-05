<?php

namespace Tests\Feature\Backend;

use App\Exceptions\HopDongDomainException;
use App\Contracts\HopDongRepositoryContract;
use App\Repositories\HopDongRepository;
use App\Services\HopDongService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class HopDongContractRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv')->primary();
        });
        Schema::create('loai_hop_dong', static function (Blueprint $table): void {
            $table->unsignedInteger('ma_lhd')->primary();
            $table->string('ten_lhd')->nullable();
        });
        Schema::create('hop_dong', static function (Blueprint $table): void {
            $table->increments('ma_hd');
            $table->string('ma_nv');
            $table->unsignedInteger('ma_lhd');
            $table->date('ngay_ky');
            $table->date('ngay_het_han')->nullable();
            $table->decimal('luong_co_ban', 18, 0);
        });

        DB::table('nhan_vien')->insert(['ma_nv' => '00001']);
        DB::table('loai_hop_dong')->insert([
            ['ma_lhd' => 1, 'ten_lhd' => 'Hợp đồng lao động không xác định thời hạn'],
            ['ma_lhd' => 2, 'ten_lhd' => 'Hợp đồng lao động xác định thời hạn'],
            ['ma_lhd' => 3, 'ten_lhd' => 'Hợp đồng lao động khoán'],
            ['ma_lhd' => 4, 'ten_lhd' => 'Hợp đồng thời vụ'],
            ['ma_lhd' => 5, 'ten_lhd' => 'Hợp đồng thử việc'],
        ]);

        Route::post('/_tests/hopdong-contract-rules', static function (\App\Http\Requests\StoreHopDongRequest $request) {
            return response()->json($request->validated());
        });
        Route::put('/_tests/hopdong-contract-rules', static function (\App\Http\Requests\UpdateHopDongRequest $request) {
            return response()->json($request->validated());
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('hop_dong');
        Schema::dropIfExists('loai_hop_dong');
        Schema::dropIfExists('nhan_vien');

        parent::tearDown();
    }

    public function test_request_requires_finite_expiry_strictly_after_signed_date(): void
    {
        foreach (['postJson', 'putJson'] as $method) {
            foreach ([null, '05/09/2026', '04/09/2026'] as $expiry) {
                $response = $this->{$method}('/_tests/hopdong-contract-rules', $this->requestPayload([
                    'ma_lhd' => 2,
                    'ngay_het_han' => $expiry,
                ]));

                $response->assertUnprocessable()->assertJsonValidationErrors('ngay_het_han');
            }

            $this->{$method}('/_tests/hopdong-contract-rules', $this->requestPayload([
                'ma_lhd' => 2,
                'ngay_het_han' => '06/09/2026',
            ]))->assertOk()->assertJsonPath('ngay_het_han', '2026-09-06');
        }
    }

    public function test_request_accepts_canonical_or_grouped_salary_and_rejects_malformed_values(): void
    {
        foreach (['postJson', 'putJson'] as $method) {
            foreach (['13000000', '13.000.000'] as $salary) {
                $this->{$method}('/_tests/hopdong-contract-rules', $this->requestPayload([
                    'luong_co_ban' => $salary,
                ]))->assertOk()->assertJsonPath('luong_co_ban', 13000000);
            }

            foreach (['13.00.000', '13,000,000', '-1', '1000000000000000000'] as $salary) {
                $this->{$method}('/_tests/hopdong-contract-rules', $this->requestPayload([
                    'luong_co_ban' => $salary,
                ]))->assertUnprocessable()->assertJsonValidationErrors('luong_co_ban');
            }
        }
    }

    public function test_request_forces_any_indefinite_expiry_to_null_on_create_and_update(): void
    {
        foreach (['postJson', 'putJson'] as $method) {
            foreach (['31/02/2026', 'crafted-invalid-expiry'] as $expiry) {
                $response = $this->{$method}('/_tests/hopdong-contract-rules', $this->requestPayload([
                    'ma_lhd' => 1,
                    'ngay_het_han' => $expiry,
                ]))->assertOk();

                $this->assertNull($response->json('ngay_het_han'));
            }
        }
    }

    public function test_service_forces_indefinite_expiry_to_null_and_persists_valid_finite_expiry(): void
    {
        $service = $this->service();

        $indefiniteId = $service->create($this->servicePayload([
            'ma_lhd' => 1,
            'ngay_het_han' => '2099-12-31',
        ]));
        $this->assertNull(DB::table('hop_dong')->where('ma_hd', $indefiniteId)->value('ngay_het_han'));

        $finiteId = $service->create($this->servicePayload([
            'ma_lhd' => 2,
            'ngay_het_han' => '2026-09-06',
        ]));
        $this->assertSame('2026-09-06', DB::table('hop_dong')->where('ma_hd', $finiteId)->value('ngay_het_han'));
    }

    public function test_service_rejects_unknown_type_and_invalid_finite_expiry_without_writing(): void
    {
        $service = $this->service();

        try {
            $service->create($this->servicePayload(['ma_lhd' => 99]));
            self::fail('Expected unknown type exception.');
        } catch (HopDongDomainException $exception) {
            $this->assertSame('HD_TYPE_NOT_FOUND', $exception->errorCode);
            $this->assertSame('ma_lhd', $exception->field);
        }

        foreach ([null, '2026-09-05', '2026-09-04'] as $expiry) {
            try {
                $service->create($this->servicePayload(['ma_lhd' => 2, 'ngay_het_han' => $expiry]));
                self::fail('Expected finite expiry exception.');
            } catch (HopDongDomainException $exception) {
                $this->assertSame('ngay_het_han', $exception->field);
            }
        }

        $this->assertSame(0, DB::table('hop_dong')->count());
    }

    public function test_service_update_forces_indefinite_expiry_null_and_rejects_invalid_salary(): void
    {
        $service = $this->service();
        $id = $service->create($this->servicePayload([
            'ma_lhd' => 2,
            'ngay_het_han' => '2026-09-06',
        ]));

        foreach ([null, '2026-09-05', '2026-09-04'] as $expiry) {
            try {
                $service->update($id, $this->servicePayload(['ma_lhd' => 2, 'ngay_het_han' => $expiry]));
                self::fail('Expected finite expiry exception.');
            } catch (HopDongDomainException $exception) {
                $this->assertSame('ngay_het_han', $exception->field);
            }
        }

        $service->update($id, $this->servicePayload(['ma_lhd' => 2, 'ngay_het_han' => '2026-09-07']));
        $this->assertSame('2026-09-07', DB::table('hop_dong')->where('ma_hd', $id)->value('ngay_het_han'));

        $service->update($id, $this->servicePayload([
            'ma_lhd' => 1,
            'ngay_het_han' => '2099-12-31',
        ]));
        $this->assertNull(DB::table('hop_dong')->where('ma_hd', $id)->value('ngay_het_han'));

        try {
            $service->update($id, $this->servicePayload(['luong_co_ban' => '13.00.000']));
            self::fail('Expected salary exception.');
        } catch (HopDongDomainException $exception) {
            $this->assertSame('HD_SALARY_INVALID', $exception->errorCode);
            $this->assertSame('luong_co_ban', $exception->field);
        }
    }

    public function test_service_keeps_update_not_found_and_hides_repository_failure_details(): void
    {
        $service = $this->service();

        try {
            $service->update(999, $this->servicePayload());
            self::fail('Expected not-found exception.');
        } catch (HopDongDomainException $exception) {
            $this->assertSame('HD_NOT_FOUND', $exception->errorCode);
        }

        $repository = \Mockery::mock(HopDongRepositoryContract::class);
        $repository->shouldReceive('findType')->once()->andThrow(new \RuntimeException('SQLSTATE[secret] internal detail'));

        try {
            (new HopDongService($repository, app('db')))->create($this->servicePayload());
            self::fail('Expected safe persistence exception.');
        } catch (HopDongDomainException $exception) {
            $this->assertSame('HD_PERSIST_FAILED', $exception->errorCode);
            $this->assertSame('Không thể lưu hợp đồng lúc này.', $exception->getMessage());
            $this->assertStringNotContainsString('SQLSTATE', $exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function requestPayload(array $overrides = []): array
    {
        return array_replace([
            'ma_nv' => '00001',
            'ma_lhd' => 1,
            'ngay_ky' => '05/09/2026',
            'ngay_het_han' => '06/09/2026',
            'luong_co_ban' => '13000000',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function servicePayload(array $overrides = []): array
    {
        return array_replace([
            'ma_nv' => '00001',
            'ma_lhd' => 1,
            'ngay_ky' => '2026-09-05',
            'ngay_het_han' => null,
            'luong_co_ban' => 13000000,
        ], $overrides);
    }

    private function service(): HopDongService
    {
        return new HopDongService(new HopDongRepository(app('db')), app('db'));
    }
}
