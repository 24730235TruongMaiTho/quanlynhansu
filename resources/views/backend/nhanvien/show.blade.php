@extends('backend.layouts.app')

@section('title', 'Hồ sơ ' . $employee->ho_ten)

@section('content')
    @php
        $backUrl = route('backend.nhanvien.index', request()->only([
            'tu_khoa',
            'ma_pb',
            'ma_cv',
            'ma_tt',
            'page',
            'so_dong',
        ]));
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
                @if (filled($employee->anh_dai_dien))
                    <img
                        class="rounded-circle border object-fit-cover"
                        src="{{ asset(ltrim($employee->anh_dai_dien, '/')) }}"
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
            <a class="btn btn-outline-secondary align-self-start align-self-sm-center" href="{{ $backUrl }}">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Quay lại danh sách
            </a>
        </div>

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
