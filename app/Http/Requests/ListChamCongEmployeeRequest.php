<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

final class ListChamCongEmployeeRequest extends FormRequest
{
    private bool $sortWasProvided = false;

    private ?Throwable $validationDatabaseFailure = null;

    private bool $departmentTableAvailable = true;

    private bool $departmentLookupRequested = false;

    private const SORT_COLUMNS = [
        'ma_nv', 'ho_ten', 'gioi_tinh', 'sdt', 'email', 'ma_pb', 'ma_cv',
        'so_lan_vao_muon', 'so_lan_ve_som', 'so_ngay_cham_cong', 'tong_gio_lam',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->sortWasProvided = $this->has('sort') || $this->has('direction');
        $this->departmentLookupRequested = $this->filled('ma_pb');
        try {
            $this->departmentTableAvailable = Schema::hasTable('phong_ban');
        } catch (Throwable $exception) {
            $this->departmentTableAvailable = false;
            $this->validationDatabaseFailure = $exception;
        }

        if (! $this->departmentTableAvailable && $this->departmentLookupRequested) {
            $this->merge(['ma_pb' => null]);
        }

        $this->merge([
            'tu_khoa' => trim((string) $this->input('tu_khoa', '')) ?: null,
            'page' => max((int) $this->input('page', 1), 1),
            'per_page' => in_array((int) $this->input('per_page', 10), [10, 20, 25, 50], true)
                ? (int) $this->input('per_page', 10)
                : 10,
            'sort' => strtolower(trim((string) $this->input('sort', 'ma_nv'))) ?: 'ma_nv',
            'direction' => strtolower(trim((string) $this->input('direction', 'desc'))) ?: 'desc',
        ]);
    }

    public function rules(): array
    {
        return [
            'tu_khoa' => ['nullable', 'string', 'max:255'],
            'ma_pb' => $this->departmentTableAvailable
                ? ['nullable', 'integer', 'exists:phong_ban,ma_pb']
                : ['nullable', 'integer'],
            'thang' => ['nullable', 'integer', 'between:1,12'],
            'nam' => ['nullable', 'integer', 'between:2000,2100'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', Rule::in([10, 20, 25, 50])],
            'sort' => ['required', 'string', Rule::in(self::SORT_COLUMNS)],
            'direction' => ['required', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /** @return array{tu_khoa:?string,ma_pb:?int,thang:int,nam:int,page:int,so_dong:int,sort:string,direction:string} */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'tu_khoa' => $validated['tu_khoa'] ?? null,
            'ma_pb' => isset($validated['ma_pb']) ? (int) $validated['ma_pb'] : null,
            'thang' => (int) ($validated['thang'] ?? now()->month),
            'nam' => (int) ($validated['nam'] ?? now()->year),
            'page' => (int) $validated['page'],
            'so_dong' => (int) $validated['per_page'],
            'sort' => $validated['sort'],
            'direction' => $validated['direction'],
        ];
    }

    public function sortWasProvided(): bool
    {
        return $this->sortWasProvided;
    }

    public function throwIfValidationDatabaseFailed(): void
    {
        if ($this->validationDatabaseFailure !== null) {
            throw $this->validationDatabaseFailure;
        }

        if (! $this->departmentTableAvailable && $this->departmentLookupRequested) {
            throw new \RuntimeException('Không thể kiểm tra phòng ban.');
        }
    }
}
