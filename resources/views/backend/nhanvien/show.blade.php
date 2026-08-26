@extends('backend.layouts.app')

@section('title', 'Hồ sơ ' . e($employee->ho_ten))

@section('content')
    @php
        $listQuery = request()->only([
            'tu_khoa',
            'ma_pb',
            'ma_cv',
            'ma_tt',
            'page',
            'so_dong',
        ]);
        $backUrl = route('backend.nhanvien.index', $listQuery);
        $editUrl = route('backend.nhanvien.edit', ['ma_nv' => $employee->ma_nv] + $listQuery);
        $nameParts = preg_split('/\s+/u', trim($employee->ho_ten), -1, PREG_SPLIT_NO_EMPTY);
        $firstInitial = mb_strtoupper(mb_substr($nameParts[0] ?? 'N', 0, 1));
        $lastInitial = count($nameParts) > 1
            ? mb_strtoupper(mb_substr($nameParts[array_key_last($nameParts)], 0, 1))
            : '';
        $initials = $firstInitial . $lastInitial;
        $gender = match ((int) $employee->gioi_tinh) {
            1 => 'Nam',
            0 => 'Nữ',
            default => 'Khác',
        };
        $avatarUrl = filled($employee->anh_dai_dien)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($employee->anh_dai_dien)
            : null;
        $canEdit = \Illuminate\Support\Facades\Gate::allows(\App\Enums\NhanVienPermission::Sua->value);
        $canResetPassword = \Illuminate\Support\Facades\Gate::allows(\App\Enums\NhanVienPermission::DatLaiMatKhau->value);
        $canDestroy = \Illuminate\Support\Facades\Gate::allows(\App\Enums\NhanVienPermission::Xoa->value);
        $isManageableTarget = (int) ($employee->ma_vt ?? 0) === \App\Enums\NhanVienRole::Employee->value;
    @endphp

    <main class="container container-lg py-4" aria-labelledby="page-title">
        <nav class="mb-3" aria-label="Đường dẫn trang">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">Nhân sự</li>
                <li class="breadcrumb-item"><a href="{{ $backUrl }}">Danh sách nhân viên</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $employee->ma_nv }}</li>
            </ol>
        </nav>

        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                @if ($avatarUrl)
                    <img
                        class="rounded-circle border object-fit-cover"
                        src="{{ $avatarUrl }}"
                        alt="Ảnh đại diện của {{ $employee->ho_ten }}"
                        width="72"
                        height="72"
                    >
                @else
                    <span
                        class="d-inline-flex align-items-center justify-content-center rounded-circle border bg-light text-secondary fw-semibold fs-4 p-3"
                        role="img"
                        aria-label="Ảnh đại diện của {{ $employee->ho_ten }}"
                    >{{ $initials }}</span>
                @endif
                <div>
                    <h1 class="h3 fw-semibold mb-1" id="page-title">{{ $employee->ho_ten }}</h1>
                    <p class="text-secondary mb-0">{{ $employee->ma_nv }} · {{ $employee->ten_cv }}</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-self-start align-self-sm-center">
                @if ($canEdit)
                    @can(\App\Enums\NhanVienPermission::Sua->value)
                    <a class="btn btn-primary" href="{{ $editUrl }}">
                        <i class="bi bi-pencil" aria-hidden="true"></i>
                        Chỉnh sửa
                    </a>
                    @endcan
                @endif
                <a class="btn btn-outline-secondary" href="{{ $backUrl }}">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    Quay lại danh sách
                </a>
            </div>
        </div>

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

        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <section class="card shadow-sm h-100" aria-labelledby="personal-title">
                    <div class="card-header bg-white py-3">
                        <h2 class="h6 fw-semibold mb-0" id="personal-title">Thông tin cá nhân</h2>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 text-secondary fw-normal">Ngày sinh</dt>
                            <dd class="col-sm-7">{{ date('d/m/Y', strtotime($employee->ngay_sinh)) }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Giới tính</dt>
                            <dd class="col-sm-7">{{ $gender }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Dân tộc</dt>
                            <dd class="col-sm-7">{{ $employee->dan_toc }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Trình độ học vấn</dt>
                            <dd class="col-sm-7">{{ $employee->hoc_van }}</dd>
                        </dl>
                    </div>
                </section>
            </div>

            <div class="col-12 col-lg-6">
                <section class="card shadow-sm h-100" aria-labelledby="contact-title">
                    <div class="card-header bg-white py-3">
                        <h2 class="h6 fw-semibold mb-0" id="contact-title">Thông tin liên hệ</h2>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 text-secondary fw-normal">Số điện thoại</dt>
                            <dd class="col-sm-7">{{ $employee->sdt }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Email</dt>
                            <dd class="col-sm-7 text-break">{{ $employee->email }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">CCCD</dt>
                            <dd class="col-sm-7">{{ $employee->cccd }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Nơi cấp CCCD</dt>
                            <dd class="col-sm-7">{{ $employee->noi_cap_cccd }}</dd>
                        </dl>
                    </div>
                </section>
            </div>

            <div class="col-12 col-lg-6">
                <section class="card shadow-sm h-100" aria-labelledby="address-title">
                    <div class="card-header bg-white py-3">
                        <h2 class="h6 fw-semibold mb-0" id="address-title">Địa chỉ</h2>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 text-secondary fw-normal">Địa chỉ cụ thể</dt>
                            <dd class="col-sm-7">{{ $employee->dia_chi_cu_the ?: 'Chưa cập nhật' }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Phường/Xã</dt>
                            <dd class="col-sm-7">{{ $employee->phuong_xa ?: 'Chưa cập nhật' }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Quận/Huyện</dt>
                            <dd class="col-sm-7">{{ $employee->quan_huyen ?: 'Chưa cập nhật' }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Tỉnh/Thành phố</dt>
                            <dd class="col-sm-7">{{ $employee->tinh_thanh ?: 'Chưa cập nhật' }}</dd>
                        </dl>
                    </div>
                </section>
            </div>

            <div class="col-12 col-lg-6">
                <section class="card shadow-sm h-100" aria-labelledby="employment-title">
                    <div class="card-header bg-white py-3">
                        <h2 class="h6 fw-semibold mb-0" id="employment-title">Công việc và tài khoản</h2>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 text-secondary fw-normal">Phòng ban</dt>
                            <dd class="col-sm-7">{{ $employee->ten_pb }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Chức vụ</dt>
                            <dd class="col-sm-7">{{ $employee->ten_cv }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Ngày vào làm</dt>
                            <dd class="col-sm-7">{{ date('d/m/Y', strtotime($employee->ngay_vao_lam)) }}</dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Trạng thái</dt>
                            <dd class="col-sm-7">
                                <span class="badge text-bg-light border fw-normal">{{ $employee->ten_tt }}</span>
                            </dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Ngày nghỉ việc</dt>
                            <dd class="col-sm-7">
                                {{ filled($employee->ngay_nghi_viec) ? date('d/m/Y', strtotime($employee->ngay_nghi_viec)) : 'Không có' }}
                            </dd>
                            <dt class="col-sm-5 text-secondary fw-normal">Vai trò</dt>
                            <dd class="col-sm-7">{{ $employee->ten_vt }}</dd>
                        </dl>
                    </div>
                </section>
            </div>
        </div>
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/nhanvien/nhanvien.js')
@endpush
