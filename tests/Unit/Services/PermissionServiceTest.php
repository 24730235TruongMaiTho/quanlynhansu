<?php

namespace Tests\Unit\Services;

use App\Authorization\PermissionRegistry;
use App\Contracts\PermissionDefinitionContract;
use App\Contracts\PermissionRepositoryContract;
use App\Enums\NhanVienPermission;
use App\Enums\PermissionAction;
use App\Enums\PhongBanPermission;
use App\Models\NhanVien;
use App\Services\PermissionService;
use Mockery;
use Tests\TestCase;

final class PermissionServiceTest extends TestCase
{
    public function test_permissions_are_cached_per_actor_and_require_exact_metadata(): void
    {
        $repository = Mockery::mock(PermissionRepositoryContract::class);
        $repository->shouldReceive('permissionsForActor')->once()->with('NV001')->andReturn([
            $this->row(101, 'NV_VIEW', 'NhanVien'),
            $this->row(201, 'PB_VIEW', 'PhongBan'),
        ]);
        $service = new PermissionService($repository, new PermissionRegistry());

        $employee = $this->employee('NV001');

        $this->assertTrue($service->allows($employee, NhanVienPermission::Xem));
        $this->assertTrue($service->allows($employee, 'NV_VIEW'));
        $this->assertTrue($service->allows($employee, PhongBanPermission::Xem));
        $this->assertFalse($service->allows($employee, NhanVienPermission::Tao));
    }

    public function test_distinct_actors_have_independent_permission_sets(): void
    {
        $repository = Mockery::mock(PermissionRepositoryContract::class);
        $repository->shouldReceive('permissionsForActor')->once()->with('NV001')->andReturn([
            $this->row(101, 'NV_VIEW', 'NhanVien'),
        ]);
        $repository->shouldReceive('permissionsForActor')->once()->with('NV002')->andReturn([
            $this->row(102, 'NV_CREATE', 'NhanVien'),
        ]);
        $service = new PermissionService($repository, new PermissionRegistry());

        $this->assertTrue($service->allows($this->employee('NV001'), NhanVienPermission::Xem));
        $this->assertFalse($service->allows($this->employee('NV001'), NhanVienPermission::Tao));
        $this->assertTrue($service->allows($this->employee('NV002'), NhanVienPermission::Tao));
        $this->assertFalse($service->allows($this->employee('NV002'), NhanVienPermission::Xem));
    }

    public function test_metadata_drift_does_not_grant_even_when_permission_id_matches(): void
    {
        $repository = Mockery::mock(PermissionRepositoryContract::class);
        $repository->shouldReceive('permissionsForActor')->once()->andReturn([
            $this->row(101, 'NV_CREATE', 'NhanVien'),
        ]);
        $service = new PermissionService($repository, new PermissionRegistry());

        $this->assertFalse($service->allows($this->employee('NV001'), NhanVienPermission::Xem));
    }

    public function test_malformed_rows_fail_closed_without_granting_valid_rows(): void
    {
        $repository = Mockery::mock(PermissionRepositoryContract::class);
        $repository->shouldReceive('permissionsForActor')->once()->andReturn([
            $this->row(101, 'NV_VIEW', 'NhanVien'),
            ['ma_quyen' => 102, 'ky_hieu_quyen' => null, 'module' => 'NhanVien'],
        ]);
        $service = new PermissionService($repository, new PermissionRegistry());

        $this->assertFalse($service->allows($this->employee('NV001'), NhanVienPermission::Xem));
    }

    public function test_repository_failure_fails_closed(): void
    {
        $repository = Mockery::mock(PermissionRepositoryContract::class);
        $repository->shouldReceive('permissionsForActor')->once()->andThrow(new \RuntimeException('database unavailable'));
        $service = new PermissionService($repository, new PermissionRegistry());

        $this->assertFalse($service->allows($this->employee('NV001'), NhanVienPermission::Xem));
    }

    public function test_unknown_ability_and_invalid_actor_do_not_access_repository(): void
    {
        $repository = Mockery::mock(PermissionRepositoryContract::class);
        $repository->shouldNotReceive('permissionsForActor');
        $service = new PermissionService($repository, new PermissionRegistry());

        $this->assertFalse($service->allows($this->employee('NV001'), 'NV_VIEW_DRIFT'));
        $this->assertFalse($service->allows($this->employee('not-an-employee'), NhanVienPermission::Xem));
    }

    public function test_registry_uses_the_canonical_database_symbols_and_modules(): void
    {
        $registry = new PermissionRegistry();

        $this->assertSame('NV_VIEW', NhanVienPermission::Xem->value);
        $this->assertSame('NhanVien', NhanVienPermission::Xem->module());
        $this->assertSame(101, $registry->forAbility('NV_VIEW')?->id());
        $this->assertSame('PhongBan', $registry->forAbility('PB_VIEW')?->module());
        $this->assertNull($registry->forAbility('NV_RESET_PASSWORD')?->action());
        $this->assertNull($registry->forAbility('NV_RESET_PASSWORD '));
        $this->assertNull($registry->forAbility('NV_LEGACY_VIEW'));
    }

    public function test_module_action_and_visibility_require_registered_view_and_exact_action(): void
    {
        $repository = Mockery::mock(PermissionRepositoryContract::class);
        $repository->shouldReceive('permissionsForActor')->once()->with('NV001')->andReturn([
            $this->row(101, 'NV_VIEW', 'NhanVien'),
            $this->row(102, 'NV_CREATE', 'NhanVien'),
            $this->row(201, 'PB_VIEW', 'PhongBan'),
        ]);
        $service = new PermissionService($repository, new PermissionRegistry());
        $employee = $this->employee('NV001');

        $this->assertTrue($service->allowsModuleAction($employee, 'NhanVien', PermissionAction::View));
        $this->assertTrue($service->allowsModuleAction($employee, 'NhanVien', PermissionAction::Create));
        $this->assertTrue($service->canSeeModule($employee, 'PhongBan'));
        $this->assertFalse($service->allowsModuleAction($employee, 'PhongBan', PermissionAction::Create));
        $this->assertFalse($service->canSeeModule($employee, 'UnknownModule'));
    }

    public function test_action_parser_accepts_only_a_complete_known_suffix(): void
    {
        $this->assertSame(PermissionAction::View, PermissionAction::fromSymbol('NV_VIEW'));
        $this->assertSame(PermissionAction::Delete, PermissionAction::fromSymbol('PB_DELETE'));
        $this->assertNull(PermissionAction::fromSymbol('VIEW'));
        $this->assertNull(PermissionAction::fromSymbol('NV_VIEW_EXTRA'));
    }

    public function test_duplicate_registered_ids_fail_closed(): void
    {
        $registry = new PermissionRegistry([DuplicatePermissionSet::class]);

        $this->assertSame([], $registry->all());
        $this->assertNull($registry->forAbility('TEST_VIEW'));
        $this->assertNull($registry->forModuleAction('Test', PermissionAction::View));
    }

    /** @return array{ma_quyen: int, ky_hieu_quyen: string, module: string} */
    private function row(int $id, string $symbol, string $module): array
    {
        return [
            'ma_quyen' => $id,
            'ky_hieu_quyen' => $symbol,
            'module' => $module,
        ];
    }

    private function employee(string $maNv): NhanVien
    {
        return NhanVien::fromAuthRow((object) [
            'ma_nv' => $maNv,
            'ho_ten' => 'Nguyễn An',
            'email' => $maNv.'@example.test',
            'mat_khau' => 'hash',
            'ma_vt' => 5,
            'ma_tt' => 2,
        ]);
    }
}

final class DuplicatePermissionSet implements PermissionDefinitionContract
{
    public static function cases(): array
    {
        return [
            new TestPermissionDefinition(900, 'TEST_VIEW', 'Test', PermissionAction::View),
            new TestPermissionDefinition(900, 'TEST_CREATE', 'Test', PermissionAction::Create),
        ];
    }

    public function id(): int { return 0; }

    public function symbol(): string { return ''; }

    public function module(): string { return ''; }

    public function action(): ?PermissionAction { return null; }
}

final class TestPermissionDefinition implements PermissionDefinitionContract
{
    public function __construct(
        private int $id,
        private string $symbol,
        private string $module,
        private ?PermissionAction $action,
    ) {}

    public function id(): int { return $this->id; }

    public function symbol(): string { return $this->symbol; }

    public function module(): string { return $this->module; }

    public function action(): ?PermissionAction { return $this->action; }
}
