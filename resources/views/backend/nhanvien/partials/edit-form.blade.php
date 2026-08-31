@php
    if (! isset($submitDisabled)) {
        $submitDisabled = $lookupError !== null || $missingLookups !== [];
    }
    $selectedLookup = $selectedLookup ?? function (string $key, string $valueKey, string $labelKey) use ($lookups, $employee): string {
        $value = old($valueKey, data_get($employee, $valueKey));
        $selected = collect($lookups[$key])->first(
            fn (mixed $item): bool => (string) data_get($item, $valueKey) === (string) $value,
        );

        return data_get($selected, $labelKey, 'Chưa chọn');
    };
    $reviewValue = $reviewValue ?? fn (string $field): string => filled(old($field, data_get($employee, $field)))
        ? old($field, data_get($employee, $field))
        : 'Chưa nhập';
@endphp

<form
    class="card-body"
    method="POST"
    action="{{ route('backend.nhanvien.update', ['ma_nv' => $employee->ma_nv]) }}"
    enctype="multipart/form-data"
    aria-busy="false"
    aria-describedby="edit-form-help"
    data-employee-wizard
    data-initial-step="{{ $firstErrorStep }}"
>
    @csrf
    @method('PUT')

    <div class="alert alert-danger mb-3" role="alert" data-modal-form-error hidden></div>

    <fieldset class="employee-step border-0 p-0 m-0" data-wizard-step="1">
        <legend class="visually-hidden">Bước 1: Hồ sơ và liên hệ</legend>
        <h2 class="h5 fw-semibold" tabindex="-1" data-step-heading>Bước 1: Hồ sơ và liên hệ</h2>
        <p class="text-secondary">Các trường có dấu <span aria-hidden="true">*</span> là bắt buộc.</p>

        @include('backend.nhanvien.partials.personal-fields')
        @include('backend.nhanvien.partials.address-fields')

        <div class="employee-step-actions justify-content-end">
            <button class="btn btn-primary" type="button" data-wizard-next>
                Tiếp tục
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
        </div>
    </fieldset>

    <fieldset class="employee-step border-0 p-0 m-0" data-wizard-step="2">
        <legend class="visually-hidden">Bước 2: Thông tin công việc</legend>
        <h2 class="h5 fw-semibold" tabindex="-1" data-step-heading>Bước 2: Thông tin công việc</h2>
        <p class="text-secondary">Chọn dữ liệu danh mục đã được cấu hình trong hệ thống.</p>

        @include('backend.nhanvien.partials.employment-fields')

        <div class="employee-step-actions">
            <button class="btn btn-outline-secondary" type="button" data-wizard-previous>
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Quay lại
            </button>
            <button class="btn btn-primary" type="button" data-wizard-next>
                Kiểm tra hồ sơ
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
        </div>
    </fieldset>

    <fieldset class="employee-step border-0 p-0 m-0" data-wizard-step="3">
        <legend class="visually-hidden">Bước 3: Kiểm tra và lưu</legend>
        <h2
            class="h5 fw-semibold"
            tabindex="-1"
            data-step-heading
            @if ($firstErrorField === 'nhan_vien') data-error-focus @endif
        >Bước 3: Kiểm tra và lưu</h2>
        <p class="text-secondary">Kiểm tra thông tin chính trước khi cập nhật hồ sơ.</p>

        <dl class="employee-review row mb-0">
            <div class="employee-review-row">
                <dt>Họ và tên</dt>
                <dd data-review-output="ho_ten">{{ $reviewValue('ho_ten') }}</dd>
            </div>
            <div class="employee-review-row">
                <dt>Email</dt>
                <dd class="text-break" data-review-output="email">{{ $reviewValue('email') }}</dd>
            </div>
            <div class="employee-review-row">
                <dt>Số điện thoại</dt>
                <dd data-review-output="sdt">{{ $reviewValue('sdt') }}</dd>
            </div>
            <div class="employee-review-row">
                <dt>Phòng ban</dt>
                <dd data-review-output="ma_pb">{{ $selectedLookup('phong_ban', 'ma_pb', 'ten_pb') }}</dd>
            </div>
            <div class="employee-review-row">
                <dt>Chức vụ</dt>
                <dd data-review-output="ma_cv">{{ $selectedLookup('chuc_vu', 'ma_cv', 'ten_cv') }}</dd>
            </div>
            <div class="employee-review-row">
                <dt>Trạng thái</dt>
                @if (\App\Enums\NhanVienStatus::isTerminalValue((int) ($employee->ma_tt ?? 0)))
                    <dd class="col-sm-7">{{ $employee->ten_tt }}</dd>
                @else
                    <dd class="col-sm-7" data-review-output="ma_tt">{{ $selectedLookup('trang_thai', 'ma_tt', 'ten_tt') }}</dd>
                @endif
            </div>
            <div class="employee-review-row">
                <dt>Ngày vào làm</dt>
                <dd data-review-output="ngay_vao_lam">{{ $reviewValue('ngay_vao_lam') }}</dd>
            </div>
        </dl>

        <div class="employee-step-actions">
            <button class="btn btn-outline-secondary" type="button" data-wizard-previous>
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Quay lại
            </button>
            <button
                class="btn btn-primary"
                type="submit"
                data-submit-employee
                @disabled($submitDisabled)
                aria-disabled="{{ $submitDisabled ? 'true' : 'false' }}"
                data-submitting-text="Đang cập nhật nhân viên…"
            >
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                Cập nhật hồ sơ
            </button>
        </div>
    </fieldset>
</form>
