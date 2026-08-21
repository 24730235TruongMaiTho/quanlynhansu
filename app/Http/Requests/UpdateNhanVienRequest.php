<?php

namespace App\Http\Requests;

use App\Contracts\NhanVienRepositoryContract;
use App\Exceptions\NhanVienDomainException;
use App\Support\NhanVienTargetGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class UpdateNhanVienRequest extends StoreNhanVienRequest
{
    private ?object $authorizedTarget = null;

    public function authorize(): bool
    {
        $employees = $this->container->make(NhanVienRepositoryContract::class);
        $guard = $this->container->make(NhanVienTargetGuard::class);
        $maNv = $this->routeEmployeeCode();

        try {
            $employee = $employees->find($maNv);
        } catch (NhanVienDomainException $exception) {
            if ($exception->domainCode !== 'NV_NOT_FOUND') {
                throw $exception;
            }

            abort(404);
        }

        abort_if($employee === null, 404);
        $guard->assertManageable($employee);
        $this->authorizedTarget = $employee;

        return true;
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['xoa_anh_dai_dien'] = ['sometimes', 'boolean'];

        return $rules;
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->hasFile('anh_dai_dien')
                && in_array($this->input('xoa_anh_dai_dien'), [true, 1, '1'], true)) {
                $validator->errors()->add(
                    'xoa_anh_dai_dien',
                    'Không thể đồng thời tải ảnh mới và yêu cầu xóa ảnh đại diện.',
                );

                return;
            }

            $targetStatus = $this->targetStatusSymbol();
            $currentStatus = $this->authorizedTarget?->ky_hieu ?? null;

            if (($currentStatus === 'DA_NGHI' && $targetStatus !== null && $targetStatus !== 'DA_NGHI')
                || ($currentStatus !== 'DA_NGHI' && $targetStatus === 'DA_NGHI')) {
                $validator->errors()->add(
                    'ma_tt',
                    'Không thể thay đổi trạng thái đã nghỉ qua thao tác cập nhật hồ sơ.',
                );
            }

        }];
    }

    protected function emailUniqueRule(): Unique
    {
        return Rule::unique('nhan_vien', 'email')
            ->ignore($this->routeEmployeeCode(), 'ma_nv');
    }

    protected function cccdUniqueRule(): Unique
    {
        return Rule::unique('nhan_vien', 'cccd')
            ->ignore($this->routeEmployeeCode(), 'ma_nv');
    }

    protected function statusExistsRule(): Exists
    {
        return Rule::exists('trang_thai_lam_viec', 'ma_tt');
    }

    protected function ignoredEmployeeCodeForEmailUniqueness(): ?string
    {
        return $this->routeEmployeeCode();
    }

    private function routeEmployeeCode(): string
    {
        $maNv = $this->route('ma_nv');

        return is_string($maNv) ? $maNv : '';
    }

    private function targetStatusSymbol(): ?string
    {
        $maTt = $this->input('ma_tt');

        if (! is_int($maTt)) {
            return null;
        }

        $symbol = DB::table('trang_thai_lam_viec')
            ->where('ma_tt', $maTt)
            ->value('ky_hieu');

        return is_string($symbol) ? $symbol : null;
    }
}
