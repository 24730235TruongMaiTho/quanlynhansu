<?php

namespace Tests\Feature\Backend;

use App\Http\Controllers\Backend\LuongHeSoLuongController;
use App\Repositories\LuongRepository;
use App\Services\LuongService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LuongJsonListContractTest extends TestCase
{
    public function test_salary_list_passes_allowlisted_pagination_to_the_service(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Backend/LuongController.php'));
        $repository = file_get_contents(app_path('Repositories/LuongRepository.php'));
        $service = file_get_contents(app_path('Services/LuongService.php'));

        self::assertIsString($controller);
        self::assertIsString($repository);
        self::assertIsString($service);
        self::assertStringContainsString("'page' => max((int) (\$validated['page'] ?? 1), 1)", $controller);
        self::assertStringContainsString("'per_page' => \$this->pageSize(\$validated['per_page'] ?? null)", $controller);
        self::assertStringContainsString('JsonPaginator::from($paginator)', $service);
        self::assertStringContainsString('in_array($candidate, [10, 20, 50], true)', $repository);
        self::assertStringNotContainsString("?? 15", $repository);
    }

    public function test_coefficient_list_uses_server_pagination_and_shared_shape(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Backend/LuongHeSoLuongController.php'));

        self::assertIsString($controller);
        self::assertStringContainsString('use App\\Support\\JsonPaginator;', $controller);
        self::assertStringContainsString("in_array(\$requestedPerPage, [10, 20, 50], true)", $controller);
        self::assertStringContainsString('->paginate(', $controller);
        self::assertStringContainsString('JsonPaginator::from($paginator)', $controller);
        $indexStart = strpos($controller, 'public function index(');
        $storeStart = strpos($controller, 'public function store(');
        $indexBody = substr($controller, $indexStart, $storeStart - $indexStart);
        self::assertStringNotContainsString("->get();", $indexBody);
    }

    public function test_salary_service_serializes_page_two_with_shared_metadata(): void
    {
        $repository = \Mockery::mock(LuongRepository::class);
        $repository->shouldReceive('all')->once()->with([
            'ma_nv' => 'NV-001',
            'ky_luong' => '2026-09-01',
            'ma_pb' => 2,
            'ma_cv' => 3,
            'page' => 2,
            'per_page' => 20,
        ])->andReturn(new LengthAwarePaginator(
            [(object) ['ma_luong' => 21]],
            41,
            20,
            2,
            ['pageName' => 'page'],
        ));

        $result = (new LuongService($repository))->getAll([
            'ma_nv' => 'NV-001',
            'ky_luong' => '2026-09-01',
            'ma_pb' => 2,
            'ma_cv' => 3,
            'page' => 2,
            'per_page' => 20,
        ]);

        self::assertTrue($result['success']);
        self::assertSame([
            'data',
            'current_page',
            'last_page',
            'per_page',
            'total',
            'from',
            'to',
        ], array_keys($result['data']));
        self::assertSame(2, $result['data']['current_page']);
        self::assertSame(20, $result['data']['per_page']);
        self::assertSame(41, $result['data']['total']);
        self::assertSame(21, $result['data']['from']);
        self::assertSame(21, $result['data']['to']);
    }

    public function test_empty_coefficient_selection_keeps_exact_shape_and_safe_page_size(): void
    {
        $response = (new LuongHeSoLuongController())->index(
            Request::create('/api/v1/luong/he-so-luong', 'GET', [
                'page' => 2,
                'per_page' => 5,
            ])
        );
        $payload = $response->getData(true);

        self::assertSame([
            'success',
            'data',
        ], array_keys($payload));
        self::assertSame([
            'data',
            'current_page',
            'last_page',
            'per_page',
            'total',
            'from',
            'to',
        ], array_keys($payload['data']));
        self::assertSame(2, $payload['data']['current_page']);
        self::assertSame(10, $payload['data']['per_page']);
        self::assertSame(0, $payload['data']['total']);
    }

    public function test_salary_service_hides_repository_exception_details(): void
    {
        $repository = \Mockery::mock(LuongRepository::class);
        $repository->shouldReceive('all')->once()->andThrow(
            new \RuntimeException('SQLSTATE[HY000]: internal salary detail')
        );

        $result = (new LuongService($repository))->getAll([]);

        self::assertFalse($result['success']);
        self::assertSame('Không thể tải danh sách lương.', $result['message']);
        self::assertStringNotContainsString('SQLSTATE', $result['message']);
    }

    public function test_coefficient_query_exception_returns_safe_json(): void
    {
        DB::shouldReceive('table')->once()->andThrow(
            new \RuntimeException('SQLSTATE[HY000]: internal coefficient detail')
        );

        $response = (new LuongHeSoLuongController())->index(
            Request::create('/api/v1/luong/he-so-luong', 'GET', [
                'ma_nv' => '00001',
            ])
        );
        $payload = $response->getData(true);

        self::assertSame(500, $response->getStatusCode());
        self::assertFalse($payload['success']);
        self::assertSame('Không thể tải danh sách hệ số lương.', $payload['message']);
        self::assertStringNotContainsString('SQLSTATE', $payload['message']);
    }

    public function test_salary_filter_and_tables_expose_the_shared_list_contract(): void
    {
        $view = file_get_contents(resource_path('views/backend/luong/index.blade.php'));

        self::assertIsString($view);
        self::assertStringContainsString('id="salary-filter-form"', $view);
        self::assertStringContainsString('filter-bar', $view);
        self::assertStringContainsString('for="search-field"', $view);
        self::assertStringContainsString('for="department-filter"', $view);
        self::assertStringContainsString('for="position-filter"', $view);
        self::assertStringContainsString('Áp dụng bộ lọc', $view);
        self::assertStringContainsString('Đặt lại', $view);
        self::assertSame(1, substr_count($view, 'id="clear-filter-btn"'));
        self::assertStringNotContainsString('id="clear-filter-btn-secondary"', $view);
        self::assertStringNotContainsString('>Xóa lọc<', $view);
        self::assertStringContainsString('value="10"', $view);
        self::assertStringContainsString('value="20"', $view);
        self::assertStringContainsString('value="50"', $view);
        self::assertStringContainsString('<caption class="visually-hidden">', $view);
        self::assertStringContainsString('scope="col"', $view);
        self::assertStringContainsString('id="coefficient-pagination"', $view);
    }
}
