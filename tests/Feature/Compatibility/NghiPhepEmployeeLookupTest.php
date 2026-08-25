<?php

namespace Tests\Feature\Compatibility;

use App\Contracts\NhanVienServiceContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

class NghiPhepEmployeeLookupTest extends TestCase
{
    use InteractsWithEmployeeModule;

    public function test_employee_lookups_are_fail_closed_before_the_service_is_called(): void
    {
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('paginate');
            $mock->shouldNotReceive('paginateForAttendance');
        });

        $this->getJson('/api/v1/nghi-phep/nhan-vien')->assertUnauthorized();
        $this->getJson('/api/v1/cham-cong/nhan-vien')->assertUnauthorized();
        $this->get('/api/v1/nghi-phep/nhan-vien')->assertRedirect(route('login'));
    }

    public function test_enabled_lookup_maps_legacy_query_to_the_canonical_employee_service(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Xem]);

        $paginator = new LengthAwarePaginator(
            collect([
                (object) [
                    'ma_nv' => 'NV001',
                    'ho_ten' => 'Nguyễn An',
                    'sdt' => '0900000001',
                    'email' => 'an@example.test',
                    'ngay_vao_lam' => '2020-01-01',
                    'anh_dai_dien' => null,
                    'ma_pb' => 2,
                    'ten_pb' => 'Kỹ thuật',
                    'ma_cv' => 3,
                    'ten_cv' => 'Lập trình viên',
                    'ma_tt' => 1,
                    'ky_hieu' => 'DANG_LAM',
                    'ten_tt' => 'Đang làm',
                ],
                (object) [
                    'ma_nv' => 'NV002',
                    'ho_ten' => 'Trần Bình',
                    'sdt' => '0900000002',
                    'email' => 'binh@example.test',
                    'ngay_vao_lam' => '2022-02-02',
                    'anh_dai_dien' => null,
                    'ma_pb' => 2,
                    'ten_pb' => 'Kỹ thuật',
                    'ma_cv' => 3,
                    'ten_cv' => 'Lập trình viên',
                    'ma_tt' => 1,
                    'ky_hieu' => 'DANG_LAM',
                    'ten_tt' => 'Đang làm',
                ],
            ]),
            2,
            15,
            2,
            ['pageName' => 'page'],
        );

        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($paginator): void {
            $mock->shouldReceive('paginate')->once()->with([
                'tu_khoa' => 'NV',
                'ma_pb' => 2,
                'ma_cv' => 3,
                'ma_tt' => null,
                'page' => 2,
                'so_dong' => 15,
            ])->andReturn($paginator);
        });

        $response = $this->getJson(
            '/api/v1/nghi-phep/nhan-vien?tu_khoa=NV&ma_pb=2&ma_cv=3&page=2&per_page=15',
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.per_page', 15)
            ->assertJsonPath('data.total', 2)
            ->assertJsonCount(2, 'data.data')
            ->assertJsonMissingPath('data.data.0.mat_khau')
            ->assertJsonMissingPath('data.data.1.mat_khau');

        $codes = array_column($response->json('data.data'), 'ma_nv');
        $this->assertSame(['NV001', 'NV002'], $codes);
        $this->assertSame($codes, array_values(array_unique($codes)));
    }

    public function test_department_manager_leave_lookup_forces_forged_and_omitted_department_filters(): void
    {
        $actor = $this->actingAsEmployeeWithPermissions([
            \App\Enums\NhanVienPermission::Xem,
        ]);
        $actor->forceFill([
            'ma_vt' => \App\Enums\NhanVienRole::DepartmentManager->value,
            'ma_pb' => 2,
        ]);

        $paginator = new LengthAwarePaginator([], 0, 15, 1);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($paginator): void {
            $mock->shouldReceive('paginate')->twice()
                ->withArgs(static fn (array $filters): bool => $filters['ma_pb'] === 2)
                ->andReturn($paginator);
        });

        $this->getJson('/api/v1/nghi-phep/nhan-vien?ma_pb=3')
            ->assertOk();
        $this->getJson('/api/v1/nghi-phep/nhan-vien')
            ->assertOk();
    }

    public function test_department_manager_leave_lookup_with_missing_department_fails_closed(): void
    {
        $actor = $this->actingAsEmployeeWithPermissions([
            \App\Enums\NhanVienPermission::Xem,
        ]);
        $actor->forceFill([
            'ma_vt' => \App\Enums\NhanVienRole::DepartmentManager->value,
            'ma_pb' => null,
        ]);
        $paginator = new LengthAwarePaginator([], 0, 15, 1);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($paginator): void {
            $mock->shouldReceive('paginate')->once()
                ->withArgs(static fn (array $filters): bool => $filters['ma_pb'] === 0)
                ->andReturn($paginator);
        });

        $this->getJson('/api/v1/nghi-phep/nhan-vien')
            ->assertOk();
    }

    #[DataProvider('unscopedRoles')]
    public function test_unscoped_roles_keep_requested_leave_department_filter(int $role): void
    {
        $actor = $this->actingAsEmployeeWithPermissions([
            \App\Enums\NhanVienPermission::Xem,
        ]);
        $actor->forceFill([
            'ma_vt' => $role,
            'ma_pb' => 2,
        ]);
        $paginator = new LengthAwarePaginator([], 0, 15, 1);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($paginator): void {
            $mock->shouldReceive('paginate')->once()
                ->withArgs(static fn (array $filters): bool => $filters['ma_pb'] === 3)
                ->andReturn($paginator);
        });

        $this->getJson('/api/v1/nghi-phep/nhan-vien?ma_pb=3')
            ->assertOk();
    }

    public static function unscopedRoles(): array
    {
        return [
            'super admin' => [1],
            'human resources' => [2],
            'cbl admin' => [3],
            'employee' => [5],
        ];
    }

    public function test_enabled_lookup_returns_a_stable_error_without_internal_details(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Xem]);
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('paginate')->once()->andThrow(
                new RuntimeException('SQLSTATE[42000] mat_khau secret'),
            );
        });

        $this->getJson('/api/v1/nghi-phep/nhan-vien')
            ->assertStatus(500)
            ->assertExactJson([
                'success' => false,
                'message' => 'Không thể tải danh sách nhân viên.',
            ]);
    }

    public function test_legacy_employee_mutations_are_removed_with_stable_http_semantics(): void
    {
        $this->actingAsEmployeeWithPermissions([\App\Enums\NhanVienPermission::Xem]);

        $this->postJson('/api/v1/nghi-phep/nhan-vien')->assertMethodNotAllowed();
        $this->putJson('/api/v1/nghi-phep/nhan-vien/NV001')->assertNotFound();
        $this->patchJson('/api/v1/nghi-phep/nhan-vien/NV001')->assertNotFound();
    }
}
