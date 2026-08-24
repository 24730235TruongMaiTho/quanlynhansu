<?php

namespace App\Contracts;

use App\Enums\NhanVienRemovalAction;
use App\Models\NhanVien;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;

interface NhanVienRepositoryContract
{
    public function paginate(array $filters): LengthAwarePaginator;

    public function paginateAttendance(array $filters): LengthAwarePaginator;

    public function find(string $maNv): ?object;

    public function create(array $profile, string $passwordHash, ?string $avatarPath): string;

    public function update(string $maNv, array $profile): void;

    public function replaceAvatarPath(string $maNv, ?string $newPath): ?string;

    public function upsertAddress(string $maNv, array $address): void;

    /**
     * @return array{action: NhanVienRemovalAction, avatar_path: ?string}
     */
    public function removeOrTerminate(string $maNv, CarbonImmutable $date): array;

    public function resetPasswordHash(string $maNv, string $hash): void;

    public function rehashAuthenticatedPassword(string $maNv, string $currentHash, string $newHash): void;

    public function findAccountByIdentifier(string $identifier): ?NhanVien;

    /** @return list<int> */
    public function permissionIds(string $maNv): array;

    /** @internal Bootstrap-only role assignment; never expose through web flows. */
    public function assignRoleForBootstrap(string $maNv, int $maVt): void;

    /**
     * @return array{phong_ban: array, chuc_vu: array, trang_thai: array}
     */
    public function lookups(): array;
}
