<?php

namespace App\Services;

use App\Contracts\HopDongRepositoryContract;
use App\Contracts\HopDongServiceContract;
use App\Exceptions\HopDongDomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use DateTimeImmutable;
use Throwable;

final class HopDongService implements HopDongServiceContract
{
    public function __construct(private HopDongRepositoryContract $repository) {}
    public function paginate(array $filters): LengthAwarePaginator { return $this->repository->paginate($filters, (int) ($filters['per_page'] ?? 20), max(1, (int) config('hopdong.expiring_warning_days', 30))); }
    public function findOrFail(int $maHd): object { return $this->repository->find($maHd) ?? throw new HopDongDomainException('Không tìm thấy hợp đồng.', 'HD_NOT_FOUND'); }
    public function formOptions(): array { return ['employees' => $this->repository->employees(), 'types' => $this->repository->types()]; }
    public function create(array $data): int
    {
        $data = $this->normalizeAndValidate($data);

        try {
            return $this->repository->create($data);
        } catch (HopDongDomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw new HopDongDomainException(
                'Không thể lưu hợp đồng lúc này.',
                'HD_PERSIST_FAILED',
            );
        }
    }

    public function update(int $maHd, array $data): void
    {
        if ($this->repository->find($maHd) === null) {
            throw new HopDongDomainException('Không tìm thấy hợp đồng.', 'HD_NOT_FOUND');
        }

        $data = $this->normalizeAndValidate($data);

        try {
            $this->repository->update($maHd, $data);
        } catch (HopDongDomainException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw new HopDongDomainException(
                'Không thể lưu hợp đồng lúc này.',
                'HD_PERSIST_FAILED',
            );
        }
    }
    public function delete(int $maHd): void { $this->repository->delete($maHd); }

    /** @param array<string, mixed> $data */
    private function normalizeAndValidate(array $data): array
    {
        $maLhd = (int) ($data['ma_lhd'] ?? 0);

        try {
            $type = $this->repository->findType($maLhd);
        } catch (Throwable $exception) {
            report($exception);
            throw new HopDongDomainException(
                'Không thể lưu hợp đồng lúc này.',
                'HD_PERSIST_FAILED',
            );
        }

        if ($type === null) {
            throw new HopDongDomainException(
                'Loại hợp đồng không tồn tại.',
                'HD_TYPE_NOT_FOUND',
                'ma_lhd',
            );
        }

        $salary = $data['luong_co_ban'] ?? null;
        if (is_string($salary) && preg_match('/\A[0-9]{1,3}(?:\.[0-9]{3})+\z/', $salary) === 1) {
            $salary = str_replace('.', '', $salary);
        }
        if (! is_int($salary) && ! (is_string($salary) && preg_match('/\A[0-9]+\z/', $salary) === 1)) {
            throw new HopDongDomainException(
                'Lương cơ bản không hợp lệ.',
                'HD_SALARY_INVALID',
                'luong_co_ban',
            );
        }
        $salary = (int) $salary;
        if ($salary < 0 || $salary > 999999999999999999) {
            throw new HopDongDomainException(
                'Lương cơ bản không hợp lệ.',
                'HD_SALARY_INVALID',
                'luong_co_ban',
            );
        }
        $data['luong_co_ban'] = $salary;

        if ($maLhd === 1) {
            $data['ngay_het_han'] = null;
            return $data;
        }

        $signed = $this->parseIsoDate($data['ngay_ky'] ?? null);
        $expiry = $this->parseIsoDate($data['ngay_het_han'] ?? null);
        if ($signed === null || $expiry === null || $expiry <= $signed) {
            throw new HopDongDomainException(
                'Ngày hết hạn phải sau hoặc bằng ngày ký.',
                'HD_EXPIRY_INVALID',
                'ngay_het_han',
            );
        }

        return $data;
    }

    private function parseIsoDate(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value
            ? $date
            : null;
    }
}
