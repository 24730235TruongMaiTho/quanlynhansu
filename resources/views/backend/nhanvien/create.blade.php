@extends('backend.layouts.app')

@section('title', 'Thêm nhân viên')

@section('content')
    @php
        $submitDisabled = $lookupError !== null || $missingLookups !== [];
        $selectedLookup = function (string $key, string $valueKey, string $labelKey) use ($lookups): string {
            $selected = collect($lookups[$key])->first(
                fn (mixed $item): bool => (string) data_get($item, $valueKey) === (string) old($valueKey),
            );

            return data_get($selected, $labelKey, 'Chưa chọn');
        };
        $reviewValue = fn (string $field): string => filled(old($field)) ? old($field) : 'Chưa nhập';
    @endphp

    <main class="employee-page container container-xl py-4" aria-labelledby="page-title">
        <nav class="mb-3" aria-label="Đường dẫn trang">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">Nhân sự</li>
                <li class="breadcrumb-item"><a href="{{ route('backend.nhanvien.index') }}">Danh sách nhân viên</a></li>
                <li class="breadcrumb-item active" aria-current="page">Thêm nhân viên</li>
            </ol>
        </nav>

        <header class="d-flex flex-column flex-sm-row align-items-sm-start justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1" id="page-title">Thêm nhân viên</h1>
                <p class="text-secondary mb-0">Nhập hồ sơ, thông tin công việc rồi kiểm tra trước khi lưu.</p>
            </div>
            <a class="btn btn-outline-secondary" href="{{ route('backend.nhanvien.index') }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Quay lại danh sách
            </a>
        </header>

        @include('backend.nhanvien.partials.flash')

        @if ($lookupError)
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Không tải được dữ liệu danh mục</p>
                <p class="mb-0">{{ $lookupError }}</p>
            </div>
        @elseif ($missingLookups !== [])
            <div class="alert alert-warning" role="alert">
                <p class="fw-semibold mb-1">Thiếu dữ liệu danh mục bắt buộc</p>
                <p class="mb-2">Chưa thể tạo nhân viên cho tới khi có đủ:</p>
                <ul class="mb-0">
                    @foreach ($missingLookups as $missingLookup)
                        <li>{{ $missingLookup }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="alert alert-info" role="note">
            <p class="mb-1"><strong>Mã nhân viên được hệ thống tự cấp</strong> sau khi lưu thành công.</p>
            <p class="mb-1">Mật khẩu demo ban đầu theo quy ước <strong>nhom3{{ '@' }}{{ now(config('app.timezone'))->year }}</strong>.</p>
            <p class="mb-0">Vai trò <strong>NHAN_VIEN_MAC_DINH</strong> không có quyền mặc định; quyền phải được cấp ở luồng quản trị riêng.</p>
        </div>

        <section class="card shadow-sm" aria-labelledby="wizard-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-3" id="wizard-title">Quy trình tạo hồ sơ</h2>
                <ol class="employee-stepper mb-0" aria-label="Tiến trình tạo nhân viên">
                    <li data-step-indicator="1" aria-current="{{ $firstErrorStep === 1 ? 'step' : 'false' }}">
                        <span aria-hidden="true">1</span> Hồ sơ
                    </li>
                    <li data-step-indicator="2" aria-current="{{ $firstErrorStep === 2 ? 'step' : 'false' }}">
                        <span aria-hidden="true">2</span> Công việc
                    </li>
                    <li data-step-indicator="3" aria-current="{{ $firstErrorStep === 3 ? 'step' : 'false' }}">
                        <span aria-hidden="true">3</span> Kiểm tra
                    </li>
                </ol>
            </div>

            <form
                class="card-body"
                method="POST"
                action="{{ route('backend.nhanvien.store') }}"
                enctype="multipart/form-data"
                aria-busy="false"
                data-employee-wizard
                data-initial-step="{{ $firstErrorStep }}"
            >
                @csrf

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
                        <dd class="col-sm-7" data-review-output="ngay_vao_lam">{{ $reviewValue('ngay_vao_lam') }}</dd>
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
                            data-submitting-text="Đang lưu nhân viên…"
                        >
                            <i class="bi bi-check-circle" aria-hidden="true"></i>
                            Lưu nhân viên
                        </button>
                    </div>
                </fieldset>
            </form>
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/nhanvien/nhanvien.js')
@endpush
