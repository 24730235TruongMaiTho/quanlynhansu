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
        <x-backend.page-header
            title="Thêm nhân viên"
            title-id="page-title"
            icon="bi-person-plus"
            description="Nhập hồ sơ, thông tin công việc rồi kiểm tra trước khi lưu."
            :breadcrumbs="[
                ['label' => 'Nhân sự', 'url' => route('backend.tongquan.index')],
                ['label' => 'Danh sách nhân viên', 'url' => route('backend.nhanvien.index')],
                ['label' => 'Thêm nhân viên'],
            ]"
        >
            <x-slot:actions>
            <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="{{ route('backend.nhanvien.index') }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Quay lại danh sách
            </a>
            </x-slot:actions>
        </x-backend.page-header>

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

            @can(\App\Enums\NhanVienPermission::Tao->value)
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
            @else
                <div class="card-body" role="alert">Bạn không có quyền tạo nhân viên.</div>
            @endcan
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/nhanvien/nhanvien.js')
@endpush
