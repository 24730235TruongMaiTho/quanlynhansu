@extends('backend.layouts.app')
@section('title', 'Quản lý chấm công')

@section('content')
    <main class="container-fluid container-xxl py-4 attendance-page" aria-labelledby="page-title"
        data-cham-cong-can-read="{{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\ChamCongPermission::Xem->value) ? '1' : '0' }}"
        data-cham-cong-can-create="{{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\ChamCongPermission::Tao->value) ? '1' : '0' }}"
        data-cham-cong-can-update="{{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\ChamCongPermission::Sua->value) ? '1' : '0' }}">
        <section class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 small text-secondary">
                    <a href="#" class="text-secondary text-decoration-none">Thời gian làm việc</a>
                    <span>/</span><span>Chấm công</span>
                </div>
                <h1 class="h3 fw-semibold mb-1" id="page-title">Chấm công</h1>
                <p class="text-secondary mb-0">Theo dõi ngày công, số giờ làm, vào muộn và về sớm theo từng nhân viên.</p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="import-btn" type="button"
                    {{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\ChamCongPermission::Tao->value) ? '' : 'disabled' }}>Nhập bảng chấm công</button>
                <button class="btn btn-outline-secondary btn-sm" id="export-btn" type="button"
                    {{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\ChamCongPermission::Xem->value) ? '' : 'disabled' }}>Xuất bảng chấm công</button>
                <button class="btn btn-success btn-sm" id="update-btn" type="button" disabled>Cập nhật chấm công</button>
            </div>
        </section>

        <section class="card shadow-sm mb-3" aria-label="Bộ lọc chấm công">
            <div class="card-body py-3">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-xl-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white">⌕</span>
                            <input class="form-control" type="search" id="search-field"
                                   placeholder="Tìm mã hoặc tên nhân viên..." aria-label="Tìm nhân viên">
                        </div>
                    </div>

                    <div class="col-6 col-md-3 col-xl-2">
                        <select class="form-select form-select-sm" id="month-filter" aria-label="Tháng"></select>
                    </div>

                    <div class="col-6 col-md-3 col-xl-2">
                        <input class="form-control form-control-sm" id="year-filter" type="number"
                               min="2000" max="2100" placeholder="Năm" aria-label="Năm">
                    </div>

                    <div class="col-12 col-md-4 col-xl-2">
                        <select class="form-select form-select-sm" id="department-filter" aria-label="Phòng ban">
                            <option value="">-- Tất cả phòng ban --</option>
                        </select>
                    </div>

                    <select id="status-filter" hidden aria-hidden="true">
                        <option value="">Tất cả trạng thái</option>
                        <option value="good">Đủ công</option>
                        <option value="warning">Cần kiểm tra</option>
                        <option value="leave">Có nghỉ phép</option>
                    </select>

                    <div class="col-12 col-md-2 col-xl-2">
                        <button class="btn btn-outline-secondary btn-sm w-100" id="clear-filter-btn" type="button">Xóa lọc</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="card shadow-sm overflow-hidden mb-3" aria-labelledby="employee-table-title">
            <div class="card-header bg-white d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 py-3">
                <div>
                    <h2 class="h6 fw-semibold mb-1" id="employee-table-title">Danh sách nhân viên</h2>
                    <p class="small text-secondary mb-0" id="employee-table-description">
                        Chọn nhân viên để xem bảng chấm công chi tiết theo tháng.
                    </p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge text-bg-light border fw-normal" id="selected-employee-badge">Chưa chọn nhân viên</span>
                    <span class="small text-secondary" id="employee-updated">Chưa tải dữ liệu</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 attendance-employee-table">
                    <thead class="table-light">
                    <tr>
                        <th style="width:42px;"></th>
                        <th>Mã nhân viên</th>
                        <th>Họ tên</th>
                        <th>Giới tính</th>
                        <th>Số điện thoại</th>
                        <th>Email</th>
                        <th>Phòng ban</th>
                        <th>Chức vụ</th>
                        <th class="text-end">Vào muộn</th>
                        <th class="text-end">Về sớm</th>
                        <th class="text-end">Ngày công</th>
                    </tr>
                    </thead>
                    <tbody id="employee-tbody">
                    <tr><td colspan="11" class="text-center text-secondary py-5">Đang tải danh sách nhân viên...</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 py-3">
                <div class="d-flex align-items-center flex-wrap gap-3">
                    <span class="small text-secondary" id="employee-page-info">Hiển thị 0 trên 0 nhân viên</span>
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-secondary mb-0 text-nowrap" for="employee-per-page">Số dòng</label>
                        <select class="form-select form-select-sm" id="employee-per-page" style="width:82px;">
                            <option value="5">5</option><option value="10">10</option>
                            <option value="15" selected>15</option><option value="25">25</option><option value="50">50</option>
                        </select>
                        <span class="small text-secondary text-nowrap">/ trang</span>
                    </div>
                </div>
                <nav id="employee-pagination" aria-label="Phân trang nhân viên"></nav>
            </div>
        </section>

        <section class="card shadow-sm overflow-hidden table-card" aria-labelledby="table-title">
            <div class="card-header bg-white d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3 py-3">
                <div>
                    <h2 class="h6 fw-semibold mb-1" id="table-title">Bảng chấm công</h2>
                    <p class="small text-secondary mb-0" id="attendance-description">
                        Chọn nhân viên ở bảng phía trên để tải dữ liệu chấm công.
                    </p>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-light border fw-normal">Tổng giờ: <strong class="ms-1" id="total-hours">0</strong></span>
                    <span class="badge text-bg-light border fw-normal">Vào muộn: <strong class="ms-1" id="late-count">0</strong></span>
                    <span class="badge text-bg-light border fw-normal">Về sớm: <strong class="ms-1" id="early-count">0</strong></span>
                    <span class="badge text-bg-light border fw-normal">Ngày công: <strong class="ms-1" id="avg-days">0</strong></span>
                </div>
            </div>

            <div class="table-responsive table-scroll">
                <table class="table table-hover align-middle mb-0 data-table attendance-detail-table">
                    <thead class="table-light">
                    <tr>
                        <th style="width:42px;"></th>
                        <th>Mã chấm công</th>
                        <th>Mã nhân viên</th>
                        <th>Ngày</th>
                        <th>Thứ</th>
                        <th class="text-end">Số giờ làm</th>
                        <th class="text-center">Vào muộn</th>
                        <th class="text-center">Về sớm</th>
                        <th class="text-end">Ngày công</th>
                        <th>Đánh giá</th>
                    </tr>
                    </thead>
                    <tbody id="attendance-tbody">
                    <tr class="empty-row"><td colspan="10" class="text-center text-secondary py-5">Chưa chọn nhân viên.</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 py-3">
                <div class="d-flex align-items-center flex-wrap gap-3">
                    <span class="small text-secondary" id="page-info">Hiển thị 0 trên 0 bản ghi</span>
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-secondary mb-0 text-nowrap" for="attendance-per-page">Số dòng</label>
                        <select class="form-select form-select-sm" id="attendance-per-page" style="width:82px;">
                            <option value="5">5</option><option value="10">10</option>
                            <option value="15" selected>15</option><option value="25">25</option><option value="50">50</option>
                        </select>
                        <span class="small text-secondary text-nowrap">/ trang</span>
                    </div>
                </div>
                <nav class="pagination mb-0" id="pagination" aria-label="Phân trang chấm công"></nav>
            </div>
        </section>
    </main>

    <input id="attendance-import-file" type="file" accept=".xlsx,.xls,.csv" hidden>
    <div class="toast" role="status" aria-live="polite"></div>

    <style>
        .attendance-page .attendance-employee-table{min-width:1180px;font-size:.875rem}
        .attendance-page .attendance-detail-table{min-width:980px;font-size:.875rem}
        .attendance-page [data-employee-row],.attendance-page [data-attendance-row]{cursor:pointer}
        .attendance-page .attendance-edit-input{width:76px;margin-left:auto}
        .attendance-page .table-primary>*{--bs-table-bg-state:rgba(13,110,253,.10)}
    </style>
@endsection

@push('scripts')
    @vite('resources/js/frontend/chamcong/chamcong.js')
@endpush
