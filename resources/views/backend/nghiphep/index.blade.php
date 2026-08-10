@extends('backend.layouts.app')
@section('title', 'Quản lý nghỉ phép')

@section('content')
    <main class="container-fluid container-xxl py-4 hr-page leave-page" aria-labelledby="page-title">
        <section class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 small text-secondary">
                    <a href="#" class="text-secondary text-decoration-none">Thời gian làm việc</a>
                    <span>/</span>
                    <span>Nghỉ phép</span>
                </div>
                <h1 class="h3 fw-semibold mb-1" id="page-title">Nghỉ phép</h1>
                <p class="text-secondary mb-0">Quản lý nhân viên, đơn nghỉ phép và quy trình phê duyệt.</p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-secondary btn-sm" id="calendar-btn" type="button">
                    Lịch nghỉ
                </button>
                <button class="btn btn-success btn-sm" id="create-btn" type="button" disabled>
                    + Thêm nghỉ phép
                </button>
            </div>
        </section>

        <section class="card shadow-sm mb-3 filter-card" aria-label="Bộ lọc nhân viên">
            <div class="card-body py-3">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-lg-4">
                        <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white">
                            <svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16"
                                 fill="none" stroke="currentColor" stroke-width="1.5"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="7" cy="7" r="4.5"></circle>
                                <path d="M10.5 10.5 14 14"></path>
                            </svg>
                        </span>
                            <input class="form-control" type="search" id="search-field"
                                   placeholder="Tìm mã hoặc tên nhân viên..." aria-label="Tìm nhân viên">
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <select class="form-select form-select-sm" id="department-filter" aria-label="Phòng ban">
                            <option value="">-- Tất cả phòng ban --</option>
                        </select>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <select class="form-select form-select-sm" id="position-filter" aria-label="Chức vụ">
                            <option value="">-- Tất cả chức vụ --</option>
                        </select>
                    </div>

                    <select id="period-filter" hidden aria-hidden="true"></select>
                    <select id="status-filter" hidden aria-hidden="true">
                        <option value=""></option>
                        <option value="pending">Chờ duyệt</option>
                        <option value="approved">Đã duyệt</option>
                        <option value="rejected">Từ chối</option>
                    </select>

                    <div class="col-12 col-lg-2 text-lg-end">
                        <button class="btn btn-outline-secondary btn-sm w-100" id="clear-filter-btn" type="button">
                            Xóa lọc
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="card shadow-sm overflow-hidden mb-3" aria-labelledby="employee-table-title">
            <div class="card-header bg-white d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 py-3">
                <div>
                    <h2 class="h6 fw-semibold mb-1" id="employee-table-title">Danh sách nhân viên</h2>
                    <p class="small text-secondary mb-0" id="employee-table-description">
                        Chọn một nhân viên để xem và xử lý dữ liệu nghỉ phép.
                    </p>
                </div>
                <span class="badge text-bg-light border fw-normal" id="selected-employee-badge">
                Chưa chọn nhân viên
            </span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="min-width:1120px;">
                    <thead class="table-light">
                    <tr>
                        <th style="width:42px;"></th>
                        <th>Mã NV</th>
                        <th>Họ tên</th>
                        <th>Giới tính</th>
                        <th>Số điện thoại</th>
                        <th>Email</th>
                        <th>Phòng ban</th>
                        <th>Chức vụ</th>
                        <th>Trạng thái</th>
                    </tr>
                    </thead>
                    <tbody id="employee-tbody">
                    <tr>
                        <td colspan="9" class="text-center text-secondary py-5">
                            Đang tải danh sách nhân viên...
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 py-3">
                <span class="small text-secondary" id="employee-page-info">Hiển thị 0 trên 0 nhân viên</span>
                <nav class="pagination mb-0" id="employee-pagination" aria-label="Phân trang nhân viên"></nav>
            </div>
        </section>

        <section class="card shadow-sm overflow-hidden table-card" aria-labelledby="leave-table-title">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                    <div>
                        <h2 class="h6 fw-semibold mb-1" id="leave-table-title">Danh sách nghỉ phép</h2>
                        <p class="small text-secondary mb-0" id="leave-table-description">
                            Chọn nhân viên ở bảng phía trên để tải dữ liệu nghỉ phép.
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <button class="btn btn-outline-secondary btn-sm" id="edit-leave-btn" type="button" disabled>
                            Sửa nghỉ phép
                        </button>
                        <button class="btn btn-outline-danger btn-sm" id="delete-leave-btn" type="button" disabled>
                            Xóa nghỉ phép
                        </button>
                        <button class="btn btn-success btn-sm" id="approve-leave-btn" type="button" disabled>
                            Duyệt nghỉ phép
                        </button>
                    </div>
                </div>

                <div class="mt-3">
                    <ul class="nav nav-tabs" id="leave-tabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="pending-tab" type="button"
                                    role="tab" aria-selected="true" data-tab="pending">
                                Chờ duyệt
                                <span class="badge text-bg-warning ms-1" id="pending-count">0</span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="history-tab" type="button"
                                    role="tab" aria-selected="false" data-tab="history">
                                Lịch sử nghỉ phép
                                <span class="badge text-bg-secondary ms-1" id="history-count">0</span>
                            </button>
                        </li>
                    </ul>

                    <span id="approved-count" hidden>0</span>
                    <span id="approved-days" hidden></span>
                    <span id="today-count" hidden>0</span>
                    <span id="approval-rate" hidden>0%</span>
                    <span id="overdue-help" hidden></span>
                </div>
            </div>

            <div class="table-responsive table-scroll">
                <table class="table table-hover align-middle mb-0 data-table" style="min-width:980px;">
                    <thead class="table-light">
                    <tr>
                        <th style="width:42px;"></th>
                        <th>Mã nghỉ phép</th>
                        <th>Mã nhân viên</th>
                        <th>Từ ngày</th>
                        <th>Đến ngày</th>
                        <th>Loại phép</th>
                        <th>Lý do</th>
                        <th>Trạng thái duyệt</th>
                    </tr>
                    </thead>
                    <tbody id="leave-tbody">
                    <tr class="empty-row">
                        <td colspan="8" class="text-center text-secondary py-5">Chưa chọn nhân viên.</td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 py-3">
                <span class="small text-secondary" id="page-info">Hiển thị 0 trên 0 yêu cầu</span>
                <nav class="pagination mb-0" id="pagination" aria-label="Phân trang nghỉ phép"></nav>
            </div>
        </section>
    </main>

    <dialog
        class="leave-dialog"
        id="leave-modal"
        aria-labelledby="leave-modal-title"
    >
        <form
            class="leave-modal-form"
            id="leave-form"
        >
            {{-- Header --}}
            <div class="d-flex align-items-start justify-content-between gap-3 px-4 pt-4 pb-3 border-bottom">
                <div>
                    <h2 class="h5 fw-semibold mb-1" id="leave-modal-title">
                        Thêm nghỉ phép
                    </h2>

                    <p class="small text-secondary mb-0" id="leave-modal-description">
                        Tạo đơn nghỉ phép cho nhân viên đang chọn.
                    </p>
                </div>

                <button
                    class="btn-close"
                    id="leave-modal-close"
                    type="button"
                    aria-label="Đóng"
                ></button>
            </div>

            {{-- Message --}}
            <div
                class="alert alert-danger mx-4 mt-3 mb-0"
                id="leave-modal-message"
                hidden
            ></div>

            {{-- Body --}}
            <div class="row g-3 p-4 leave-modal-body">

                {{-- Mã nghỉ phép --}}
                <div class="col-12 col-md-6">
                    <label
                        class="form-label fw-semibold"
                        for="leave-id"
                    >
                        Mã nghỉ phép
                    </label>

                    <input
                        class="form-control form-control-sm"
                        id="leave-id"
                        type="text"
                        readonly
                        placeholder="Tự động"
                    >
                </div>

                {{-- Mã nhân viên --}}
                <div class="col-12 col-md-6">
                    <label
                        class="form-label fw-semibold"
                        for="leave-employee-code"
                    >
                        Mã nhân viên
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        class="form-control form-control-sm"
                        id="leave-employee-code"
                        type="text"
                        readonly
                        required
                    >
                </div>

                {{-- Từ ngày --}}
                <div class="col-12 col-md-6">
                    <label
                        class="form-label fw-semibold"
                        for="leave-from-date"
                    >
                        Từ ngày
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        class="form-control form-control-sm"
                        id="leave-from-date"
                        type="text"
                        required
                    >
                </div>

                {{-- Đến ngày --}}
                <div class="col-12 col-md-6">
                    <label
                        class="form-label fw-semibold"
                        for="leave-to-date"
                    >
                        Đến ngày
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        class="form-control form-control-sm"
                        id="leave-to-date"
                        type="text"
                        required
                    >
                </div>

                {{-- Loại phép --}}
                <div class="col-12">
                    <label
                        class="form-label fw-semibold"
                        for="leave-type"
                    >
                        Loại phép
                        <span class="text-danger">*</span>
                    </label>

                    <select
                        class="form-select form-select-sm"
                        id="leave-type"
                        required
                    >
                        <option value="">
                            -- Chọn loại phép --
                        </option>
                    </select>
                </div>

                {{-- Lý do --}}
                <div class="col-12">
                    <label
                        class="form-label fw-semibold"
                        for="leave-reason"
                    >
                        Lý do
                    </label>

                    <textarea
                        class="form-control form-control-sm"
                        id="leave-reason"
                        rows="3"
                        maxlength="255"
                        placeholder="Nhập lý do nghỉ..."
                    ></textarea>
                </div>
            </div>

            {{-- Footer --}}
            <div class="d-flex justify-content-end gap-2 px-4 py-3 border-top bg-light">
                <button
                    class="btn btn-outline-secondary btn-sm"
                    id="leave-modal-cancel"
                    type="button"
                >
                    Hủy
                </button>

                <button
                    class="btn btn-success btn-sm"
                    id="leave-modal-submit"
                    type="submit"
                >
                    Lưu thông tin
                </button>
            </div>
        </form>
    </dialog>

    <div class="toast" role="status" aria-live="polite"></div>
@endsection

@push('scripts')
    @vite('resources/js/frontend/nghiphep/nghiphep.js')
@endpush
