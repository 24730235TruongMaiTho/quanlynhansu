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
        $repository->shouldReceive('permissionsForActor')->once()->with('00001')->andReturn([
            $this->row(17, 'NhanVien.Read', 'NhanVien'),
            $this->row(9, 'PhongBan.Read', 'PhongBan'),
        ]);
        $service = new PermissionService($repository, new PermissionRegistry());

        $employee = $this->employee('00001');

        $this->assertTrue($service->allows($employee, NhanVienPermission::Xem));
        $this->assertTrue($service->allows($employee, 'NhanVien.Read'));
        $this->assertTrue($service->allows($employee, PhongBanPermission::Xem));
        $this->assertFalse($service->allows($employee, NhanVienPermission::Tao));
    }

    public function test_distinct_actors_have_independent_permission_sets(): void
    {
        $repository = Mockery::mock(PermissionRepositoryContract::class);
        $repository->shouldReceive('permissionsForActor')->once()->with('00001')->andReturn([
            $this->row(17, 'NhanVien.Read', 'NhanVien'),
        ]);
        $repository->shouldReceive('permissionsForActor')->once()->with('00002')->andReturn([
            $this->row(18, 'NhanVien.Insert', 'NhanVien'),
        ]);
        $service = new PermissionService($repository, new PermissionRegistry());

        $this->assertTrue($service->allows($this->employee('00001'), NhanVienPermission::Xem));
        $this->assertFalse($service->allows($this->employee('00001'), NhanVienPermission::Tao));
        $this->assertTrue($service->allows($this->employee('00002'), NhanVienPermission::Tao));
        $this->assertFalse($service->allows($this->employee('00002'), NhanVienPermission::Xem));
    }

    public function test_metadata_drift_does_not_grant_even_when_permission_id_matches(): void
    {
        $repository = Mockery::mock(PermissionRepositoryContract::class);
        $repository->shouldReceive('permissionsForActor')->once()->andReturn([
            $this->row(18, 'NhanVien.Insert', 'NhanVien'),
        ]);
        $service = new PermissionService($repository, new PermissionRegistry());

        $this->assertFalse($service->allows($this->employee('00001'), NhanVienPermission::Xem));
    }

    public function test_malformed_rows_fail_closed_without_granting_valid_rows(): void
    {
        $repository = Mockery::mock(PermissionRepositoryContract::class);
        $repository->shouldReceive('permissionsForActor')->once()->andReturn([
            $this->row(17, 'NhanVien.Read', 'NhanVien'),
            ['ma_quyen' => 18, 'ky_hieu_quyen' => null, 'module' => 'NhanVien'],
        ]);
        $service = new PermissionService($repository, new PermissionRegistry());

        $this->assertFalse($service->allows($this->employee('00001'), NhanVienPermission::Xem));
    }

    public function test_repository_failure_fails_closed(): void
    {
        $repository = Mockery::mock(PermissionRepositoryContract::class);
        $repository->shouldReceive('permissionsForActor')->once()->andThrow(new \RuntimeException('database unavailable'));
        $service = new PermissionService($repository, new PermissionRegistry());

        $this->assertFalse($service->allows($this->employee('00001'), NhanVienPermission::Xem));
    }

    public function test_unknown_ability_and_invalid_actor_do_not_access_repository(): void
    {
        $repository = Mockery::mock(PermissionRepositoryContract::class);
        $repository->shouldNotReceive('permissionsForActor');
        $service = new PermissionService($repository, new PermissionRegistry());

        $this->assertFalse($service->allows($this->employee('00001'), 'NhanVien.Read.Drift'));
        $this->assertFalse($service->allows($this->employee('not-an-employee'), NhanVienPermission::Xem));
    }

    public function test_registry_uses_the_canonical_database_symbols_and_modules(): void
    {
        $registry = new PermissionRegistry();

        $this->assertSame('NhanVien.Read', NhanVienPermission::Xem->value);
        $this->assertSame('NhanVien', NhanVienPermission::Xem->module());
        $this->assertSame(17, $registry->forAbility('NhanVien.Read')?->id());
        $this->assertSame('PhongBan', $registry->forAbility('PhongBan.Read')?->module());
        $this->assertNull($registry->forAbility('NhanVien.ResetPassword'));
        $this->assertNull($registry->forAbility('NhanVien.ResetPassword '));
        $this->assertNull($registry->forAbility('NhanVien.LegacyRead'));
    }

    public function test_module_action_and_visibility_require_registered_view_and_exact_action(): void
    {
        $repository = Mockery::mock(PermissionRepositoryContract::class);
        $repository->shouldReceive('permissionsForActor')->once()->with('00001')->andReturn([
            $this->row(17, 'NhanVien.Read', 'NhanVien'),
            $this->row(18, 'NhanVien.Insert', 'NhanVien'),
            $this->row(9, 'PhongBan.Read', 'PhongBan'),
        ]);
        $service = new PermissionService($repository, new PermissionRegistry());
        $employee = $this->employee('00001');

        $this->assertTrue($service->allowsModuleAction($employee, 'NhanVien', PermissionAction::View));
        $this->assertTrue($service->allowsModuleAction($employee, 'NhanVien', PermissionAction::Create));
        $this->assertTrue($service->canSeeModule($employee, 'PhongBan'));
        $this->assertFalse($service->allowsModuleAction($employee, 'PhongBan', PermissionAction::Create));
        $this->assertFalse($service->canSeeModule($employee, 'UnknownModule'));
    }

    public function test_action_parser_accepts_only_a_complete_known_suffix(): void
    {
        $this->assertSame(PermissionAction::View, PermissionAction::fromSymbol('NhanVien.Read'));
        $this->assertSame(PermissionAction::Delete, PermissionAction::fromSymbol('PhongBan.Delete'));
        $this->assertNull(PermissionAction::fromSymbol('VIEW'));
        $this->assertNull(PermissionAction::fromSymbol('.Read'));
        $this->assertNull(PermissionAction::fromSymbol('NhanVien.Nested.Read'));
        $this->assertNull(PermissionAction::fromSymbol('NhanVien.ReadExtra'));
    }

    public function test_duplicate_registered_ids_fail_closed(): void
    {
        $registry = new PermissionRegistry([DuplicatePermissionSet::class]);

        $this->assertSame([], $registry->all());
        $this->assertNull($registry->forAbility('Test.Read'));
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
            'ma_tt' => 1,
        ]);
    }
}

final class DuplicatePermissionSet implements PermissionDefinitionContract
{
    public static function cases(): array
    {
        return [
            new TestPermissionDefinition(900, 'Test.Read', 'Test', PermissionAction::View),
            new TestPermissionDefinition(900, 'Test.Insert', 'Test', PermissionAction::Create),
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
