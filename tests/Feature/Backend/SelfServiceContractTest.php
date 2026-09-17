<?php

namespace Tests\Feature\Backend;

use App\Contracts\HopDongServiceContract;
use Illuminate\Foundation\Vite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\HtmlString;
use Mockery\MockInterface;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

final class SelfServiceContractTest extends TestCase
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

    public function test_employee_without_contract_permission_sees_only_the_own_read_only_page(): void
    {
        $this->actingAsEmployeeWithPermissions([], ['ma_nv' => '00007', 'ma_vt' => 5]);

        $this->mock(HopDongServiceContract::class, function (MockInterface $mock): void {
            $mock->shouldReceive('paginateForEmployee')
                ->once()
                ->with('00007', 20)
                ->andReturn(new LengthAwarePaginator([
                    (object) [
                        'ma_hd' => 9,
                        'ma_nv' => '00007',
                        'ma_lhd' => 1,
                        'ten_lhd' => 'Không thời hạn',
                        'ngay_ky' => '2025-01-01',
                        'ngay_het_han' => null,
                        'luong_co_ban' => 9000000,
                    ],
                ], 1, 20, 1, ['path' => '/hop-dong-cua-toi', 'pageName' => 'page']));
        });

        $this->get('/hop-dong-cua-toi')
            ->assertOk()
            ->assertViewIs('backend.selfservice.hopdong')
            ->assertSee('Hợp đồng của tôi')
            ->assertSee('Không thời hạn')
            ->assertDontSee('00007')
            ->assertDontSee('Thêm hợp đồng')
            ->assertDontSee('Chỉnh sửa')
            ->assertDontSee('Xóa');
    }

    public function test_self_contract_route_is_auth_only_and_management_route_remains_protected(): void
    {
        $self = \Illuminate\Support\Facades\Route::getRoutes()->getByName('backend.selfservice.hopdong.index');
        self::assertNotNull($self);
        self::assertContains('auth', $self->gatherMiddleware());
        self::assertNotContains('can:HopDong.Read', $self->gatherMiddleware());

        $management = \Illuminate\Support\Facades\Route::getRoutes()->getByName('backend.hopdong.index');
        self::assertNotNull($management);
        self::assertContains('can:HopDong.Read', $management->gatherMiddleware());
    }

    public function test_guest_cannot_access_own_contract_page(): void
    {
        $this->get('/hop-dong-cua-toi')->assertRedirect(route('login'));
    }
}
