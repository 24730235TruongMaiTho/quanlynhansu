<?php

namespace Tests\Feature\Compatibility;

use App\Enums\ChamCongPermission;
use App\Services\ChamCongService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\Support\InteractsWithEmployeeModule;
use Tests\TestCase;

final class ChamCongBatchAuthorizationTest extends TestCase
{
    use InteractsWithEmployeeModule;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('nhan_vien', static function (Blueprint $table): void {
            $table->string('ma_nv', 5)->primary();
        });
        DB::table('nhan_vien')->insert(['ma_nv' => '00001']);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('nhan_vien');
        parent::tearDown();
    }

    public function test_batch_delete_semantics_require_delete_in_addition_to_insert_and_update(): void
    {
        $this->actingAsEmployeeWithPermissions([
            ChamCongPermission::Tao,
            ChamCongPermission::Sua,
        ]);

        $this->putJson('/api/v1/cham-cong/batch', $this->payload())
            ->assertForbidden();
    }

    public function test_actor_with_all_batch_permissions_reaches_service_boundary_without_live_mutation(): void
    {
        $this->actingAsEmployeeWithPermissions([
            ChamCongPermission::Tao,
            ChamCongPermission::Sua,
            ChamCongPermission::Xoa,
        ]);

        $this->mock(ChamCongService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('saveBatchAttendance')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message' => 'ok',
                    'data' => [],
                ]);
        });

        $this->putJson('/api/v1/cham-cong/batch', $this->payload())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'ma_nv' => '00001',
            'thang' => 9,
            'nam' => 2026,
            'rows' => [[
                'ma_cc' => null,
                'ngay_lam' => '2026-09-01',
                'so_gio_lam' => -1,
                'vao_muon' => 0,
                've_som' => 0,
            ]],
        ];
    }
}
