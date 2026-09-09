@php
    $modalOnly = $modalOnly ?? false;
    $submitDisabled = $submitDisabled ?? $lookupError !== null || $missingLookups !== [];
    $selectedLookup = $selectedLookup ?? function (string $key, string $valueKey, string $labelKey) use ($lookups): string {
        $selected = collect($lookups[$key])->first(
            fn (mixed $item): bool => (string) data_get($item, $valueKey) === (string) old($valueKey),
        );

        return data_get($selected, $labelKey, 'Chưa chọn');
    };
    $reviewValue = $reviewValue ?? fn (string $field): string => filled(old($field)) ? old($field) : 'Chưa nhập';
    $reviewDateValue = static function () use ($reviewValue, $modalOnly): string {
        $value = $reviewValue('ngay_vao_lam');

        if (! $modalOnly || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        return substr($value, 8, 2).'/'.substr($value, 5, 2).'/'.substr($value, 0, 4);
    };
@endphp

<form
    class="card-body"
    method="POST"
    action="{{ route('backend.nhanvien.store') }}"
    enctype="multipart/form-data"
    aria-busy="false"
    data-employee-wizard
    data-employee-modal-form
    data-initial-step="{{ $firstErrorStep }}"
>
    @csrf

    <fieldset class="employee-step border-0 p-0 m-0" data-wizard-step="1">
        <legend class="visually-hidden">Bước 1: Hồ sơ và liên hệ</legend>
        <h2 class="h5 fw-semibold" tabindex="-1" data-step-heading>Bước 1: Hồ sơ và liên hệ</h2>
        <p class="text-secondary">Các trường có dấu <span aria-hidden="true">*</span> là bắt buộc.</p>

        @include('backend.nhanvien.partials.personal-fields')
        @include('backend.nhanvien.partials.address-fields', ['showDistrictField' => ! $modalOnly])

        <div class="employee-step-actions justify-content-end">
            <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="button" data-wizard-next>
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
            <button class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" type="button" data-wizard-previous>
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Quay lại
            </button>
            <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="button" data-wizard-next>
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
        <p class="text-secondary">Kiểm tra thông tin chính trước khi tạo hồ sơ.</p>

        <dl class="employee-review row mb-0">
            <dt class="col-sm-5">Họ và tên</dt>
            <dd class="col-sm-7" data-review-output="ho_ten">{{ $reviewValue('ho_ten') }}</dd>
            <dt class="col-sm-5">Email</dt>
            <dd class="col-sm-7 text-break" data-review-output="email">{{ $reviewValue('email') }}</dd>
            <dt class="col-sm-5">Số điện thoại</dt>
            <dd class="col-sm-7" data-review-output="sdt">{{ $reviewValue('sdt') }}</dd>
            <dt class="col-sm-5">Phòng ban</dt>
            <dd class="col-sm-7" data-review-output="ma_pb">{{ $selectedLookup('phong_ban', 'ma_pb', 'ten_pb') }}</dd>
            <dt class="col-sm-5">Chức vụ</dt>
            <dd class="col-sm-7" data-review-output="ma_cv">{{ $selectedLookup('chuc_vu', 'ma_cv', 'ten_cv') }}</dd>
            <dt class="col-sm-5">Trạng thái</dt>
            <dd class="col-sm-7" data-review-output="ma_tt">{{ $selectedLookup('trang_thai', 'ma_tt', 'ten_tt') }}</dd>
            <dt class="col-sm-5">Ngày vào làm</dt>
            <dd
                class="col-sm-7"
                data-review-output="ngay_vao_lam"
                @if ($modalOnly) data-review-format="date-dmy" @endif
            >{{ $reviewDateValue() }}</dd>
        </dl>

        <div class="employee-step-actions">
            <button class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" type="button" data-wizard-previous>
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Quay lại
            </button>
            <button
                class="btn btn-primary d-inline-flex align-items-center gap-2"
                type="submit"
                data-submit-employee
                @disabled($submitDisabled)
                aria-disabled="{{ $submitDisabled ? 'true' : 'false' }}"
                data-submitting-text="Đang lưu nhân viên…"
            >
                <i class="bi bi-check-circle" aria-hidden="true"></i>
                Lưu nhân viên
            </button>
        </div>
    </fieldset>
</form>
