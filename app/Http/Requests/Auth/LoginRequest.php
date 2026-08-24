<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginRequest extends FormRequest
{
    public const GENERIC_ERROR = 'Thông tin đăng nhập không hợp lệ.';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dinh_danh' => ['required', 'string', 'max:100'],
            'mat_khau' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $identifier = trim((string) $this->input('dinh_danh', ''));

        if (str_contains($identifier, '@')) {
            $identifier = Str::lower($identifier);
        }

        $this->merge(['dinh_danh' => $identifier]);
    }

    public function ensureNotRateLimited(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            throw ValidationException::withMessages([
                'dinh_danh' => self::GENERIC_ERROR,
            ]);
        }
    }

    public function hitRateLimiter(): void
    {
        RateLimiter::hit($this->throttleKey(), 60);
    }

    public function clearRateLimiter(): void
    {
        RateLimiter::clear($this->throttleKey());
    }

    public function throttleKey(): string
    {
        $identifier = Str::lower(trim((string) $this->input('dinh_danh', '')));

        return hash('sha256', $identifier.'|'.$this->ip());
    }
}
