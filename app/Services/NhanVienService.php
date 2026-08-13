<?php

namespace App\Services;

use App\Contracts\NhanVienRepositoryContract;
use App\Contracts\NhanVienServiceContract;
use App\Exceptions\NhanVienDomainException;
use App\Support\NhanVienAvatarPath;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
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

        try {
            return $this->database->connection()->transaction(function () use (
                $profile,
                $address,
                $passwordHash,
                $finalAvatarPath,
            ): string {
                $maNv = $this->repository->create($profile, $passwordHash, $finalAvatarPath);
                $this->repository->upsertAddress($maNv, $address);

                return $maNv;
            });
        } catch (Throwable $exception) {
            if ($disk instanceof FilesystemAdapter && $finalAvatarPath !== null) {
                $disk->delete($avatarPath->assertOwnedFile($finalAvatarPath));
            }

            throw $exception;
        }
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

        try {
            $extension = strtolower($avatar->extension());
            $temporaryPath = $paths->newTemporaryPath($extension);
            $finalPath = $paths->newPath($extension);
            $storedPath = $disk->putFileAs(
                dirname($temporaryPath),
                $avatar,
                basename($temporaryPath),
            );

            if (! is_string($storedPath)
                || $paths->assertOwnedTemporaryFile($storedPath) !== $temporaryPath) {
                throw new NhanVienDomainException(
                    'Không thể lưu ảnh đại diện. Vui lòng thử lại.',
                    'NV_AVATAR_WRITE_FAILED',
                    'anh_dai_dien',
                );
            }

            if (! $disk->move($temporaryPath, $finalPath)) {
                throw new NhanVienDomainException(
                    'Không thể lưu ảnh đại diện. Vui lòng thử lại.',
                    'NV_AVATAR_MOVE_FAILED',
                    'anh_dai_dien',
                );
            }

            $temporaryPath = null;

            return [$disk, $finalPath];
        } catch (Throwable $exception) {
            if (isset($temporaryPath) && is_string($temporaryPath)) {
                $disk->delete($paths->assertOwnedTemporaryFile($temporaryPath));
            }

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
}
