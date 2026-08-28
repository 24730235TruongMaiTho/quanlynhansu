@extends('backend.layouts.app')
@section('title', 'Quản lý chấm công')

@section('content')
    <main class="container-fluid container-xxl py-4 attendance-page" aria-labelledby="page-title">
        <section class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 small text-secondary">
                    <a href="#" class="text-secondary text-decoration-none">Thời gian làm việc</a>
                    <span>/</span><span>Chấm công</span>
                </div>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <h1 class="h3 fw-semibold mb-1" id="page-title">Chấm công</h1>
                    <span
                        class="badge rounded-pill text-bg-light border"
                        id="attendance-readonly-badge"
                        hidden
                    >
                        Chế độ chỉ xem
                    </span>
                </div>
                <p class="text-secondary mb-0">Theo dõi ngày công, số giờ làm, vào muộn và về sớm theo từng nhân viên.</p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <button
                    class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2"
                    id="import-btn"
                    type="button"
                    data-attendance-permission="ChamCong.Insert"
                    hidden
                    title="Nhập bảng chấm công"
                >
                    <svg
                        aria-hidden="true"
                        width="15"
                        height="15"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M8 14V6"></path>
                        <path d="m4.8 8.8 3.2-3.2 3.2 3.2"></path>
                        <path d="M3 2h10"></path>
                    </svg>

                    <span>Nhập bảng chấm công</span>
                </button>
                <button
                    class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2"
                    id="export-btn"
                    type="button"
                    data-attendance-permission="ChamCong.Read"
                    hidden
                    title="Xuất bảng chấm công"
                >
                    <svg
                        aria-hidden="true"
                        width="15"
                        height="15"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M8 2v8"></path>
                        <path d="m4.8 7.2 3.2 3.2 3.2-3.2"></path>
                        <path d="M3 14h10"></path>
                    </svg>

                    <span>Xuất bảng chấm công</span>
                </button>
                <button
                    class="btn btn-primary btn-sm"
                    id="update-btn"
                    type="button"
                    disabled
                    data-attendance-permission="ChamCong.Update"
                    hidden
                >
                    Cập nhật chấm công
                </button>

                <button
                    class="btn btn-outline-danger btn-sm"
                    id="delete-btn"
                    type="button"
                    disabled
                    data-attendance-permission="ChamCong.Delete"
                    hidden
                >
                    Xóa chấm công
                </button>
            </div>
        </section>

        <section
            class="alert alert-light border shadow-sm mb-3"
            id="attendance-auth-loading"
        >
            <div class="d-flex align-items-center gap-2">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <span>Đang kiểm tra tài khoản và quyền truy cập...</span>
            </div>
        </section>

        <section
            class="alert alert-danger border shadow-sm mb-3"
            id="attendance-access-denied"
            hidden
        >
            <div class="fw-semibold mb-1">Không có quyền truy cập Chấm công</div>
            <div class="small" id="attendance-access-denied-message">
                Tài khoản hiện tại chưa được cấp quyền phù hợp.
            </div>
        </section>

        <section
            class="alert alert-info border shadow-sm mb-3"
            id="attendance-no-read-notice"
            hidden
        >
            <div class="fw-semibold mb-1">Danh sách chấm công đang được ẩn</div>
            <div class="small">
                Tài khoản chưa có quyền <code>ChamCong.Read</code>.
            </div>
        </section>

        <section
            class="card shadow-sm mb-3"
            aria-label="Bộ lọc chấm công"
            data-attendance-permission="ChamCong.Read"
            hidden
        >
            <div class="card-body py-3">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-xl-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white">⌕</span>
                            <input class="form-control" type="search" id="search-field"
                                   placeholder="Tìm mã hoặc tên nhân viên..." aria-label="Tìm nhân viên">
                        </div>
                    </div>

                    <div class="col-12 col-lg-auto">
                        <div
                            class="attendance-period-picker d-flex align-items-center flex-nowrap gap-2"
                        >
                            <label
                                class="text-secondary fw-semibold mb-0 text-nowrap"
                                for="month-filter"
                            >
                                Kỳ chấm công
                            </label>

                            <select
                                class="form-select form-select-sm attendance-month-select"
                                id="month-filter"
                                aria-label="Tháng chấm công"
                            ></select>

                            <input
                                class="form-control form-control-sm attendance-year-input"
                                id="year-filter"
                                type="search"
                                inputmode="numeric"
                                maxlength="4"
                                placeholder="Năm"
                                aria-label="Năm chấm công"
                            >
                        </div>
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

        <section
            class="card shadow-sm overflow-hidden mb-3"
            aria-labelledby="employee-table-title"
            data-attendance-permission="ChamCong.Read"
            hidden
        >
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

        <section
            class="card shadow-sm overflow-hidden table-card"
            aria-labelledby="table-title"
            data-attendance-permission="ChamCong.Read"
            hidden
        >
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

    <dialog
        class="attendance-import-dialog"
        id="attendance-import-dialog"
        aria-labelledby="attendance-import-dialog-title"
    >
        <div class="attendance-import-modal">
            <div
                class="d-flex align-items-start justify-content-between
                       gap-3 px-4 pt-4 pb-3 border-bottom"
            >
                <div>
                    <h2
                        class="h5 fw-semibold mb-1"
                        id="attendance-import-dialog-title"
                    >
                        Nhập bảng chấm công
                    </h2>

                    <p class="small text-secondary mb-0">
                        Tải file mẫu hoặc nhập dữ liệu chấm công từ Excel/CSV.
                    </p>
                </div>

                <button
                    class="btn-close"
                    id="attendance-import-close"
                    type="button"
                    aria-label="Đóng"
                ></button>
            </div>

            <div
                class="alert alert-danger mx-4 mt-3 mb-0"
                id="attendance-import-message"
                hidden
            ></div>

            <div
                class="alert alert-success mx-4 mt-3 mb-0"
                id="attendance-import-success"
                hidden
            ></div>

            <div class="p-4">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <button
                            class="attendance-import-option w-100 text-start"
                            id="download-import-template-btn"
                            type="button"
                        >
                            <span class="attendance-import-option-icon">
                                <svg
                                    aria-hidden="true"
                                    width="24"
                                    height="24"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M6 2.75h8l4 4V21H6z"></path>
                                    <path d="M14 2.75V7h4"></path>
                                    <path d="M12 10v6"></path>
                                    <path d="m9.5 13.5 2.5 2.5 2.5-2.5"></path>
                                </svg>
                            </span>

                            <span class="d-block fw-semibold mb-1">
                                Tải file mẫu
                            </span>

                            <span class="d-block small text-secondary">
                                Tải template Excel đúng cấu trúc để nhập chấm công.
                            </span>

                            <span
                                class="d-inline-flex align-items-center gap-1
                                       small fw-semibold mt-3 text-primary"
                                id="download-import-template-label"
                            >
                                Tải Excel mẫu
                                <span aria-hidden="true">→</span>
                            </span>
                        </button>
                    </div>

                    <div class="col-12 col-md-6">
                        <button
                            class="attendance-import-option w-100 text-start"
                            id="choose-import-file-btn"
                            type="button"
                        >
                            <span class="attendance-import-option-icon">
                                <svg
                                    aria-hidden="true"
                                    width="24"
                                    height="24"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M4 17.5V20h16v-2.5"></path>
                                    <path d="M12 16V4"></path>
                                    <path d="m7.5 8.5 4.5-4.5 4.5 4.5"></path>
                                </svg>
                            </span>

                            <span class="d-block fw-semibold mb-1">
                                Nhập file chấm công
                            </span>

                            <span class="d-block small text-secondary">
                                Chọn file XLSX, XLS hoặc CSV tối đa 5MB.
                            </span>

                            <span
                                class="d-inline-flex align-items-center gap-1
                                       small fw-semibold mt-3 text-primary"
                            >
                                Chọn file
                                <span aria-hidden="true">→</span>
                            </span>
                        </button>
                    </div>
                </div>

                <div
                    class="attendance-import-selected mt-3"
                    id="attendance-import-selected"
                    hidden
                >
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <span class="attendance-import-file-icon">
                                <svg
                                    aria-hidden="true"
                                    width="20"
                                    height="20"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M6 2.75h8l4 4V21H6z"></path>
                                    <path d="M14 2.75V7h4"></path>
                                </svg>
                            </span>

                            <div class="min-w-0">
                                <div
                                    class="fw-semibold text-truncate"
                                    id="attendance-import-file-name"
                                >
                                    Chưa chọn file
                                </div>

                                <div
                                    class="small text-secondary"
                                    id="attendance-import-file-size"
                                ></div>
                            </div>
                        </div>

                        <button
                            class="btn btn-sm btn-link text-danger text-decoration-none"
                            id="attendance-import-remove-file"
                            type="button"
                        >
                            Bỏ file
                        </button>
                    </div>
                </div>
            </div>

            <div
                class="d-flex justify-content-end gap-2
                       px-4 py-3 border-top bg-light"
            >
                <button
                    class="btn btn-outline-secondary btn-sm"
                    id="attendance-import-cancel"
                    type="button"
                >
                    Đóng
                </button>

                <button
                    class="btn btn-primary btn-sm d-inline-flex
                           align-items-center gap-2"
                    id="attendance-import-submit"
                    type="button"
                    disabled
                >
                    <svg
                        aria-hidden="true"
                        width="15"
                        height="15"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M8 14V6"></path>
                        <path d="m4.8 8.8 3.2-3.2 3.2 3.2"></path>
                        <path d="M3 2h10"></path>
                    </svg>

                    <span id="attendance-import-submit-label">
                        Nhập dữ liệu
                    </span>
                </button>
            </div>
        </div>
    </dialog>

    <dialog
        class="attendance-export-dialog"
        id="attendance-export-dialog"
        aria-labelledby="attendance-export-dialog-title"
    >
        <form
            class="attendance-export-form"
            id="attendance-export-form"
        >
            <div class="d-flex align-items-start justify-content-between gap-3 px-4 pt-4 pb-3 border-bottom">
                <div>
                    <h2
                        class="h5 fw-semibold mb-1"
                        id="attendance-export-dialog-title"
                    >
                        Xuất bảng chấm công
                    </h2>

                    <p class="small text-secondary mb-0">
                        Chọn tháng và năm cần xuất dữ liệu.
                    </p>
                </div>

                <button
                    class="btn-close"
                    id="attendance-export-close"
                    type="button"
                    aria-label="Đóng"
                ></button>
            </div>

            <div
                class="alert alert-danger mx-4 mt-3 mb-0"
                id="attendance-export-message"
                hidden
            ></div>

            <div class="row g-3 p-4">
                <div class="col-12 col-sm-6">
                    <label
                        class="form-label fw-semibold"
                        for="attendance-export-month"
                    >
                        Tháng
                        <span class="text-danger">*</span>
                    </label>

                    <select
                        class="form-select form-select-sm"
                        id="attendance-export-month"
                        required
                    ></select>
                </div>

                <div class="col-12 col-sm-6">
                    <label
                        class="form-label fw-semibold"
                        for="attendance-export-year"
                    >
                        Năm
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        class="form-control form-control-sm"
                        id="attendance-export-year"
                        type="number"
                        inputmode="numeric"
                        min="2000"
                        max="2100"
                        step="1"
                        placeholder="Ví dụ: 2026"
                        required
                    >
                </div>

                <div class="col-12">
                    <label
                        class="form-label fw-semibold"
                        for="attendance-export-format"
                    >
                        Định dạng
                    </label>

                    <select
                        class="form-select form-select-sm"
                        id="attendance-export-format"
                    >
                        <option value="xlsx" selected>
                            Excel (.xlsx)
                        </option>
                        <option value="csv">
                            CSV (.csv)
                        </option>
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 px-4 py-3 border-top bg-light">
                <button
                    class="btn btn-outline-secondary btn-sm"
                    id="attendance-export-cancel"
                    type="button"
                >
                    Hủy
                </button>

                <button
                    class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"
                    id="attendance-export-submit"
                    type="submit"
                >
                    <svg
                        aria-hidden="true"
                        width="15"
                        height="15"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M8 2v8"></path>
                        <path d="m4.8 7.2 3.2 3.2 3.2-3.2"></path>
                        <path d="M3 14h10"></path>
                    </svg>

                    <span id="attendance-export-submit-label">
                        Xuất file
                    </span>
                </button>
            </div>
        </form>
    </dialog>

    <input
        id="attendance-import-file"
        type="file"
        accept=".xlsx,.xls,.csv"
        hidden
    >
    <div class="toast attendance-toast" role="status" aria-live="polite"></div>

    <style>
        .attendance-page {
            --attendance-border: #d0d7de;
        }

        .attendance-page .attendance-period-picker {
            min-height: 38px;
            padding: 3px 5px 3px 10px;
            border: 1px solid var(--attendance-border);
            border-radius: 8px;
            background: #fff;
            white-space: nowrap;
        }

        .attendance-page .attendance-period-picker > label {
            margin-right: 4px;
            font-size: .75rem;
            font-weight: 600;
        }

        .attendance-page .attendance-month-select {
            width: 108px !important;
            min-width: 108px !important;
            height: 32px;
            padding: 4px 36px 4px 10px !important;
            border: 0 !important;
            box-shadow: none !important;
            background-position: right 10px center !important;
            background-size: 12px 12px !important;
            font-size: .8125rem;
            font-weight: 600;
            cursor: pointer;
        }

        .attendance-page .attendance-year-input {
            width: 78px !important;
            min-width: 78px !important;
            height: 32px;
            padding: 4px 8px;
            border: 0 !important;
            border-left: 1px solid #d8dee4 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            text-align: center;
            font-size: .8125rem;
            font-weight: 600;
        }

        .attendance-page .attendance-period-picker:focus-within {
            border-color: #0969da;
            box-shadow: 0 0 0 .2rem rgba(9,105,218,.12);
        }

        .attendance-page .attendance-employee-table {
            min-width: 1180px;
            font-size: .8125rem;
        }

        .attendance-page .attendance-detail-table {
            min-width: 980px;
            font-size: .8125rem;
        }

        .attendance-page .attendance-employee-table thead th,
        .attendance-page .attendance-detail-table thead th {
            color: #57606a;
            font-size: .75rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .attendance-page .attendance-employee-table > :not(caption) > * > *,
        .attendance-page .attendance-detail-table > :not(caption) > * > * {
            padding: 10px;
            border-bottom-color: #eaeef2;
        }

        .attendance-page [data-employee-row],
        .attendance-page [data-attendance-row] {
            cursor: pointer;
        }

        .attendance-page .attendance-edit-input {
            width: 76px;
            margin-left: auto;
        }

        .attendance-page .table-primary > * {
            --bs-table-bg-state: rgba(9,105,218,.08);
        }

        #employee-per-page,
        #attendance-per-page {
            width: 84px !important;
            min-width: 84px !important;
            padding-right: 32px;
            background-position: right .65rem center;
        }

        .attendance-page .attendance-status-badge {
            cursor: help;
        }

        @media (max-width: 767.98px) {
            .attendance-page .attendance-period-picker {
                width: 100%;
            }

            .attendance-page .attendance-month-select {
                flex: 1 1 108px;
                width: auto !important;
            }

            .attendance-page .attendance-year-input {
                flex: 0 0 82px;
            }
        }

        .attendance-export-dialog,
        .attendance-import-dialog {
            position: fixed;
            inset: 0;
            width: min(560px, calc(100vw - 32px));
            max-width: 560px;
            max-height: calc(100vh - 40px);
            margin: auto;
            padding: 0;
            border: 0;
            border-radius: 12px;
            background: #fff;
            overflow: auto;
            box-shadow:
                0 24px 48px rgba(31, 35, 40, .18),
                0 0 0 1px rgba(31, 35, 40, .08);
        }

        .attendance-import-dialog {
            width: min(660px, calc(100vw - 32px));
            max-width: 660px;
        }

        .attendance-export-dialog::backdrop,
        .attendance-import-dialog::backdrop {
            background: rgba(31, 35, 40, .42);
            backdrop-filter: blur(1px);
        }

        .attendance-export-form,
        .attendance-import-modal {
            margin: 0;
        }

        .attendance-export-dialog .form-label,
        .attendance-import-dialog .form-label {
            margin-bottom: 6px;
            font-size: .8125rem;
        }

        .attendance-export-dialog .form-control,
        .attendance-export-dialog .form-select,
        .attendance-import-dialog .form-control,
        .attendance-import-dialog .form-select {
            min-height: 36px;
        }

        .attendance-import-option {
            height: 100%;
            min-height: 190px;
            padding: 20px;
            border: 1px solid #d8dee4;
            border-radius: 10px;
            background: #fff;
            color: #24292f;
            transition:
                border-color .15s ease,
                box-shadow .15s ease,
                transform .15s ease;
        }

        .attendance-import-option:hover {
            border-color: #0969da;
            box-shadow: 0 4px 16px rgba(140, 149, 159, .16);
            transform: translateY(-1px);
        }

        .attendance-import-option:focus-visible {
            outline: 0;
            border-color: #0969da;
            box-shadow: 0 0 0 .2rem rgba(9,105,218,.12);
        }

        .attendance-import-option-icon,
        .attendance-import-file-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            margin-bottom: 14px;
            border: 1px solid #d8dee4;
            border-radius: 10px;
            background: #f6f8fa;
            color: #0969da;
        }

        .attendance-import-file-icon {
            width: 38px;
            height: 38px;
            margin-bottom: 0;
            flex: 0 0 38px;
        }

        .attendance-import-selected {
            padding: 12px 14px;
            border: 1px solid #d8dee4;
            border-radius: 9px;
            background: #f6f8fa;
        }

        .attendance-import-selected .min-w-0 {
            min-width: 0;
        }

        @media (max-width: 575.98px) {
            .attendance-export-dialog,
            .attendance-import-dialog {
                width: calc(100vw - 24px);
                max-height: calc(100vh - 24px);
            }

            .attendance-import-option {
                min-height: 160px;
            }
        }
    </style>
@endsection

@push('scripts')
    @vite('resources/js/frontend/chamcong/chamcong.js')
@endpush
