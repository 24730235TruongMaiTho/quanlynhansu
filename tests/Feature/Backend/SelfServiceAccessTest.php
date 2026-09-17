<?php

namespace Tests\Feature\Backend;

use App\Contracts\HopDongServiceContract;
use App\Contracts\NhanVienServiceContract;
use App\Services\ChamCongService;
use App\Services\LuongService;
use Illuminate\Foundation\Vite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\HtmlString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

final class SelfServiceAccessTest extends TestCase
{
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                return new HtmlString('');
            }
        });
    }

    #[DataProvider('canonicalRoles')]
    public function test_every_role_can_open_all_self_service_pages_without_module_permissions(int $role): void
    {
        $this->actingAsEmployeeWithPermissions([], ['ma_nv' => '00007', 'ma_vt' => $role]);
        $this->bindEmptySelfServicePages();

        foreach (['/ho-so-ca-nhan', '/tao-nghi-phep', '/hop-dong-cua-toi', '/luong-cua-toi', '/cham-cong-cua-toi'] as $url) {
            $this->get($url)->assertSuccessful();
        }
    }

    public static function canonicalRoles(): array
    {
        return [[1], [2], [3], [4], [5]];
    }

    public function test_management_pages_remain_protected_without_module_permissions(): void
    {
        $this->actingAsEmployeeWithPermissions([], ['ma_nv' => '00007', 'ma_vt' => 5]);

        foreach (['/hop-dong', '/luong', '/cham-cong', '/nghi-phep'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_deny_all_sidebar_shows_self_service_but_not_management_links(): void
    {
        $this->actingAsEmployeeWithPermissions([], ['ma_nv' => '00007', 'ma_vt' => 5]);

        $this->view('backend.layouts.sidebar')
            ->assertSee('Tài khoản cá nhân')
            ->assertSee('Thông Tin Cá Nhân')
            ->assertSee('Đơn nghỉ phép')
            ->assertSee('Hợp đồng')
            ->assertSee('Lương')
            ->assertSee('Chấm công')
            ->assertDontSee('Đơn nghỉ phép của tôi')
            ->assertDontSee('Hợp đồng của tôi')
            ->assertDontSee('Lương của tôi')
            ->assertDontSee('Chấm công của tôi')
            ->assertDontSee('Danh sách hợp đồng')
            ->assertDontSee('Danh sách lương')
            ->assertDontSee('Danh sách chấm công')
            ->assertDontSee('Danh sách nghỉ phép');
    }

    public function test_guest_is_redirected_from_self_service_pages(): void
    {
        foreach (['/ho-so-ca-nhan', '/tao-nghi-phep', '/hop-dong-cua-toi', '/luong-cua-toi', '/cham-cong-cua-toi'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    private function bindEmptySelfServicePages(): void
    {
        $empty = new LengthAwarePaginator([], 0, 10, 1, ['path' => '/']);

        $this->mock(HopDongServiceContract::class, function ($mock) use ($empty): void {
            $mock->shouldReceive('paginateForEmployee')->andReturn($empty);
        });
        $this->mock(NhanVienServiceContract::class, function ($mock): void {
            $mock->shouldReceive('findOrFail')->andReturn(auth()->user());
        });
        $this->mock(LuongService::class, function ($mock) use ($empty): void {
            $mock->shouldReceive('getForEmployee')->andReturn([
                'success' => true,
                'data' => $empty,
            ]);
        });
        $this->mock(ChamCongService::class, function ($mock) use ($empty): void {
            $mock->shouldReceive('forEmployee')->andReturn([
                'success' => true,
                'paginator' => $empty,
                'summary' => [
                    'tong_gio_lam' => 0.0,
                    'so_lan_vao_muon' => 0,
                    'so_lan_ve_som' => 0,
                    'so_ngay_cham_cong' => 0.0,
                ],
            ]);
        });
    }
}
