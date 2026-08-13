<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

class Du18TuoiTaiNgayVaoLam implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ngaySinh = $this->parseIsoDate($value);
        $ngayVaoLam = $this->parseIsoDate($this->data['ngay_vao_lam'] ?? null);

        if ($ngaySinh === null || $ngayVaoLam === null) {
            return;
        }

        if ($ngaySinh->addYears(18)->isAfter($ngayVaoLam)) {
            $fail('Nhân viên phải đủ 18 tuổi tại ngày vào làm.');
        }
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
}
