<?php

namespace Tests\Feature\Backend;

use App\Enums\ChamCongPermission;
use App\Models\ChamCong;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

final class ChamCongDeleteFeatureTest extends TestCase
{
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('cham_cong', static function (Blueprint $table): void {
            $table->increments('ma_cc');
            $table->string('ma_nv', 5);
            $table->date('ngay_lam');
            $table->smallInteger('so_gio_lam');
            $table->boolean('vao_muon');
            $table->boolean('ve_som');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('cham_cong');

        parent::tearDown();
    }

    public function test_model_matches_the_active_attendance_contract(): void
    {
        $model = new ChamCong;

        self::assertSame('cham_cong', $model->getTable());
        self::assertSame('ma_cc', $model->getKeyName());
        self::assertFalse($model->usesTimestamps());
        self::assertSame([
            'ma_nv',
            'ngay_lam',
            'so_gio_lam',
            'vao_muon',
            've_som',
        ], $model->getFillable());
        self::assertSame('date', $model->getCasts()['ngay_lam']);
        self::assertSame('integer', $model->getCasts()['so_gio_lam']);
        self::assertSame('boolean', $model->getCasts()['vao_muon']);
        self::assertSame('boolean', $model->getCasts()['ve_som']);
    }

    public function test_delete_endpoint_removes_a_persisted_attendance_row(): void
    {
        $this->actingAsEmployeeWithPermissions([ChamCongPermission::Xoa]);

        $id = DB::table('cham_cong')->insertGetId([
            'ma_nv' => '00021',
            'ngay_lam' => '2026-09-17',
            'so_gio_lam' => 8,
            'vao_muon' => 0,
            've_som' => 0,
        ]);

        $this->deleteJson('/api/v1/cham-cong/'.$id)
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Xóa thành công',
            ]);

        self::assertDatabaseMissing('cham_cong', ['ma_cc' => $id]);
    }

    public function test_delete_endpoint_returns_a_safe_not_found_for_missing_id(): void
    {
        $this->actingAsEmployeeWithPermissions([ChamCongPermission::Xoa]);

        $this->deleteJson('/api/v1/cham-cong/9999')
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'Không tìm thấy bản ghi',
            ]);
    }
}
