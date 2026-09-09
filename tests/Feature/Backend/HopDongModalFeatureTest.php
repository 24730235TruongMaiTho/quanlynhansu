<?php

namespace Tests\Feature\Backend;

use App\Contracts\HopDongServiceContract;
use App\Enums\HopDongPermission;
use App\Exceptions\HopDongDomainException;
use App\Models\NhanVien;
use App\Services\PermissionService;
use Illuminate\Foundation\Vite;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

final class HopDongModalFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
        });
        Schema::create('loai_hop_dong', static function (Blueprint $table): void {
            $table->increments('ma_lhd');
            $table->string('ten_lhd');
        });
        DB::table('nhan_vien')->insert(['ma_nv' => '00001']);
        DB::table('loai_hop_dong')->insert(['ma_lhd' => 1, 'ten_lhd' => 'Không thời hạn']);

        $actor = NhanVien::fromAuthRow((object) [
            'ma_nv' => '00001',
            'ho_ten' => 'Nguyễn An',
            'email' => 'an@example.test',
            'mat_khau' => 'test-hash',
            'ma_vt' => 1,
            'ma_tt' => 1,
        ]);
        $this->actingAs($actor);
        $allowed = array_map(static fn (HopDongPermission $permission): string => $permission->value, HopDongPermission::cases());
        $this->mock(PermissionService::class, function ($mock) use ($actor, $allowed): void {
            $mock->shouldReceive('canSeeModule')->andReturn(true);
            $mock->shouldReceive('allows')
                ->andReturnUsing(static function (mixed $candidate, mixed $permission) use ($actor, $allowed): bool {
                    $symbol = $permission instanceof \App\Contracts\PermissionDefinitionContract
                        ? $permission->symbol()
                        : $permission;

                    return $candidate instanceof NhanVien
                        && $candidate->getAuthIdentifier() === $actor->getAuthIdentifier()
                        && is_string($symbol)
                        && in_array($symbol, $allowed, true);
                });
        });
        $this->app->instance(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                return new HtmlString('');
            }
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('loai_hop_dong');
        Schema::dropIfExists('nhan_vien');

        parent::tearDown();
    }

    public function test_contract_list_exposes_create_modal_trigger_and_real_fallback(): void
    {
        $this->mock(HopDongServiceContract::class, function ($mock): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new LengthAwarePaginator([], 0, 20, 1, ['pageName' => 'page']));
            $mock->shouldReceive('formOptions')->once()->andReturn(['employees' => [], 'types' => []]);
        });

        $createUrl = route('backend.hopdong.create');
        $this->get('/hop-dong')
            ->assertOk()
            ->assertSee('data-action="modal"', false)
            ->assertSee('data-modal-mode="create"', false)
            ->assertSee('data-modal-url="'.e($createUrl).'"', false)
            ->assertSee('href="'.e($createUrl).'"', false)
            ->assertSee('data-simple-edit-modal', false);
    }

    public function test_modal_create_returns_form_partial_but_regular_create_keeps_full_page(): void
    {
        $this->mock(HopDongServiceContract::class, function ($mock): void {
            $mock->shouldReceive('formOptions')->twice()->andReturn([
                'employees' => [(object) ['ma_nv' => '00001', 'ho_ten' => 'Nguyễn An']],
                'types' => [(object) ['ma_lhd' => 1, 'ten_lhd' => 'Không thời hạn']],
            ]);
        });

        $this->get('/hop-dong/create', [
            'X-Create-Modal' => '1',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertViewIs('backend.hopdong.partials.create-modal-content')
            ->assertSee('data-contract-form', false)
            ->assertSee('data-simple-modal-form', false)
            ->assertSee('data-contract-modal-close', false)
            ->assertDontSee('<html', false);

        $this->get('/hop-dong/create')
            ->assertOk()
            ->assertViewIs('backend.hopdong.form')
            ->assertSee('<main', false)
            ->assertSee('data-contract-form', false);
    }

    public function test_contract_list_exposes_edit_modal_trigger_and_shared_dialog_for_update_permission(): void
    {
        $this->mock(HopDongServiceContract::class, function ($mock): void {
            $mock->shouldReceive('paginate')->once()->andReturn(new LengthAwarePaginator([
                (object) [
                    'ma_hd' => 18,
                    'ma_nv' => '00001',
                    'ho_ten' => 'Nguyễn An',
                    'ten_lhd' => 'Không thời hạn',
                    'ngay_ky' => '2019-02-15',
                    'ngay_het_han' => null,
                    'sap_het_han' => false,
                ],
            ], 1, 20, 1, ['pageName' => 'page']));
            $mock->shouldReceive('formOptions')->once()->andReturn(['employees' => [], 'types' => []]);
        });

        $editUrl = route('backend.hopdong.edit', 18);
        $this->get('/hop-dong')
            ->assertOk()
            ->assertSee('data-action="modal"', false)
            ->assertSee('data-modal-mode="edit"', false)
            ->assertSee('data-modal-url="'.e($editUrl).'"', false)
            ->assertSee('href="'.e($editUrl).'"', false)
            ->assertSee('data-edit-title="Chỉnh sửa hợp đồng"', false);
    }

    public function test_modal_edit_returns_form_partial_but_regular_edit_keeps_full_page(): void
    {
        $this->mock(HopDongServiceContract::class, function ($mock): void {
            $mock->shouldReceive('findOrFail')->twice()->with(18)->andReturn((object) [
                'ma_hd' => 18,
                'ma_nv' => '00001',
                'ma_lhd' => 1,
                'ngay_ky' => '2019-02-15',
                'ngay_het_han' => null,
                'luong_co_ban' => 1234000,
            ]);
            $mock->shouldReceive('formOptions')->twice()->andReturn([
                'employees' => [(object) ['ma_nv' => '00001', 'ho_ten' => 'Nguyễn An']],
                'types' => [(object) ['ma_lhd' => 1, 'ten_lhd' => 'Không thời hạn']],
            ]);
        });

        $this->get('/hop-dong/18/edit', [
            'X-Edit-Modal' => '1',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
            ->assertOk()
            ->assertViewIs('backend.hopdong.partials.edit-modal-content')
            ->assertSee('data-contract-form', false)
            ->assertSee('data-simple-modal-form', false)
            ->assertSee('name="_method"', false)
            ->assertSee('value="PUT"', false)
            ->assertSee('value="2019-02-15"', false)
            ->assertSee('value="1234000"', false)
            ->assertDontSee('<html', false);

        $this->get('/hop-dong/18/edit')
            ->assertOk()
            ->assertViewIs('backend.hopdong.form')
            ->assertSee('<main', false)
            ->assertSee('data-contract-form', false);
    }

    public function test_json_store_returns_created_contract_success(): void
    {
        $this->mock(HopDongServiceContract::class, function ($mock): void {
            $mock->shouldReceive('create')->once()->andReturn(20);
        });

        $this->postJson('/hop-dong', $this->payload())
            ->assertCreated()
            ->assertJson(['success' => true, 'message' => 'Đã thêm hợp đồng.'])
            ->assertJsonMissingPath('redirect');
    }

    public function test_json_store_maps_domain_validation_to_safe_422(): void
    {
        $this->mock(HopDongServiceContract::class, function ($mock): void {
            $mock->shouldReceive('create')->once()->andThrow(new HopDongDomainException(
                'Loại hợp đồng không tồn tại.',
                'HD_TYPE_NOT_FOUND',
                'ma_lhd',
            ));
        });

        $this->postJson('/hop-dong', $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.ma_lhd.0', 'Loại hợp đồng không tồn tại.')
            ->assertJsonMissingPath('error_code')
            ->assertDontSee('HD_TYPE_NOT_FOUND');
    }

    public function test_json_store_hides_unexpected_failure_details(): void
    {
        $this->mock(HopDongServiceContract::class, function ($mock): void {
            $mock->shouldReceive('create')->once()->andThrow(new \RuntimeException('SQLSTATE private details'));
        });

        $this->postJson('/hop-dong', $this->payload())
            ->assertStatus(500)
            ->assertJson(['success' => false, 'message' => 'Không thể tạo hợp đồng lúc này.'])
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('private details');
    }

    public function test_json_update_returns_success_without_redirect(): void
    {
        $this->mock(HopDongServiceContract::class, function ($mock): void {
            $mock->shouldReceive('update')->once()->with(18, \Mockery::type('array'));
        });

        $this->putJson('/hop-dong/18', $this->payload())
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Đã cập nhật hợp đồng.'])
            ->assertJsonMissingPath('redirect');
    }

    public function test_json_update_maps_domain_validation_to_safe_422(): void
    {
        $this->mock(HopDongServiceContract::class, function ($mock): void {
            $mock->shouldReceive('update')->once()->andThrow(new HopDongDomainException(
                'Ngày hết hạn phải sau Ngày ký.',
                'HD_EXPIRY_INVALID',
                'ngay_het_han',
            ));
        });

        $this->putJson('/hop-dong/18', $this->payload())
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.ngay_het_han.0', 'Ngày hết hạn phải sau Ngày ký.')
            ->assertJsonMissingPath('error_code');
    }

    public function test_json_update_maps_not_found_to_safe_404(): void
    {
        $this->mock(HopDongServiceContract::class, function ($mock): void {
            $mock->shouldReceive('update')->once()->andThrow(new HopDongDomainException(
                'Không tìm thấy hợp đồng.',
                'HD_NOT_FOUND',
            ));
        });

        $this->putJson('/hop-dong/18', $this->payload())
            ->assertNotFound()
            ->assertJson(['success' => false, 'message' => 'Không tìm thấy hợp đồng.']);
    }

    public function test_json_update_hides_persistence_failure_details(): void
    {
        $this->mock(HopDongServiceContract::class, function ($mock): void {
            $mock->shouldReceive('update')->once()->andThrow(new HopDongDomainException(
                'Không thể lưu hợp đồng lúc này.',
                'HD_PERSIST_FAILED',
            ));
        });

        $this->putJson('/hop-dong/18', $this->payload())
            ->assertStatus(500)
            ->assertJson(['success' => false, 'message' => 'Không thể cập nhật hợp đồng lúc này.'])
            ->assertDontSee('private details');
    }

    public function test_json_update_hides_unexpected_failure_details(): void
    {
        $this->mock(HopDongServiceContract::class, function ($mock): void {
            $mock->shouldReceive('update')->once()->andThrow(new \RuntimeException('SQLSTATE private details'));
        });

        $this->putJson('/hop-dong/18', $this->payload())
            ->assertStatus(500)
            ->assertJson(['success' => false, 'message' => 'Không thể cập nhật hợp đồng lúc này.'])
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('private details');
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'ma_nv' => '00001',
            'ma_lhd' => 1,
            'ngay_ky' => '05/09/2026',
            'ngay_het_han' => '06/09/2026',
            'luong_co_ban' => '13.000.000',
        ];
    }
}
