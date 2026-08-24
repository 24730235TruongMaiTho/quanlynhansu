<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdatePhongBanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('ten_pb') && is_string($this->input('ten_pb'))) {
            $this->merge(['ten_pb' => trim($this->input('ten_pb'))]);
        }
    }

    public function rules(): array
    {
        return ['ten_pb' => ['required', 'string', 'max:100']];
    }

    public function messages(): array
    {
        return [
            'ten_pb.required' => 'Tên phòng ban không được để trống.',
            'ten_pb.string' => 'Tên phòng ban không hợp lệ.',
            'ten_pb.max' => 'Tên phòng ban không được dài quá 100 ký tự.',
        ];
    }
}
