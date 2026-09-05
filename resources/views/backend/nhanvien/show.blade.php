@extends('backend.layouts.app')

@section('title', 'Hồ sơ ' . e($employee->ho_ten))

@section('content')
    @php
        $listQuery = array_filter(request()->only([
            'tu_khoa',
            'ma_pb',
            'ma_cv',
            'ma_tt',
            'page',
            'so_dong',
        ]), static fn (mixed $value): bool => $value !== null && $value !== '');
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
        $formatDate = static fn (mixed $value): string => filled($value) ? date('d/m/Y', strtotime((string) $value)) : 'Chưa cập nhật';
        $avatarUrl = filled($employee->anh_dai_dien)
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($employee->anh_dai_dien)
            : null;
    @endphp

    <main class="container container-lg py-4" aria-labelledby="page-title">
        <x-backend.page-header
            title="{{ $employee->ho_ten }}"
            title-id="page-title"
            icon="bi-person-circle"
            description="{{ $employee->ma_nv }} · {{ $employee->ten_cv }}"
            :breadcrumbs="[
                ['label' => 'Nhân sự', 'url' => route('backend.tongquan.index')],
                ['label' => 'Danh sách nhân viên', 'url' => $backUrl],
                ['label' => $employee->ma_nv],
            ]"
        >
            <x-slot:titlePrefix>
                @if ($avatarUrl)
                    <img
                        class="rounded-circle border object-fit-cover"
                        src="{{ $avatarUrl }}"
                        alt="Ảnh đại diện của {{ $employee->ho_ten }}"
                        width="112"
                        height="112"
                    >
                @else
                    <span
                        class="employee-avatar-placeholder d-inline-flex align-items-center justify-content-center rounded-circle border bg-light text-secondary fw-semibold fs-2 p-3"
                        role="img"
                        aria-label="Ảnh đại diện của {{ $employee->ho_ten }}"
                    >{{ $initials }}</span>
                @endif
            </x-slot:titlePrefix>
            <x-slot:actions>
                @can(\App\Enums\NhanVienPermission::Sua->value)
                    <a class="btn btn-primary" href="{{ $editUrl }}" data-employee-edit-trigger>
                        <i class="bi bi-pencil" aria-hidden="true"></i> Chỉnh sửa
                    </a>
                @endcan
                <a class="btn btn-outline-secondary" href="{{ $backUrl }}">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Quay lại danh sách
                </a>
            </x-slot:actions>
        </x-backend.page-header>

        @include('backend.nhanvien.partials.flash')

        @include('backend.nhanvien.partials.action-dialogs', ['employee' => $employee])

        <div class="row g-3">
            <div class="col-12">
                <section class="card shadow-sm" aria-labelledby="personal-title">
                    <div class="card-header bg-white py-3">
                        <h2 class="h6 fw-semibold mb-0" id="personal-title">Thông tin cá nhân</h2>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">Họ tên</dt>
                            <dd class="col-sm-8 col-lg-9">{{ $employee->ho_ten }}</dd>
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">Giới tính</dt>
                            <dd class="col-sm-8 col-lg-9">{{ $gender }}</dd>
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">Ngày sinh</dt>
                            <dd class="col-sm-8 col-lg-9">{{ $formatDate($employee->ngay_sinh) }}</dd>
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">Chức vụ</dt>
                            <dd class="col-sm-8 col-lg-9">{{ $employee->ten_cv }}</dd>
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">Phòng ban</dt>
                            <dd class="col-sm-8 col-lg-9">{{ $employee->ten_pb }}</dd>
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">Số điện thoại</dt>
                            <dd class="col-sm-8 col-lg-9">{{ $employee->sdt ?: 'Chưa cập nhật' }}</dd>
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">Email</dt>
                            <dd class="col-sm-8 col-lg-9 text-break">{{ $employee->email ?: 'Chưa cập nhật' }}</dd>
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">Dân tộc</dt>
                            <dd class="col-sm-8 col-lg-9">{{ $employee->dan_toc ?: 'Chưa cập nhật' }}</dd>
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">CCCD</dt>
                            <dd class="col-sm-8 col-lg-9">{{ $employee->cccd ?: 'Chưa cập nhật' }}</dd>
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">Nơi cấp CCCD</dt>
                            <dd class="col-sm-8 col-lg-9">{{ $employee->noi_cap_cccd ?: 'Chưa cập nhật' }}</dd>
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">Học vấn</dt>
                            <dd class="col-sm-8 col-lg-9">{{ $employee->hoc_van ?: 'Chưa cập nhật' }}</dd>
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">Ngày vào làm</dt>
                            <dd class="col-sm-8 col-lg-9">{{ $formatDate($employee->ngay_vao_lam) }}</dd>
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">Ngày nghỉ việc</dt>
                            <dd class="col-sm-8 col-lg-9">{{ $formatDate($employee->ngay_nghi_viec) }}</dd>
                            <dt class="col-sm-4 col-lg-3 text-secondary fw-normal">Trạng thái làm việc</dt>
                            <dd class="col-sm-8 col-lg-9"><span class="badge text-bg-light border fw-normal">{{ $employee->ten_tt }}</span></dd>
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
                <section class="card shadow-sm h-100" aria-labelledby="salary-title">
                    <div class="card-header bg-white py-3">
                        <h2 class="h6 fw-semibold mb-0" id="salary-title">Phụ cấp chức vụ</h2>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-6 text-secondary fw-normal">Hệ số phụ cấp chức vụ</dt>
                            <dd class="col-sm-6">{{ isset($employee->he_so_phu_cap) ? number_format((float) $employee->he_so_phu_cap, 2, '.', ',') : 'Chưa cập nhật' }}</dd>
                        </dl>
                    </div>
                </section>
            </div>

            <div class="col-12 col-lg-6">
                <section class="card shadow-sm h-100" aria-labelledby="account-title">
                    <div class="card-header bg-white py-3">
                        <h2 class="h6 fw-semibold mb-0" id="account-title">Tài khoản</h2>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 text-secondary fw-normal">Vai trò</dt>
                            <dd class="col-sm-7">{{ $employee->ten_vt }}</dd>
                        </dl>
                    </div>
                </section>
            </div>
        </div>

        @can(\App\Enums\NhanVienPermission::Sua->value)
            @include('backend.nhanvien.partials.edit-modal')
        @endcan
    </main>
@endsection

@push('scripts')
    @vite('resources/js/frontend/nhanvien/nhanvien.js')
@endpush
