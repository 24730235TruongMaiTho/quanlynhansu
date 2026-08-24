@extends('backend.layouts.app')

@section('title', 'Cập nhật ' . e($employee->ho_ten))

@section('content')
    @php
        $submitDisabled = $lookupError !== null || $missingLookups !== [];
        $backQuery = request()->only(['tu_khoa', 'ma_pb', 'ma_cv', 'ma_tt', 'page', 'so_dong']);
        $backUrl = route('backend.nhanvien.show', ['ma_nv' => $employee->ma_nv] + $backQuery);
        $selectedLookup = function (string $key, string $valueKey, string $labelKey) use ($lookups, $employee): string {
            $value = old($valueKey, data_get($employee, $valueKey));
            $selected = collect($lookups[$key])->first(
                fn (mixed $item): bool => (string) data_get($item, $valueKey) === (string) $value,
            );

            return data_get($selected, $labelKey, 'Chưa chọn');
        };
        $reviewValue = fn (string $field): string => filled(old($field, data_get($employee, $field)))
            ? old($field, data_get($employee, $field))
            : 'Chưa nhập';
        $canResetPassword = \Illuminate\Support\Facades\Gate::allows(\App\Enums\NhanVienPermission::DatLaiMatKhau->value);
        $canDestroy = \Illuminate\Support\Facades\Gate::allows(\App\Enums\NhanVienPermission::Xoa->value);
        $isManageableTarget = (int) ($employee->ma_vt ?? 0) === \App\Enums\NhanVienRole::Employee->value;
    @endphp

    <main class="employee-page container container-xl py-4" aria-labelledby="page-title">
        <nav class="mb-3" aria-label="Đường dẫn trang">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">Nhân sự</li>
                <li class="breadcrumb-item"><a href="{{ route('backend.nhanvien.index', $backQuery) }}">Danh sách nhân viên</a></li>
                <li class="breadcrumb-item"><a href="{{ $backUrl }}">{{ $employee->ma_nv }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Cập nhật</li>
            </ol>
        </nav>

        <header class="d-flex flex-column flex-sm-row align-items-sm-start justify-content-between gap-3 mb-4">
            <div>
                <h1 class="h3 fw-semibold mb-1" id="page-title">Cập nhật hồ sơ nhân viên</h1>
                <p class="text-secondary mb-0">Chỉnh sửa hồ sơ và địa chỉ; mã, vai trò và mật khẩu được hệ thống giữ nguyên.</p>
            </div>
            <a class="btn btn-outline-secondary" href="{{ $backUrl }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Quay lại hồ sơ
            </a>
        </header>

        @include('backend.nhanvien.partials.flash')
        @if ($isManageableTarget && ($canResetPassword || $canDestroy))
            @php
                $dialogKey = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $employee->ma_nv);
                $resetDialogId = 'employee-reset-password-' . $dialogKey;
                $destroyDialogId = 'employee-destroy-' . $dialogKey;
            @endphp
            <div class="employee-action-dialogs d-inline-flex flex-wrap gap-2 mt-2" data-action-dialogs>
                @can(\App\Enums\NhanVienPermission::DatLaiMatKhau->value)
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-dialog-open="{{ $resetDialogId }}" aria-controls="{{ $resetDialogId }}">Đặt lại mật khẩu</button>
                    <dialog class="employee-action-dialog" id="{{ $resetDialogId }}" data-action-dialog aria-labelledby="{{ $resetDialogId }}-title">
                        <form method="POST" action="{{ route('backend.nhanvien.reset-password', ['ma_nv' => $employee->ma_nv]) }}" data-dialog-form>
                            @csrf
                            @method('PATCH')
                            <h2 class="h5" id="{{ $resetDialogId }}-title">Đặt lại mật khẩu nhân viên</h2>
                            <p>Mật khẩu sẽ được thay bằng quy ước tĩnh <code>nhom3@{năm thao tác}</code>; mật khẩu thực không hiển thị trên trang.</p>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-outline-secondary" data-dialog-cancel>Hủy</button>
                                <button type="submit" class="btn btn-primary" data-dialog-submit>Đặt lại mật khẩu</button>
                            </div>
                        </form>
                    </dialog>
                @endcan
                @can(\App\Enums\NhanVienPermission::Xoa->value)
                    <button class="btn btn-sm btn-outline-danger" type="button" data-dialog-open="{{ $destroyDialogId }}" aria-controls="{{ $destroyDialogId }}">Xóa hoặc kết thúc</button>
                    <dialog class="employee-action-dialog" id="{{ $destroyDialogId }}" data-action-dialog aria-labelledby="{{ $destroyDialogId }}-title">
                        <form method="POST" action="{{ route('backend.nhanvien.destroy', ['ma_nv' => $employee->ma_nv]) }}" data-dialog-form data-confirm-message="Xác nhận xóa cứng nếu chưa có lịch sử; nếu đã có lịch sử, hồ sơ sẽ được kết thúc theo lịch sử.">
                            @csrf
                            @method('DELETE')
                            <h2 class="h5" id="{{ $destroyDialogId }}-title">Xóa hoặc kết thúc hồ sơ</h2>
                            <p>Xóa cứng nếu chưa có lịch sử; nếu đã có lịch sử, hệ thống chỉ kết thúc hồ sơ và giữ lại lịch sử liên quan.</p>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-outline-secondary" data-dialog-cancel>Hủy</button>
                                <button type="submit" class="btn btn-danger" data-dialog-submit>Xác nhận thao tác</button>
                            </div>
                        </form>
                    </dialog>
                @endcan
            </div>
        @endif

        @if ($lookupError)
            <div class="alert alert-danger" role="alert">
                <p class="fw-semibold mb-1">Không tải được dữ liệu danh mục</p>
                <p class="mb-0">{{ $lookupError }}</p>
            </div>
        @elseif ($missingLookups !== [])
            <div class="alert alert-warning" role="alert">
                <p class="fw-semibold mb-1">Thiếu dữ liệu danh mục bắt buộc</p>
                <p class="mb-2">Chưa thể cập nhật nhân viên cho tới khi có đủ:</p>
                <ul class="mb-0">
                    @foreach ($missingLookups as $missingLookup)
                        <li>{{ $missingLookup }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="alert alert-info" role="note">
            <p class="mb-1"><strong>Mã nhân viên:</strong> {{ $employee->ma_nv }} (chỉ đọc).</p>
            <p class="mb-0"><strong>Vai trò:</strong> {{ $employee->ten_vt }} (chỉ đọc; thay đổi vai trò thuộc luồng quản trị riêng).</p>
        </div>

        <section class="card shadow-sm" aria-labelledby="wizard-title">
            <div class="card-header bg-white py-3">
                <h2 class="h6 fw-semibold mb-3" id="wizard-title">Quy trình cập nhật hồ sơ</h2>
                <ol class="employee-stepper mb-0" aria-label="Tiến trình cập nhật nhân viên">
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

            @can(\App\Enums\NhanVienPermission::Sua->value)
            <form
                class="card-body"
                method="POST"
                action="{{ route('backend.nhanvien.update', ['ma_nv' => $employee->ma_nv]) }}"
                enctype="multipart/form-data"
                aria-busy="false"
                data-employee-wizard
                data-initial-step="{{ $firstErrorStep }}"
            >
                @csrf
                @method('PUT')

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
                        @if ((int) ($employee->ma_tt ?? 0) === \App\Enums\NhanVienStatus::Terminated->value)
                            <dd class="col-sm-7">{{ $employee->ten_tt }}</dd>
                        @else
                            <dd class="col-sm-7" data-review-output="ma_tt">{{ $selectedLookup('trang_thai', 'ma_tt', 'ten_tt') }}</dd>
                        @endif
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
                            data-submitting-text="Đang cập nhật nhân viên…"
                        >
                            <i class="bi bi-check-circle" aria-hidden="true"></i>
                            Cập nhật hồ sơ
                        </button>
                    </div>
                </fieldset>
            </form>
            @endcan
        </section>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/nhanvien/nhanvien.js')
@endpush
