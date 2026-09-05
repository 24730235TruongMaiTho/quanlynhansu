<?php

namespace Tests\Feature\Backend;

use App\Repositories\LuongRepository;
use App\Services\LuongService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LuongHeSoLuongTask12BContractTest extends TestCase
{
    public function test_coefficient_permission_contract_is_registered_with_active_ids(): void
    {
        self::assertFileExists(app_path('Enums/HeSoLuongPermission.php'));

        $source = file_get_contents(app_path('Enums/HeSoLuongPermission.php'));
        $permissions = file_get_contents(config_path('permissions.php'));

        self::assertIsString($source);
        self::assertIsString($permissions);
        self::assertStringContainsString("'HeSoLuong.Read'", $source);
        self::assertStringContainsString('=> 38', $source);
        self::assertStringContainsString('=> 41', $source);
        self::assertStringContainsString('HeSoLuongPermission::class', $permissions);
    }

    public function test_coefficient_routes_use_exact_permission_and_include_delete(): void
    {
        $routes = file_get_contents(base_path('routes/api.php'));

        self::assertIsString($routes);
        self::assertStringContainsString("use App\\Enums\\HeSoLuongPermission;", $routes);
        self::assertStringContainsString("Route::delete('he-so-luong/{ma_ls}'", $routes);
        self::assertStringContainsString("HeSoLuongPermission::Xoa->value", $routes);
        self::assertStringNotContainsString("LuongPermission::Xem->value)->name('api.v1.luong.he-so-luong'", $routes);
    }

    public function test_coefficient_mutation_routes_accept_numeric_ids_only(): void
    {
        foreach ([
            'api.v1.luong.he-so-luong.show',
            'api.v1.luong.he-so-luong.update',
            'api.v1.luong.he-so-luong.destroy',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);
            self::assertNotNull($route, $name);
            self::assertSame('[0-9]+', $route->wheres['ma_ls'] ?? null, $name);
        }
    }

    public function test_coefficient_request_contract_requires_strict_ranges_and_dates(): void
    {
        $store = file_get_contents(app_path('Http/Requests/StoreLuongHeSoLuongRequest.php'));
        $update = file_get_contents(app_path('Http/Requests/UpdateLuongHeSoLuongRequest.php'));

        self::assertIsString($store);
        self::assertIsString($update);
        self::assertStringContainsString("'regex:/\\A[0-9]{5}\\z/'", $store);
        self::assertStringContainsString("'required', 'date_format:Y-m-d'", $store);
        self::assertStringNotContainsString("'nullable', 'date_format:Y-m-d'", $store);
        self::assertStringContainsString("regex:/\\A(?:\\d{1,3})(?:\\.\\d{1,2})?\\z/", $store);
        self::assertStringNotContainsString("'sometimes'", $update);
    }

    public function test_salary_export_does_not_call_missing_stored_procedure_and_hides_raw_errors(): void
    {
        $service = file_get_contents(app_path('Services/LuongService.php'));
        $procedure = 'sp_luong_tim_kiem_phan_trang';

        self::assertIsString($service);
        self::assertStringNotContainsString('CALL '.$procedure, $service);
        self::assertStringNotContainsString("'message' => \$e->getMessage()", $service);
        self::assertStringNotContainsString('Không thể xuất báo cáo lương: ', $service);
    }

    public function test_active_seed_grants_coefficient_permissions_to_superadmin_and_hr(): void
    {
        $seed = file_get_contents(base_path('database/sql/du_lieu_mau.sql'));

        self::assertIsString($seed);
        self::assertStringContainsString('(1, 38),(1, 39),(1, 40),(1, 41),(1, 42)', $seed);
        self::assertStringContainsString('(2, 38),(2, 39),(2, 40),(2, 41),(2, 42)', $seed);
        self::assertStringContainsString('ALTER TABLE quyen AUTO_INCREMENT = 43;', $seed);
    }

    public function test_salary_mutation_failures_are_reported_with_safe_messages(): void
    {
        $repository = \Mockery::mock(LuongRepository::class);
        $repository->shouldReceive('find')->once()->andThrow(new \RuntimeException('SQLSTATE[secret]'));
        $repository->shouldReceive('create')->once()->andThrow(new \RuntimeException('SQLSTATE[secret]'));
        $repository->shouldReceive('update')->once()->andThrow(new \RuntimeException('SQLSTATE[secret]'));
        $repository->shouldReceive('delete')->once()->andThrow(new \RuntimeException('SQLSTATE[secret]'));

        $service = new LuongService($repository);
        $results = [
            $service->getById(1),
            $service->create([]),
            $service->update(1, []),
            $service->delete(1),
        ];

        foreach ($results as $result) {
            self::assertFalse($result['success']);
            self::assertStringNotContainsString('SQLSTATE', $result['message']);
        }
        self::assertSame('Không thể tải bản ghi lương.', $results[0]['message']);
        self::assertSame('Không thể tạo bản ghi lương.', $results[1]['message']);
        self::assertSame('Không thể cập nhật bản ghi lương.', $results[2]['message']);
        self::assertSame('Không thể xóa bản ghi lương.', $results[3]['message']);
    }

    public function test_salary_export_reads_every_repository_page_without_a_fixed_truncation(): void
    {
        $repository = \Mockery::mock(LuongRepository::class);
        $repository->shouldReceive('all')->once()->with([
            'ky_luong' => '2026-09-01', 'page' => 1, 'per_page' => 50,
        ])->andReturn(new LengthAwarePaginator(
            [(object) ['ma_luong' => 1]], 51, 50, 1,
        ));
        $repository->shouldReceive('all')->once()->with([
            'ky_luong' => '2026-09-01', 'page' => 2, 'per_page' => 50,
        ])->andReturn(new LengthAwarePaginator(
            [(object) ['ma_luong' => 51]], 51, 50, 2,
        ));

        $method = new \ReflectionMethod(LuongService::class, 'getAllSalaryRowsForExport');
        $method->setAccessible(true);
        $rows = $method->invoke(new LuongService($repository), ['ky_luong' => '2026-09-01']);

        self::assertCount(2, $rows);
        self::assertSame(51, $rows->last()->ma_luong);
    }
}
