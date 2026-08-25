<?php

namespace App\Support;

use App\Enums\NhanVienRole;

/**
 * Server-side row scope for department managers.
 *
 * The actor is passed explicitly so repositories and services never resolve
 * Auth implicitly. A malformed department-manager identity is fail-closed.
 */
final class NhanVienDepartmentScope
{
    public function departmentId(?object $actor): ?int
    {
        if (! $this->isDepartmentManager($actor)) {
            return null;
        }

        $department = data_get($actor, 'ma_pb');

        return is_int($department) && $department > 0
            ? $department
            : (is_string($department) && ctype_digit($department) && (int) $department > 0
                ? (int) $department
                : null);
    }

    public function isDepartmentManager(?object $actor): bool
    {
        return $actor !== null
            && (int) data_get($actor, 'ma_vt', 0) === NhanVienRole::DepartmentManager->value;
    }

    /** @param array<string, mixed> $filters */
    public function constrainFilters(array $filters, ?object $actor): array
    {
        if ($actor === null) {
            $filters['ma_pb'] = 0;

            return $filters;
        }

        if (! $this->isDepartmentManager($actor)) {
            return $filters;
        }

        $filters['ma_pb'] = $this->departmentId($actor) ?? 0;

        return $filters;
    }

    /** @param array{phong_ban?: array, chuc_vu?: array, trang_thai?: array} $lookups */
    public function constrainLookups(array $lookups, ?object $actor): array
    {
        if ($actor === null) {
            $lookups['phong_ban'] = [];

            return $lookups;
        }

        $department = $this->departmentId($actor);
        if (! $this->isDepartmentManager($actor)) {
            return $lookups;
        }

        $lookups['phong_ban'] = $department === null
            ? []
            : array_values(array_filter(
                $lookups['phong_ban'] ?? [],
                static fn (mixed $row): bool => (int) data_get($row, 'ma_pb', 0) === $department,
            ));

        return $lookups;
    }

    public function canView(?object $actor, ?object $target): bool
    {
        if ($actor === null || $target === null) {
            return false;
        }

        if (! $this->isDepartmentManager($actor)) {
            return true;
        }

        $department = $this->departmentId($actor);

        return $department !== null
            && (int) data_get($target, 'ma_pb', 0) === $department;
    }
}
