<?php

namespace Tests\Feature\Backend\NhanVien;

use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Services\NhanVienService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Vite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

class NhanVienShowTest extends TestCase
{
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                $entries = is_array($entrypoints) ? $entrypoints : [$entrypoints];

                return new HtmlString(collect($entries)->map(
                    fn (string $entry): string => '<script type="module" src="/build/'.basename($entry).'"></script>',
                )->implode(''));
            }
        });
    }

    public function test_service_returns_the_repository_employee(): void
    {
        $employee = $this->employee();
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('NV001')->andReturn($employee);

        $service = new NhanVienService(
            $this->app->make('db'),
            $repository,
            $this->app->make(FilesystemManager::class),
            $this->app->make(Hasher::class),
        );

        $this->assertSame($employee, $service->findOrFail('NV001'));
    }

    public function test_service_turns_a_missing_employee_into_a_safe_not_found_response(): void
    {
        $repository = Mockery::mock(NhanVienRepositoryContract::class);
        $repository->shouldReceive('find')->once()->with('NV404')->andReturnNull();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('');

        (new NhanVienService(
            $this->app->make('db'),
            $repository,
            $this->app->make(FilesystemManager::class),
            $this->app->make(Hasher::class),
        ))->findOrFail('NV404');
    }

    public function test_enabled_show_renders_the_complete_safe_profile_and_whitelisted_back_link(): void
    {
        $this->enableEmployeeModule();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findOrFail')->once()->with('NV001')->andReturn($this->employee());
        });

        $backUrl = route('backend.nhanvien.index', [
            'tu_khoa' => 'Nguyễn An',
            'ma_pb' => '1',
            'ma_cv' => '2',
            'ma_tt' => '1',
            'page' => '3',
            'so_dong' => '20',
        ]);
        $editUrl = route('backend.nhanvien.edit', [
            'ma_nv' => 'NV001',
            'tu_khoa' => 'Nguyễn An',
            'ma_pb' => '1',
            'ma_cv' => '2',
            'ma_tt' => '1',
            'page' => '3',
            'so_dong' => '20',
        ]);

        $response = $this->get('/admin/nhan-vien/NV001?'.http_build_query([
            'tu_khoa' => 'Nguyễn An',
            'ma_pb' => 1,
            'ma_cv' => 2,
            'ma_tt' => 1,
            'page' => 3,
            'so_dong' => 20,
            'redirect' => 'https://evil.example/steal',
            'return_to' => 'https://evil.example/return',
        ]));

        $response
            ->assertOk()
            ->assertViewIs('backend.nhanvien.show')
            ->assertViewHas('employee', fn (object $employee): bool => $employee->ma_nv === 'NV001')
            ->assertSee('Nguyễn An')
            ->assertSee('NV001')
            ->assertSee('01/01/1990')
            ->assertSee('Nam')
            ->assertSee('0900000001')
            ->assertSee('an@example.test')
            ->assertSee('123456789001')
            ->assertSee('TP Hồ Chí Minh')
            ->assertSee('12 Nguyễn Huệ')
            ->assertSee('Phường Bến Nghé')
            ->assertSee('Quận 1')
            ->assertSee('Kỹ thuật')
            ->assertSee('Lập trình viên')
            ->assertSee('Đang làm việc')
            ->assertSee('Nhân viên')
            ->assertSee('<dl', false)
            ->assertSee('src="'.Storage::disk('public')->url($this->employee()->anh_dai_dien).'"', false)
            ->assertSee('alt="Ảnh đại diện của Nguyễn An"', false)
            ->assertSee('href="'.e($backUrl).'"', false)
            ->assertSee('href="'.e($editUrl).'"', false)
            ->assertDontSee('evil.example')
            ->assertDontSee('secret-hash-value')
            ->assertDontSee('mat_khau')
            ->assertSee('Chỉnh sửa')
            ->assertDontSee('Xóa nhân viên')
            ->assertDontSee('Đặt lại mật khẩu')
            ->assertSee('/build/nhanvien.js', false);

        $this->assertSame(1, substr_count($response->getContent(), '/build/nhanvien.js'));
    }

    public function test_show_hides_the_edit_action_for_a_privileged_employee(): void
    {
        $this->enableEmployeeModule();
        $employee = $this->employee();
        $employee->ky_hieu_vai_tro = 'QUAN_TRI';
        $employee->ten_vt = 'Quản trị viên';

        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('findOrFail')->once()->with('NV001')->andReturn($employee);
        });

        $editUrl = route('backend.nhanvien.edit', ['ma_nv' => 'NV001']);

        $this->get('/admin/nhan-vien/NV001')
            ->assertOk()
            ->assertDontSee('Chỉnh sửa')
            ->assertDontSee('href="'.e($editUrl).'"', false);
    }

    public function test_show_renders_initials_when_the_employee_has_no_avatar(): void
    {
        $this->enableEmployeeModule();
        $employee = $this->employee();
        $employee->anh_dai_dien = null;

        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('findOrFail')->once()->with('NV001')->andReturn($employee);
        });

        $this->get('/admin/nhan-vien/NV001')
            ->assertOk()
            ->assertSee('aria-label="Ảnh đại diện của Nguyễn An"', false)
            ->assertSee('>NA<', false)
            ->assertDontSee('<img', false);
    }

    public function test_show_never_renders_an_external_avatar_origin(): void
    {
        $this->enableEmployeeModule();
        $employees = collect([
            'https://tracker.example/pixel.png',
            '//tracker.example/pixel.png',
        ])->map(function (string $avatarPath): object {
            $employee = $this->employee();
            $employee->anh_dai_dien = $avatarPath;

            return $employee;
        });

        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employees): void {
            $mock->shouldReceive('findOrFail')
                ->twice()
                ->with('NV001')
                ->andReturn($employees[0], $employees[1]);
        });

        foreach ($employees as $employee) {
            $this->get('/admin/nhan-vien/NV001')
                ->assertOk()
                ->assertSee('src="'.Storage::disk('public')->url($employee->anh_dai_dien).'"', false)
                ->assertDontSee('src="https://tracker.example', false)
                ->assertDontSee('src="//tracker.example', false);
        }
    }

    public function test_dynamic_employee_name_is_escaped_in_the_document_title(): void
    {
        $this->enableEmployeeModule();
        $employee = $this->employee();
        $employee->ho_ten = '</title><script>alert(1)</script>';

        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($employee): void {
            $mock->shouldReceive('findOrFail')->once()->with('NV001')->andReturn($employee);
        });

        $this->get('/admin/nhan-vien/NV001')
            ->assertOk()
            ->assertSee('&lt;/title&gt;&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('</title><script>alert(1)</script>', false);
    }

    public function test_missing_employee_returns_404_without_leaking_internal_details(): void
    {
        $this->enableEmployeeModule();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findOrFail')->once()->with('NV404')->andThrow(new NotFoundHttpException);
        });

        $this->get('/admin/nhan-vien/NV404')
            ->assertNotFound()
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('sp_nhan_vien_chi_tiet');
    }

    public function test_invalid_employee_codes_do_not_dispatch_show(): void
    {
        $this->enableEmployeeModule();
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('findOrFail');
        });

        foreach (['NV1', 'NV0001', 'nv001'] as $code) {
            $this->get('/admin/nhan-vien/'.$code)->assertNotFound();
        }
    }

    public function test_employee_module_guard_blocks_show_before_calling_the_service(): void
    {
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('findOrFail');
        });

        $this->get('/admin/nhan-vien/NV001')->assertNotFound();
    }

    public function test_index_show_link_preserves_only_the_six_whitelisted_query_keys(): void
    {
        $this->enableEmployeeModule();
        $filters = [
            'tu_khoa' => 'Nguyễn An',
            'ma_pb' => 1,
            'ma_cv' => 2,
            'ma_tt' => 1,
            'page' => 3,
            'so_dong' => 20,
        ];
        $this->mock(NhanVienServiceContract::class, function (MockInterface $mock) use ($filters): void {
            $mock->shouldReceive('paginate')->once()->with($filters)->andReturn(
                new LengthAwarePaginator([$this->employee()], 41, 20, 3, ['pageName' => 'page']),
            );
            $mock->shouldReceive('lookups')->once()->andReturn([
                'phong_ban' => [],
                'chuc_vu' => [],
                'trang_thai' => [],
            ]);
        });

        $showUrl = route('backend.nhanvien.show', ['ma_nv' => 'NV001'] + $filters);

        $this->get('/admin/nhan-vien?'.http_build_query($filters + [
            'redirect' => 'https://evil.example/steal',
        ]))
            ->assertOk()
            ->assertSee('Xem')
            ->assertSee('href="'.e($showUrl).'"', false)
            ->assertDontSee('evil.example');
    }

    private function employee(): object
    {
        return (object) [
            'ma_nv' => 'NV001',
            'ho_ten' => 'Nguyễn An',
            'ngay_sinh' => '1990-01-01',
            'gioi_tinh' => 1,
            'sdt' => '0900000001',
            'email' => 'an@example.test',
            'ngay_vao_lam' => '2020-01-15',
            'ma_pb' => 1,
            'ten_pb' => 'Kỹ thuật',
            'ma_cv' => 2,
            'ten_cv' => 'Lập trình viên',
            'dan_toc' => 'Kinh',
            'cccd' => '123456789001',
            'noi_cap_cccd' => 'Cục CSQLHC về TTXH',
            'hoc_van' => 'Đại học',
            'ma_tt' => 1,
            'ky_hieu' => 'DANG_LAM',
            'ten_tt' => 'Đang làm việc',
            'ngay_nghi_viec' => null,
            'ma_vt' => 3,
            'ky_hieu_vai_tro' => 'NHAN_VIEN_MAC_DINH',
            'ten_vt' => 'Nhân viên',
            'anh_dai_dien' => 'nhan-vien/avatars/550e8400-e29b-41d4-a716-446655440000.png',
            'dia_chi_cu_the' => '12 Nguyễn Huệ',
            'phuong_xa' => 'Phường Bến Nghé',
            'quan_huyen' => 'Quận 1',
            'tinh_thanh' => 'TP Hồ Chí Minh',
            'mat_khau' => 'secret-hash-value',
        ];
    }
}
