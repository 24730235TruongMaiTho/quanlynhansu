<?php

namespace Tests\Feature\Compatibility;

use App\Http\Requests\StoreChamCongRequest;
use App\Http\Requests\StoreLuongRequest;
use App\Http\Requests\StoreNghiPhepRequest;
use App\Http\Requests\UpdateChamCongRequest;
use App\Http\Requests\UpdateLuongRequest;
use App\Http\Requests\UpdateNghiPhepRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CanonicalEmployeeCodeValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', function (Blueprint $table): void {
            $table->string('ma_nv')->primary();
        });

        DB::table('nhan_vien')->insert(array_map(
            static fn (string $code): array => ['ma_nv' => $code],
            ['NV001', '1', '001', 'NV01', 'NV0001'],
        ));
    }

    #[DataProvider('employeeRequests')]
    public function test_request_accepts_an_existing_canonical_employee_code(string $requestClass): void
    {
        $validator = Validator::make(
            ['ma_nv' => 'NV001'],
            ['ma_nv' => (new $requestClass())->rules()['ma_nv']],
        );

        $this->assertFalse($validator->errors()->has('ma_nv'));
    }

    #[DataProvider('employeeRequests')]
    public function test_request_rejects_noncanonical_employee_codes(string $requestClass): void
    {
        $rules = ['ma_nv' => (new $requestClass())->rules()['ma_nv']];

        foreach ([1, '001', 'NV01', 'NV0001'] as $invalidCode) {
            $validator = Validator::make(['ma_nv' => $invalidCode], $rules);
            $this->assertTrue(
                $validator->errors()->has('ma_nv'),
                sprintf('%s accepted invalid employee code [%s].', $requestClass, $invalidCode),
            );
        }
    }

    #[DataProvider('employeeRequests')]
    public function test_request_requires_the_employee_code_and_checks_existence(string $requestClass): void
    {
        $rules = ['ma_nv' => (new $requestClass())->rules()['ma_nv']];

        $this->assertTrue(Validator::make([], $rules)->errors()->has('ma_nv'));
        $this->assertTrue(Validator::make(['ma_nv' => 'NV999'], $rules)->errors()->has('ma_nv'));
    }

    public static function employeeRequests(): array
    {
        return [
            'store attendance' => [StoreChamCongRequest::class],
            'update attendance' => [UpdateChamCongRequest::class],
            'store leave' => [StoreNghiPhepRequest::class],
            'update leave' => [UpdateNghiPhepRequest::class],
            'store salary' => [StoreLuongRequest::class],
            'update salary' => [UpdateLuongRequest::class],
        ];
    }
}
