<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class BulkAssignVaiTroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->request->all() as $field => $value) {
                if ($field !== 'assignments' && ! in_array($field, ['_token', '_method'], true)) {
                    $validator->errors()->add($field, 'Yêu cầu không nhận dữ liệu ngoài danh sách phân quyền.');
                }
            }

            $assignments = $this->input('assignments');
            if (! is_array($assignments)) {
                return;
            }

            foreach ($assignments as $employeeCode => $roleId) {
                if (! is_string($employeeCode) || preg_match('/\A[0-9]{5}\z/', $employeeCode) !== 1) {
                    $validator->errors()->add(
                        'assignments',
                        'Mã nhân viên trong phân quyền không hợp lệ.',
                    );
                }
                if ((is_string($roleId) && ! ctype_digit($roleId)) || (! is_int($roleId) && ! is_string($roleId))) {
                    $validator->errors()->add('assignments', 'Vai trò trong phân quyền không hợp lệ.');
                }
            }
        });
    }

    /** @return array<string, int> */
    public function assignments(): array
    {
        $assignments = $this->validated('assignments');

        return is_array($assignments)
            ? array_map(static fn (mixed $roleId): int => (int) $roleId, $assignments)
            : [];
    }
}
