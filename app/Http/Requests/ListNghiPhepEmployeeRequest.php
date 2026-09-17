<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListNghiPhepEmployeeRequest extends FormRequest
{
    private bool $sortWasProvided = false;

    private ?int $legacyPerPage = null;

    private const SORT_COLUMNS = ['ma_nv', 'ho_ten', 'gioi_tinh', 'sdt', 'email', 'ma_pb', 'ma_cv', 'ma_tt'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sortWasProvided = $this->has('sort') || $this->has('direction');
        $requestedPerPage = (int) $this->input('per_page', 10);
        $this->legacyPerPage = $requestedPerPage === 15 ? 15 : null;

        $this->merge([
            'tu_khoa' => trim((string) $this->input('tu_khoa', '')) ?: null,
            'page' => max((int) $this->input('page', 1), 1),
            // The management UI is deliberately limited to the shared sizes.
            // Keep the historical API value 15 only at the output boundary.
            'per_page' => in_array($requestedPerPage, [10, 20, 50], true) ? $requestedPerPage : 10,
            'sort' => strtolower(trim((string) $this->input('sort', 'ma_nv'))) ?: 'ma_nv',
            'direction' => strtolower(trim((string) $this->input('direction', 'desc'))) ?: 'desc',
        ]);
    }

    public function rules(): array
    {
        return [
            'tu_khoa' => ['nullable', 'string', 'max:255'],
            'ma_pb' => ['nullable', 'integer', 'min:1'],
            'ma_cv' => ['nullable', 'integer', 'min:1'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', Rule::in([10, 20, 50])],
            'sort' => ['required', 'string', Rule::in(self::SORT_COLUMNS)],
            'direction' => ['required', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /** @return array{tu_khoa:?string,ma_pb:?int,ma_cv:?int,ma_tt:null,page:int,so_dong:int,sort:string,direction:string} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'tu_khoa' => $validated['tu_khoa'] ?? null,
            'ma_pb' => isset($validated['ma_pb']) ? (int) $validated['ma_pb'] : null,
            'ma_cv' => isset($validated['ma_cv']) ? (int) $validated['ma_cv'] : null,
            'ma_tt' => null,
            'page' => (int) $validated['page'],
            'so_dong' => $this->legacyPerPage ?? (int) $validated['per_page'],
            'sort' => $validated['sort'],
            'direction' => $validated['direction'],
        ];
    }

    public function sortWasProvided(): bool
    {
        return $this->sortWasProvided;
    }
}
