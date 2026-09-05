<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListTaiKhoanRequest extends FormRequest
{
    private const PAGE_SIZES = [10, 20, 50];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $keyword = $this->input('tu_khoa');
        $page = filter_var($this->input('page', 1), FILTER_VALIDATE_INT);
        $perPage = filter_var($this->input('per_page', 10), FILTER_VALIDATE_INT);

        $normalizedKeyword = is_string($keyword) ? trim($keyword) : $keyword;

        $this->merge([
            'tu_khoa' => $normalizedKeyword === '' ? null : $normalizedKeyword,
            'page' => $page !== false && $page > 0 ? $page : 1,
            'per_page' => in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 10,
        ]);
    }

    public function rules(): array
    {
        return [
            'tu_khoa' => ['nullable', 'string', 'max:100'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', Rule::in(self::PAGE_SIZES)],
        ];
    }

    /** @return array{tu_khoa: ?string, page: int, per_page: int} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'tu_khoa' => $validated['tu_khoa'] ?? null,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 10),
        ];
    }
}
