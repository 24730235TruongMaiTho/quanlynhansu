<?php

namespace App\Support;

use App\Enums\NhanVienRole;
use App\Models\NhanVien;

/**
 * Xác định phạm vi dữ liệu Nhân viên ở ranh giới HTTP.
 *
 * Repository vẫn nhận bộ lọc/truy vấn thuần dữ liệu; thành phần này là nơi duy
 * nhất áp chính sách Trưởng phòng theo phòng ban của người thao tác hiện tại.
 */
final class NhanVienScope
{
    public function isDepartmentManager(?object $actor): bool
    {
        return $actor instanceof NhanVien
            && (int) $actor->getAttribute('ma_vt') === NhanVienRole::DepartmentManager->value;
    }

    public function departmentId(?object $actor): ?int
    {
        if (! $this->isDepartmentManager($actor)) {
            return null;
        }

        $department = $actor->getAttribute('ma_pb');
        if (is_int($department) && $department > 0) {
            return $department;
        }

        if (is_string($department) && ctype_digit($department) && (int) $department > 0) {
            return (int) $department;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>|null Trả về null nếu người thao tác không có phạm vi hợp lệ.
     */
    public function filtersFor(?object $actor, array $filters): ?array
    {
        if (! $this->isDepartmentManager($actor)) {
            return $filters;
        }

        $department = $this->departmentId($actor);
        if ($department === null) {
            return null;
        }

        $filters['ma_pb'] = $department;

        return $filters;
    }

    public function canAccess(?object $actor, ?object $employee): bool
    {
        if (! $actor instanceof NhanVien || $employee === null) {
            return false;
        }

        if (! $this->isDepartmentManager($actor)) {
            return true;
        }

        $department = $this->departmentId($actor);
        $targetDepartment = $employee->ma_pb ?? null;

        return $department !== null
            && ((is_int($targetDepartment) && $targetDepartment > 0)
                || (is_string($targetDepartment) && ctype_digit($targetDepartment) && (int) $targetDepartment > 0))
            && (int) $targetDepartment === $department;
    }

    /**
     * Giới hạn danh mục phòng ban vào phòng ban riêng của người thao tác.
     *
     * @param  array{phong_ban?: array, chuc_vu?: array, trang_thai?: array}  $lookups
     * @return array{phong_ban?: array, chuc_vu?: array, trang_thai?: array}
     */
    public function lookupsFor(?object $actor, array $lookups): array
    {
        if (! $this->isDepartmentManager($actor)) {
            return $lookups;
        }

        $department = $this->departmentId($actor);
        if ($department === null) {
            return [];
        }

        $lookups['phong_ban'] = array_values(array_filter(
            $lookups['phong_ban'] ?? [],
            static fn (mixed $item): bool => (int) data_get($item, 'ma_pb', 0) === $department,
        ));

        return $lookups;
    }
}
