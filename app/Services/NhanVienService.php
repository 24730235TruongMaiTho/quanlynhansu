<?php

namespace App\Services;

use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Enums\NhanVienRemovalAction;
use App\Enums\NhanVienRole;
use App\Enums\NhanVienStatus;
use App\Exceptions\NhanVienDomainException;
use App\Support\NhanVienAvatarPath;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NhanVienService implements NhanVienServiceContract
{
    private const PROFILE_FIELDS = [
        'ho_ten', 'ngay_sinh', 'gioi_tinh', 'sdt', 'email', 'ngay_vao_lam',
        'ma_pb', 'ma_cv', 'dan_toc', 'cccd', 'noi_cap_cccd', 'hoc_van', 'ma_tt',
    ];

    private const ADDRESS_FIELDS = [
        'dia_chi_cu_the', 'phuong_xa', 'quan_huyen', 'tinh_thanh',
    ];

    private const OWN_PROFILE_FIELDS = [
        'ho_ten', 'ngay_sinh', 'gioi_tinh', 'sdt', 'email',
        'dan_toc', 'cccd', 'noi_cap_cccd', 'hoc_van',
    ];

    public function __construct(
        private DatabaseManager $database,
        private NhanVienRepositoryContract $repository,
        private FilesystemManager $files,
        private Hasher $hasher,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function paginateForAttendance(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateAttendance($filters);
    }

    public function findOrFail(string $maNv): object
    {
        $employee = $this->repository->find($maNv);

        abort_if($employee === null, 404);

        return $employee;
    }

    public function create(array $validated): string
    {
        $profile = array_intersect_key($validated, array_flip(self::PROFILE_FIELDS));
        $address = array_intersect_key($validated, array_flip(self::ADDRESS_FIELDS));
        $plainPassword = 'nhom3@'.now(config('app.timezone'))->year;
        $passwordHash = $this->hasher->make($plainPassword);
        unset($plainPassword);

        $avatar = $validated['anh_dai_dien'] ?? null;
        $avatarPath = new NhanVienAvatarPath;
        $disk = null;
        $finalAvatarPath = null;

        if ($avatar instanceof UploadedFile) {
            [$disk, $finalAvatarPath] = $this->storeAvatar($avatar, $avatarPath);
        }

        $connection = $this->database->connection();

        try {
            $maNv = $connection->transaction(function () use (
                $profile,
                $address,
                $passwordHash,
                $finalAvatarPath,
            ): string {
                $maNv = $this->repository->create($profile, $passwordHash, $finalAvatarPath);
                $this->repository->upsertAddress($maNv, $address);

                return $maNv;
            });

            if (
                $disk instanceof FilesystemAdapter
                && $finalAvatarPath !== null
                && $connection->transactionLevel() > 0
            ) {
                // An outer transaction may still abort after this closure; compensate the new owned file.
                $connection->afterRollBack(function () use (
                    $disk,
                    $avatarPath,
                    $finalAvatarPath,
                ): void {
                    $this->deleteOwnedAvatarCandidates(
                        $disk,
                        $avatarPath,
                        [],
                        [$finalAvatarPath],
                    );
                });
            }

            return $maNv;
        } catch (Throwable $exception) {
            if ($disk instanceof FilesystemAdapter && $finalAvatarPath !== null) {
                $this->deleteOwnedAvatarCandidates($disk, $avatarPath, [], [$finalAvatarPath]);
            }

            throw $exception;
        }
    }

    /**
     * Update profile, address, and optional avatar metadata atomically on the default connection.
     * Filesystem compensation remains outside the database transaction boundary by design.
     */
    public function update(string $maNv, array $validated): object
    {
        $profile = array_intersect_key($validated, array_flip(self::PROFILE_FIELDS));
        $address = array_intersect_key($validated, array_flip(self::ADDRESS_FIELDS));
        $avatar = $validated['anh_dai_dien'] ?? null;
        $removeAvatar = in_array(
            $validated['xoa_anh_dai_dien'] ?? false,
            [true, 1, '1'],
            true,
        );
        $avatarPath = new NhanVienAvatarPath;
        $disk = null;
        $newAvatarPath = null;
        $oldAvatarPath = null;

        if ($avatar instanceof UploadedFile) {
            [$disk, $newAvatarPath] = $this->storeAvatar($avatar, $avatarPath);
        }

        $replaceAvatar = $newAvatarPath !== null || $removeAvatar;
        $connection = $this->database->connection();

        try {
            // Keep profile, address, avatar metadata, and the hydration read on one write transaction.
            $employee = $connection->transaction(function () use (
                $maNv,
                $profile,
                $address,
                $replaceAvatar,
                $newAvatarPath,
                &$oldAvatarPath,
            ): object {
                $this->repository->update($maNv, $profile);
                $this->repository->upsertAddress($maNv, $address);

                if ($replaceAvatar) {
                    $oldAvatarPath = $this->repository->replaceAvatarPath($maNv, $newAvatarPath);
                }

                $employee = $this->repository->find($maNv);
                if ($employee === null) {
                    throw new NhanVienDomainException(
                        'Không tìm thấy nhân viên.',
                        'NV_NOT_FOUND',
                    );
                }

                return $employee;
            });

            // Old files are external to the database; defer cleanup until the root commit succeeds.
            if ($replaceAvatar && $oldAvatarPath !== null && $oldAvatarPath !== $newAvatarPath) {
                $connection->afterCommit(function () use (
                    $maNv,
                    $oldAvatarPath,
                    $avatarPath,
                    $disk,
                ): void {
                    $this->deleteCommittedOldAvatar(
                        $maNv,
                        $oldAvatarPath,
                        $avatarPath,
                        $disk,
                    );
                });
            }

            if (
                $disk instanceof FilesystemAdapter
                && $newAvatarPath !== null
                && $connection->transactionLevel() > 0
            ) {
                // An outer transaction may still abort after this closure; compensate the new owned file.
                $connection->afterRollBack(function () use (
                    $disk,
                    $avatarPath,
                    $newAvatarPath,
                ): void {
                    $this->deleteOwnedAvatarCandidates(
                        $disk,
                        $avatarPath,
                        [],
                        [$newAvatarPath],
                    );
                });
            }
        } catch (Throwable $exception) {
            if ($disk instanceof FilesystemAdapter && $newAvatarPath !== null) {
                $this->deleteOwnedAvatarCandidates($disk, $avatarPath, [], [$newAvatarPath]);
            }

            throw $exception;
        }

        return $employee;
    }

    public function updateOwnProfile(string $maNv, array $validated): object
    {
        if (array_key_exists('ngay_sinh', $validated)) {
            $birthDate = $this->parseIsoDate($validated['ngay_sinh']);
            if ($birthDate === null) {
                throw new NhanVienDomainException(
                    'Ngày sinh không hợp lệ.',
                    'NV_PROFILE_BIRTH_DATE_INVALID',
                    'ngay_sinh',
                );
            }

            $current = $this->repository->find($maNv);
            if ($current === null) {
                throw new NhanVienDomainException('Không tìm thấy nhân viên.', 'NV_NOT_FOUND');
            }

            $startDate = $this->parseIsoDate($current->ngay_vao_lam ?? null);
            if ($startDate !== null && $birthDate->addYears(18)->isAfter($startDate)) {
                throw new NhanVienDomainException(
                    'Nhân viên phải đủ 18 tuổi tại ngày vào làm.',
                    'NV_PROFILE_BIRTH_DATE_TOO_YOUNG',
                    'ngay_sinh',
                );
            }
        }

        $allowed = array_merge(self::OWN_PROFILE_FIELDS, self::ADDRESS_FIELDS, [
            'anh_dai_dien', 'xoa_anh_dai_dien',
        ]);

        return $this->update($maNv, array_intersect_key($validated, array_flip($allowed)));
    }

    public function changeOwnPassword(string $maNv, string $currentPassword, string $newPassword): void
    {
        $employee = $this->repository->findAccountByIdentifier($maNv);
        if ($employee === null || ! $this->matchesPassword($currentPassword, (string) $employee->getAuthPassword())) {
            throw new NhanVienDomainException('Mật khẩu hiện tại không đúng.', 'NV_PROFILE_PASSWORD_INVALID', 'mat_khau_hien_tai');
        }

        if ($this->matchesPassword($newPassword, (string) $employee->getAuthPassword())) {
            throw new NhanVienDomainException('Mật khẩu mới phải khác mật khẩu hiện tại.', 'NV_PROFILE_PASSWORD_REUSED', 'mat_khau_moi');
        }

        $this->repository->rehashAuthenticatedPassword(
            $maNv,
            (string) $employee->getAuthPassword(),
            $this->hasher->make($newPassword),
        );
    }

    public function resetPassword(string $maNv, string $actorMaNv): void
    {
        if ($maNv === $actorMaNv) {
            throw new NhanVienDomainException('Không tìm thấy nhân viên.', 'NV_RESET_SELF_FORBIDDEN');
        }

        $actor = $this->repository->find($actorMaNv);
        $target = $this->repository->find($maNv);

        if ($target === null || NhanVienStatus::isTerminalValue((int) ($target->ma_tt ?? 0))) {
            throw new NhanVienDomainException('Không tìm thấy nhân viên.', 'NV_RESET_NOT_FOUND');
        }

        $actorRole = (int) ($actor->ma_vt ?? 0);
        if ((int) ($target->ma_vt ?? 0) === NhanVienRole::SuperAdmin->value
            && $actorRole !== NhanVienRole::SuperAdmin->value) {
            throw new NhanVienDomainException('Không tìm thấy nhân viên.', 'NV_RESET_PROTECTED_TARGET');
        }

        if ($actorRole === NhanVienRole::DepartmentManager->value
            && (int) ($actor->ma_pb ?? 0) !== (int) ($target->ma_pb ?? 0)) {
            throw new NhanVienDomainException('Không tìm thấy nhân viên.', 'NV_RESET_SCOPE_FORBIDDEN');
        }

        $plainPassword = 'nhom3@'.now(config('app.timezone'))->year;
        $hash = $this->hasher->make($plainPassword);
        unset($plainPassword);

        $this->repository->resetPassword($maNv, $hash);
    }

    private function matchesPassword(string $plainText, string $storedHash): bool
    {
        $algorithm = password_get_info($storedHash)['algo'];
        if ($algorithm !== null && $algorithm !== 0) {
            return $this->hasher->check($plainText, $storedHash);
        }

        return hash_equals(strtolower($storedHash), strtolower(hash('sha256', $plainText)));
    }

    private function parseIsoDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
            $errors = CarbonImmutable::getLastErrors();
        } catch (Throwable) {
            return null;
        }

        if ($date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }

    public function removeOrTerminate(string $maNv): NhanVienRemovalAction
    {
        $connection = $this->database->connection();
        $businessDate = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        // Keep the repository operation on the write transaction that owns its row lock and outcome.
        $result = $connection->transaction(
            fn (): array => $this->repository->removeOrTerminate($maNv, $businessDate),
        );
        $action = $result['action'] ?? null;

        if (! $action instanceof NhanVienRemovalAction) {
            throw new NhanVienDomainException(
                'Không thể xử lý yêu cầu nhân viên. Vui lòng thử lại.',
                'NV_DATABASE_ERROR',
            );
        }

        if ($action === NhanVienRemovalAction::Deleted && is_string($result['avatar_path'] ?? null)) {
            $cleanup = function () use ($maNv, $result): void {
                $this->deleteRemovedAvatar($maNv, $result['avatar_path']);
            };

            // File cleanup follows the root commit so an outer rollback cannot lose the old avatar.
            if ($connection->transactionLevel() > 0) {
                $connection->afterCommit($cleanup);
            } else {
                $cleanup();
            }
        }

        return $action;
    }

    public function lookups(): array
    {
        return $this->repository->lookups();
    }

    /**
     * @return array{FilesystemAdapter, string}
     */
    private function storeAvatar(UploadedFile $avatar, NhanVienAvatarPath $paths): array
    {
        $disk = $this->files->disk('public');
        $temporaryPath = null;
        $finalPath = null;
        $storedPath = null;

        try {
            $extension = strtolower($avatar->extension());
            $temporaryPath = $paths->newTemporaryPath($extension);
            $finalPath = $paths->newPath($extension);
            $storedPath = $disk->putFileAs(
                dirname($temporaryPath),
                $avatar,
                basename($temporaryPath),
            );

            if (! is_string($storedPath)) {
                throw new NhanVienDomainException(
                    'Không thể lưu ảnh đại diện. Vui lòng thử lại.',
                    'NV_AVATAR_WRITE_FAILED',
                    'anh_dai_dien',
                );
            }

            try {
                $ownedStoredPath = $paths->assertOwnedTemporaryFile($storedPath);
            } catch (Throwable) {
                $ownedStoredPath = null;
            }

            if ($ownedStoredPath !== $temporaryPath) {
                throw new NhanVienDomainException(
                    'Không thể lưu ảnh đại diện. Vui lòng thử lại.',
                    'NV_AVATAR_WRITE_FAILED',
                    'anh_dai_dien',
                );
            }

            try {
                $moved = $disk->move($temporaryPath, $finalPath);
            } catch (Throwable) {
                throw new NhanVienDomainException(
                    'Không thể lưu ảnh đại diện. Vui lòng thử lại.',
                    'NV_AVATAR_MOVE_FAILED',
                    'anh_dai_dien',
                );
            }

            if (! $moved) {
                throw new NhanVienDomainException(
                    'Không thể lưu ảnh đại diện. Vui lòng thử lại.',
                    'NV_AVATAR_MOVE_FAILED',
                    'anh_dai_dien',
                );
            }

            return [$disk, $finalPath];
        } catch (Throwable $exception) {
            $this->deleteOwnedAvatarCandidates(
                $disk,
                $paths,
                [$storedPath, $temporaryPath],
                [$finalPath],
            );

            if ($exception instanceof NhanVienDomainException) {
                throw $exception;
            }

            throw new NhanVienDomainException(
                'Không thể lưu ảnh đại diện. Vui lòng thử lại.',
                'NV_AVATAR_WRITE_FAILED',
                'anh_dai_dien',
            );
        }
    }

    /**
     * Cleanup is deliberately best-effort so storage failures never replace the
     * database or domain exception which triggered compensation.
     *
     * @param  array<int, mixed>  $temporaryCandidates
     * @param  array<int, mixed>  $finalCandidates
     */
    private function deleteOwnedAvatarCandidates(
        FilesystemAdapter $disk,
        NhanVienAvatarPath $paths,
        array $temporaryCandidates,
        array $finalCandidates,
    ): void {
        $owned = [];

        foreach ($temporaryCandidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            try {
                $path = $paths->assertOwnedTemporaryFile($candidate);
                if ($path !== null) {
                    $owned[$path] = true;
                }
            } catch (Throwable) {
                // Never compensate a path that is not proven to be ours.
            }
        }

        foreach ($finalCandidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            try {
                $path = $paths->assertOwnedFile($candidate);
                if ($path !== null) {
                    $owned[$path] = true;
                }
            } catch (Throwable) {
                // Never compensate a path that is not proven to be ours.
            }
        }

        foreach (array_keys($owned) as $path) {
            try {
                $disk->delete($path);
            } catch (Throwable) {
                // Cleanup must remain non-throwing and must not expose paths/PII.
            }
        }
    }

    private function deleteCommittedOldAvatar(
        string $maNv,
        string $oldPath,
        NhanVienAvatarPath $paths,
        ?FilesystemAdapter $disk,
    ): void {
        try {
            $ownedPath = $paths->assertOwnedFile($oldPath);
        } catch (Throwable) {
            $this->safeAvatarWarning('employee_avatar_cleanup_skipped', [
                'ma_nv' => $maNv,
                'reason' => 'UNOWNED_PATH',
            ]);

            return;
        }

        if ($ownedPath === null) {
            return;
        }

        try {
            $disk ??= $this->files->disk('public');
            if (! $disk->delete($ownedPath)) {
                $this->safeAvatarWarning('employee_avatar_cleanup_failed', [
                    'ma_nv' => $maNv,
                    'reason' => 'DELETE_FALSE',
                ]);
            }
        } catch (Throwable $exception) {
            $this->safeAvatarWarning('employee_avatar_cleanup_failed', [
                'ma_nv' => $maNv,
                'reason' => $exception::class,
            ]);
        }
    }

    private function deleteRemovedAvatar(string $maNv, string $oldPath): void
    {
        try {
            $paths = new NhanVienAvatarPath;
            $ownedPath = $paths->assertOwnedFile($oldPath);
        } catch (Throwable) {
            $this->safeAvatarWarning('employee_avatar_cleanup_skipped', [
                'ma_nv' => $maNv,
                'reason' => 'UNOWNED_PATH',
            ]);

            return;
        }

        if ($ownedPath === null) {
            return;
        }

        try {
            $disk = $this->files->disk('public');
            if (! $disk->delete($ownedPath)) {
                $this->safeAvatarWarning('employee_avatar_cleanup_failed', [
                    'ma_nv' => $maNv,
                    'reason' => 'DELETE_FALSE',
                ]);
            }
        } catch (Throwable $exception) {
            $this->safeAvatarWarning('employee_avatar_cleanup_failed', [
                'ma_nv' => $maNv,
                'reason' => $exception::class,
            ]);
        }
    }

    /**
     * Logging is observability only and must never turn committed work into an
     * HTTP failure.
     *
     * @param  array<string, string>  $context
     */
    private function safeAvatarWarning(string $event, array $context): void
    {
        try {
            Log::warning($event, $context);
        } catch (Throwable) {
            // The database outcome and response must not depend on the logger.
        }
    }
}
