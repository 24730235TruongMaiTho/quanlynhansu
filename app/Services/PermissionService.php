<?php

namespace App\Services;

use App\Contracts\PermissionDefinitionContract;
use App\Contracts\PermissionRegistryContract;
use App\Contracts\PermissionRepositoryContract;
use App\Enums\PermissionAction;
use App\Models\NhanVien;
use Throwable;

class PermissionService
{
    /** @var array<string, ?array<string, true>> */
    private array $permissionSets = [];

    public function __construct(
        private PermissionRepositoryContract $repository,
        private PermissionRegistryContract $registry,
    ) {}

    public function allows(NhanVien $employee, PermissionDefinitionContract|string $permission): bool
    {
        $definition = $this->resolveDefinition($permission);
        $maNv = $employee->getAuthIdentifier();

        if ($definition === null || ! is_string($maNv) || preg_match('/\A[0-9]{5}\z/', $maNv) !== 1) {
            return false;
        }

        if (! array_key_exists($maNv, $this->permissionSets)) {
            $this->permissionSets[$maNv] = $this->loadPermissionSet($maNv);
        }

        $permissionSet = $this->permissionSets[$maNv];
        $key = $this->key($definition->id(), $definition->symbol(), $definition->module());

        return $permissionSet !== null && isset($permissionSet[$key]);
    }

    public function allowsModuleAction(NhanVien $employee, string $module, PermissionAction $action): bool
    {
        $definition = $this->registry->forModuleAction($module, $action);

        return $definition !== null && $this->allows($employee, $definition);
    }

    public function canSeeModule(NhanVien $employee, string $module): bool
    {
        return $this->allowsModuleAction($employee, $module, PermissionAction::View);
    }

    private function resolveDefinition(PermissionDefinitionContract|string $permission): ?PermissionDefinitionContract
    {
        if ($permission instanceof PermissionDefinitionContract) {
            $canonical = $this->registry->forAbility($permission->symbol());

            return $canonical !== null
                && $canonical->id() === $permission->id()
                && $canonical->module() === $permission->module()
                ? $canonical
                : null;
        }

        return $this->registry->forAbility($permission);
    }

    /** @return array<string, true>|null */
    private function loadPermissionSet(string $maNv): ?array
    {
        try {
            $rows = $this->repository->permissionsForActor($maNv);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($rows)) {
            return null;
        }

        $set = [];
        foreach ($rows as $row) {
            $normalized = $this->normalizeRow($row);
            if ($normalized === null) {
                return null;
            }

            [$id, $symbol, $module] = $normalized;
            if ($this->registry->forAbility($symbol)?->id() === $id
                && $this->registry->forAbility($symbol)?->module() === $module) {
                $set[$this->key($id, $symbol, $module)] = true;
            }
        }

        return $set;
    }

    /** @return array{0: int, 1: string, 2: string}|null */
    private function normalizeRow(mixed $row): ?array
    {
        $id = is_array($row) ? ($row['ma_quyen'] ?? null) : (is_object($row) ? ($row->ma_quyen ?? null) : null);
        $symbol = is_array($row) ? ($row['ky_hieu_quyen'] ?? null) : (is_object($row) ? ($row->ky_hieu_quyen ?? null) : null);
        $module = is_array($row) ? ($row['module'] ?? null) : (is_object($row) ? ($row->module ?? null) : null);

        if (is_string($id) && ctype_digit($id)) {
            $id = (int) $id;
        }

        if (! is_int($id) || $id < 1 || ! is_string($symbol) || $symbol === '' || ! is_string($module) || $module === '') {
            return null;
        }

        return [$id, $symbol, $module];
    }

    private function key(int $id, string $symbol, string $module): string
    {
        return $id.'|'.$symbol.'|'.$module;
    }
}
