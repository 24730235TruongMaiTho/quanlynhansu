<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListTaiKhoanRequest extends FormRequest
{
    private bool $sortWasProvided = false;

    private const PAGE_SIZES = [10, 20, 50];
    private const SORT_COLUMNS = ['ma_nv', 'ho_ten', 'email', 'ten_vt'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sortWasProvided = $this->has('sort') || $this->has('direction');
        $keyword = $this->input('tu_khoa');
        $page = filter_var($this->input('page', 1), FILTER_VALIDATE_INT);
        $perPage = filter_var($this->input('per_page', 10), FILTER_VALIDATE_INT);

        $normalizedKeyword = is_string($keyword) ? trim($keyword) : $keyword;

        $this->merge([
            'tu_khoa' => $normalizedKeyword === '' ? null : $normalizedKeyword,
            'page' => $page !== false && $page > 0 ? $page : 1,
            'per_page' => in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 10,
            'sort' => strtolower(trim((string) $this->input('sort', 'ma_nv'))) ?: 'ma_nv',
            'direction' => strtolower(trim((string) $this->input('direction', 'desc'))) ?: 'desc',
        ]);
    }

    public function rules(): array
    {
        return [
            'tu_khoa' => ['nullable', 'string', 'max:100'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', Rule::in(self::PAGE_SIZES)],
            'sort' => ['required', 'string', Rule::in(self::SORT_COLUMNS)],
            'direction' => ['required', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /** @return array{tu_khoa: ?string, page: int, per_page: int, sort: string, direction: string} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'tu_khoa' => $validated['tu_khoa'] ?? null,
            'page' => (int) ($validated['page'] ?? 1),
            'per_page' => (int) ($validated['per_page'] ?? 10),
            'sort' => $validated['sort'] ?? 'ma_nv',
            'direction' => $validated['direction'] ?? 'desc',
        ];
    }

    public function sortWasProvided(): bool
    {
        return $this->sortWasProvided;
    }
}
