@extends('backend.layouts.app')
@section('title', 'Quản lý lương')

@section('content')
    <main
        class="container-fluid container-xxl py-4 salary-page hr-page"
        aria-labelledby="page-title"
    >
        <section
            class="page-heading d-flex flex-column flex-lg-row
               align-items-lg-start justify-content-between gap-3 mb-3"
        >
            <div>
                <nav
                    class="crumb d-flex align-items-center gap-2 mb-1
                       small text-secondary"
                    aria-label="Breadcrumb"
                >
                    <a href="#" class="text-secondary text-decoration-none">
                        Nhân sự
                    </a>
                    <span>/</span>
                    <span>Lương</span>
                </nav>

                <div class="d-flex align-items-center flex-wrap gap-2">
                    <h1 class="h3 fw-semibold mb-0" id="page-title">
                        Bảng lương
                    </h1>

                    <span
                        class="badge rounded-pill text-bg-light border"
                        id="salary-readonly-badge"
                        hidden
                    >
                    Chế độ chỉ xem
                </span>
                </div>

                <p class="text-secondary mt-1 mb-0">
                    Theo dõi và xử lý bảng lương trong phạm vi quyền được cấp.
                </p>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <button
                    class="btn btn-outline-secondary btn-sm d-inline-flex
                       align-items-center gap-2"
                    id="export-btn"
                    type="button"
                    data-salary-permission="Luong.Read"
                    hidden
                >
                    <svg aria-hidden="true" width="15" height="15" viewBox="0 0 16 16"
                         fill="none" stroke="currentColor" stroke-width="1.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 2v8"/>
                        <path d="m4.8 7.2 3.2 3.2 3.2-3.2"/>
                        <path d="M3 14h10"/>
                    </svg>
                    Xuất báo cáo
                </button>

                <button
                    class="btn btn-success btn-sm d-inline-flex
                       align-items-center gap-2"
                    id="create-salary-btn"
                    type="button"
                    data-salary-permission="Luong.Insert"
                    hidden
                >
                    <svg aria-hidden="true" width="15" height="15" viewBox="0 0 16 16"
                         fill="none" stroke="currentColor" stroke-width="1.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 3v10M3 8h10"/>
                    </svg>
                    Thêm thông tin lương
                </button>
            </div>
        </section>

        {{-- Auth loading --}}
        <section
            class="alert alert-light border shadow-sm mb-3"
            id="salary-auth-loading"
        >
            <div class="d-flex align-items-center gap-2">
                <span class="spinner-border spinner-border-sm"></span>
                <span>Đang kiểm tra tài khoản và quyền truy cập...</span>
            </div>
        </section>

        {{-- Access denied --}}
        <section
            class="alert alert-danger border shadow-sm mb-3"
            id="salary-access-denied"
            hidden
        >
            <div class="fw-semibold mb-1">
                Không có quyền truy cập module Lương
            </div>
            <div class="small" id="salary-access-denied-message">
                Tài khoản hiện tại chưa được cấp quyền phù hợp.
            </div>
        </section>

        <div id="salary-content" hidden>
            <section
                class="alert alert-info border shadow-sm mb-3"
                id="salary-no-read-notice"
                hidden
            >
                <div class="fw-semibold mb-1">
                    Danh sách bảng lương đang được ẩn
                </div>
                <div class="small">
                    Tài khoản chưa có quyền <code>Luong.Read</code>.
                </div>
            </section>

            {{-- Filters --}}
            <section class="card shadow-sm mb-3 filter-card">
                <div class="card-body py-3">
                    <div class="row g-2 align-items-center">
                        <div
                            class="col-12 col-lg-4 col-xl-3"
                            data-salary-permission="Luong.Read"
                            hidden
                        >
                            <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white">
                                <svg aria-hidden="true" width="16" height="16"
                                     viewBox="0 0 16 16" fill="none"
                                     stroke="currentColor" stroke-width="1.5"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="7" cy="7" r="4.5"/>
                                    <path d="M10.5 10.5 14 14"/>
                                </svg>
                            </span>

                                <input
                                    class="form-control"
                                    id="search-field"
                                    type="search"
                                    placeholder="Tìm mã hoặc tên nhân viên..."
                                >
                            </div>
                        </div>

                        <div
                            class="col-12 col-sm-6 col-lg-2"
                            data-salary-permission="Luong.Read"
                            hidden
                        >
                            <select
                                class="form-select form-select-sm"
                                id="department-filter"
                            >
                                <option value="">-- Tất cả phòng ban --</option>
                            </select>
                        </div>

                        <div
                            class="col-12 col-sm-6 col-lg-2"
                            data-salary-permission="Luong.Read"
                            hidden
                        >
                            <select
                                class="form-select form-select-sm"
                                id="position-filter"
                            >
                                <option value="">-- Tất cả chức vụ --</option>
                            </select>
                        </div>

                        <div class="col-12 col-lg-auto">
                            <div class="salary-period-picker d-flex align-items-center gap-2 flex-nowrap">
                                <label
                                    class="text-secondary fw-semibold mb-0 text-nowrap"
                                    for="salary-month-select"
                                >
                                    Kỳ lương
                                </label>

                                <select
                                    class="form-select form-select-sm salary-month-select"
                                    id="salary-month-select"
                                    aria-label="Tháng lương"
                                ></select>

                                <input
                                    class="form-control form-control-sm salary-year-input"
                                    id="salary-year-input"
                                    type="search"
                                    inputmode="numeric"
                                    maxlength="4"
                                    placeholder="Năm"
                                    aria-label="Năm lương"
                                >
                            </div>
                        </div>

                        <div
                            class="col-12 col-sm-auto ms-lg-auto"
                            data-salary-permission="Luong.Read"
                            hidden
                        >
                            <button
                                class="btn btn-outline-secondary btn-sm w-100"
                                id="clear-filter-btn"
                                type="button"
                            >
                                Xóa lọc
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Salary table --}}
            <section
                class="card shadow-sm overflow-hidden table-card"
                data-salary-permission="Luong.Read"
                hidden
            >
                <div
                    class="card-header bg-white d-flex flex-column flex-md-row
                       align-items-md-center justify-content-between gap-3 py-3"
                >
                    <div>
                        <h2 class="h6 fw-semibold mb-1" id="table-title">
                            Chi tiết kỳ lương
                        </h2>
                        <p class="small text-secondary mb-0" id="table-updated">
                            Đang chuẩn bị dữ liệu...
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="small text-secondary" id="table-stat">
                        Đang tải...
                    </span>

                        <button
                            class="btn btn-outline-secondary btn-sm"
                            id="reconcile-btn"
                            type="button"
                            data-salary-permission="Luong.Update"
                            hidden
                        >
                            Đối soát
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 salary-data-table">
                        <thead class="table-light">
                        <tr>
                            <th class="salary-col-employee">Nhân viên</th>
                            <th class="salary-col-position">Phòng ban / Chức vụ</th>
                            <th class="text-end salary-col-period">Kỳ lương</th>
                            <th class="text-end">Thưởng</th>
                            <th class="text-end">Phạt</th>
                            <th class="text-end">Bảo hiểm</th>
                            <th class="text-end">Thuế</th>
                            <th class="text-end">Hệ số phụ cấp</th>
                            <th class="text-end">Thực nhận</th>
                            <th class="text-end">Ngày công</th>
                            <th class="text-end">Vào muộn</th>
                            <th class="text-end">Về sớm</th>
                            <th class="salary-col-status">Trạng thái</th>
                            <th class="text-end salary-col-actions">Thao tác</th>
                        </tr>
                        </thead>

                        <tbody id="salary-tbody">
                        <tr>
                            <td colspan="14" class="text-center text-secondary py-5">
                                Đang tải dữ liệu...
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="card-footer bg-white d-flex flex-column flex-sm-row
                       align-items-sm-center justify-content-between gap-2 py-3"
                >
                    <div class="d-flex align-items-center flex-wrap gap-3">
                    <span class="small text-secondary" id="page-info">
                        Hiển thị 0 trên 0 nhân viên
                    </span>

                        <div class="d-flex align-items-center gap-2">
                            <label class="small text-secondary mb-0" for="salary-per-page">
                                Số dòng
                            </label>

                            <select
                                class="form-select form-select-sm"
                                id="salary-per-page"
                                style="width:84px"
                            >
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="15" selected>15</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>

                            <span class="small text-secondary">/ trang</span>
                        </div>
                    </div>

                    <nav id="pagination" aria-label="Phân trang bảng lương"></nav>
                </div>
            </section>

            {{-- Salary coefficient --}}
            <section
                class="card shadow-sm mt-3 overflow-hidden"
                id="salary-coefficient-card"
                data-salary-permission="HeSoLuong.Read"
                hidden
            >
                <div
                    class="card-header bg-white d-flex flex-column flex-md-row
                       align-items-md-center justify-content-between gap-3 py-3"
                >
                    <div>
                        <h2 class="h6 fw-semibold mb-1" id="salary-coefficient-title">
                            Lịch sử hệ số lương
                        </h2>

                        <p
                            class="small text-secondary mb-0"
                            id="salary-coefficient-description"
                        >
                            Chọn nút hệ số của một nhân viên để xem dữ liệu.
                        </p>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2">
                    <span
                        class="badge text-bg-light border"
                        id="coefficient-selected-employee"
                    >
                        Chưa chọn nhân viên
                    </span>

                        <button
                            class="btn btn-success btn-sm"
                            id="add-coefficient-btn"
                            type="button"
                            disabled
                            data-salary-permission="HeSoLuong.Insert"
                            hidden
                        >
                            + Thêm hệ số
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width:42px;">
                                <input
                                    class="form-check-input"
                                    id="coefficient-check-all"
                                    type="checkbox"
                                    disabled
                                >
                            </th>
                            <th>Mã lịch sử</th>
                            <th class="text-end">Hệ số lương</th>
                            <th>Từ ngày</th>
                            <th>Đến ngày</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                        </thead>

                        <tbody id="salary-coefficient-tbody">
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-5">
                                Chưa chọn nhân viên.
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="card-footer bg-white d-flex flex-column flex-sm-row
                       align-items-sm-center justify-content-between gap-2 py-3"
                >
                <span class="small text-secondary" id="coefficient-info">
                    Hiển thị 0 bản ghi
                </span>

                    <div class="d-flex align-items-center gap-2">
                        <button
                            class="btn btn-outline-secondary btn-sm"
                            id="edit-coefficient-btn"
                            type="button"
                            disabled
                            data-salary-permission="HeSoLuong.Update"
                            hidden
                        >
                            Sửa hệ số
                        </button>

                        <button
                            class="btn btn-outline-danger btn-sm"
                            id="delete-coefficient-btn"
                            type="button"
                            disabled
                            data-salary-permission="HeSoLuong.Delete"
                            hidden
                        >
                            Xóa hệ số
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <div class="toast salary-toast" role="status" aria-live="polite"></div>

    {{-- Salary modal --}}
    <dialog class="salary-dialog" id="salary-modal">
        <form id="salary-form" class="salary-modal-form">
            <header
                class="salary-modal-header d-flex align-items-start
                   justify-content-between gap-3"
            >
                <div>
                    <h2 class="h5 fw-semibold mb-1" id="salary-modal-title">
                        Thông tin lương
                    </h2>
                    <p class="small text-secondary mb-0" id="salary-modal-description">
                        Thông tin lương theo kỳ.
                    </p>
                </div>

                <button
                    class="btn-close"
                    id="salary-modal-close"
                    type="button"
                    aria-label="Đóng"
                ></button>
            </header>

            <div
                class="alert alert-danger salary-modal-message"
                id="salary-modal-message"
                hidden
            ></div>

            <div class="salary-form-grid row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="salary-id">
                        Mã lương
                    </label>
                    <input
                        class="form-control form-control-sm"
                        id="salary-id"
                        type="text"
                        readonly
                        placeholder="Tự động"
                    >
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="salary-period">
                        Kỳ lương
                    </label>
                    <input
                        class="form-control form-control-sm"
                        id="salary-period"
                        type="text"
                        readonly
                    >
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold" for="salary-employee-code">
                        Mã nhân viên
                    </label>
                    <input
                        class="form-control form-control-sm"
                        id="salary-employee-code"
                        type="text"
                        maxlength="5"
                        required
                    >
                </div>

                @foreach ([
                    ['salary-bonus', 'Thưởng'],
                    ['salary-penalty', 'Phạt'],
                    ['salary-insurance', 'Bảo hiểm'],
                    ['salary-tax', 'Thuế'],
                ] as [$id, $label])
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold" for="{{ $id }}">
                            {{ $label }}
                        </label>

                        <div class="input-group input-group-sm">
                            <input
                                class="form-control text-end"
                                id="{{ $id }}"
                                type="number"
                                min="0"
                                step="1000"
                                value="0"
                            >
                            <span class="input-group-text">₫</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <footer class="salary-modal-footer d-flex justify-content-end gap-2">
                <button
                    class="btn btn-outline-secondary btn-sm"
                    id="salary-modal-cancel"
                    type="button"
                >
                    Hủy
                </button>

                <button
                    class="btn btn-success btn-sm"
                    id="salary-modal-submit"
                    type="submit"
                >
                    Lưu thông tin
                </button>
            </footer>
        </form>
    </dialog>

    {{-- Coefficient modal --}}
    <dialog class="coefficient-dialog" id="coefficient-modal">
        <form class="coefficient-modal-form" id="coefficient-form">
            <header
                class="coefficient-modal-header d-flex align-items-start
                   justify-content-between gap-3"
            >
                <div>
                    <h2 class="h5 fw-semibold mb-1" id="coefficient-modal-title">
                        Chi tiết hệ số lương
                    </h2>
                    <p class="small text-secondary mb-0" id="coefficient-modal-description">
                        Thông tin hệ số lương.
                    </p>
                </div>

                <button
                    class="btn-close"
                    id="coefficient-modal-close"
                    type="button"
                    aria-label="Đóng"
                ></button>
            </header>

            <div
                class="alert alert-danger coefficient-modal-message"
                id="coefficient-modal-message"
                hidden
            ></div>

            <div class="row g-3 coefficient-form-body">
                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="coefficient-employee-code">
                        Mã nhân viên
                    </label>
                    <input
                        class="form-control form-control-sm"
                        id="coefficient-employee-code"
                        type="text"
                        readonly
                    >
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="coefficient-id">
                        Mã lịch sử
                    </label>
                    <input
                        class="form-control form-control-sm"
                        id="coefficient-id"
                        type="text"
                        readonly
                    >
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold" for="coefficient-employee-name">
                        Nhân viên
                    </label>
                    <input
                        class="form-control form-control-sm"
                        id="coefficient-employee-name"
                        type="text"
                        readonly
                    >
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold" for="coefficient-value">
                        Hệ số lương
                    </label>
                    <input
                        class="form-control form-control-sm"
                        id="coefficient-value"
                        type="number"
                        min="0.01"
                        max="99.99"
                        step="0.01"
                        required
                    >
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="coefficient-from-date">
                        Từ ngày
                    </label>
                    <input
                        class="form-control form-control-sm"
                        id="coefficient-from-date"
                        type="date"
                        required
                    >
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label fw-semibold" for="coefficient-to-date">
                        Đến ngày
                    </label>
                    <input
                        class="form-control form-control-sm"
                        id="coefficient-to-date"
                        type="date"
                    >
                </div>
            </div>

            <footer class="coefficient-modal-footer d-flex justify-content-end gap-2">
                <button
                    class="btn btn-outline-secondary btn-sm"
                    id="coefficient-modal-cancel"
                    type="button"
                >
                    Hủy
                </button>

                <button
                    class="btn btn-success btn-sm"
                    id="coefficient-modal-submit"
                    type="submit"
                >
                    Lưu hệ số
                </button>
            </footer>
        </form>
    </dialog>

    <style>
        /* =========================
           Salary UI refinement
        ========================== */
        .salary-page {
            --salary-action-border: #d8dee4;
            --salary-action-bg: #fff;
            --salary-action-hover: #f6f8fa;
            --salary-sticky-bg: #fff;
        }

        /* ----- Filter / kỳ lương ----- */
        .salary-page .salary-period-picker {
            min-height: 38px;
            padding: 4px 6px 4px 10px;
            border: 1px solid #d0d7de;
            border-radius: 8px;
            background: #fff;
        }

        .salary-page .salary-period-picker > label {
            margin-right: 4px;

            color: #656d76;
            font-size: 12px;
            font-weight: 600;
        }

        .salary-page .salary-month-select {
            width: 108px !important;
            min-width: 108px !important;
            height: 32px;

            padding: 4px 36px 4px 10px !important;

            background-position:
                right 10px center !important;

            background-size:
                12px 12px !important;

            border: 0 !important;
            box-shadow: none !important;

            font-size: 13px;
            font-weight: 600;

            cursor: pointer;
        }

        .salary-page .salary-year-input {
            width: 76px !important;
            min-width: 76px !important;
            height: 32px;

            padding: 4px 8px;

            border: 0 !important;
            border-left: 1px solid #d8dee4 !important;
            border-radius: 0 !important;

            box-shadow: none !important;

            text-align: center;
            font-size: 13px;
            font-weight: 600;
        }

        .salary-page .salary-period-picker {
            display: inline-flex;
            align-items: center;

            min-height: 38px;

            padding: 3px 5px 3px 10px;

            border: 1px solid #d0d7de;
            border-radius: 8px;

            background: #fff;

            white-space: nowrap;
        }

        /* ----- Table sizing ----- */
        .salary-page .table-responsive {
            scrollbar-width: thin;
        }

        .salary-page .salary-data-table {
            min-width: 1560px;
            table-layout: auto;
            font-size: .8125rem;
        }

        .salary-page .salary-data-table > :not(caption) > * > * {
            padding: 12px 10px;
            vertical-align: middle;
            border-bottom-color: #eaeef2;
        }

        .salary-page .salary-data-table thead th {
            height: 42px;
            color: #57606a;
            font-size: .75rem;
            font-weight: 700;
            white-space: nowrap;
            background: #f6f8fa;
        }

        .salary-page .salary-data-table tbody td {
            white-space: nowrap;
        }

        .salary-page .salary-col-employee {
            min-width: 210px;
            width: 210px;
        }

        .salary-page .salary-col-position {
            min-width: 165px;
            width: 165px;
        }

        .salary-page .salary-col-period {
            min-width: 82px;
        }

        .salary-page .salary-col-status {
            min-width: 235px;
        }

        .salary-page .salary-col-actions {
            min-width: 164px;
            width: 164px;
        }

        /* sticky first + last column for easier use */
        .salary-page .salary-data-table th:first-child,
        .salary-page .salary-data-table td:first-child {
            position: sticky;
            left: 0;
            z-index: 2;
            background: var(--salary-sticky-bg);
        }

        .salary-page .salary-data-table thead th:first-child {
            z-index: 4;
            background: #f6f8fa;
        }

        .salary-page .salary-data-table th:last-child,
        .salary-page .salary-data-table td:last-child {
            position: sticky;
            right: 0;
            z-index: 3;
            background: var(--salary-sticky-bg);
            box-shadow: -8px 0 12px -12px rgba(31,35,40,.45);
        }

        .salary-page .salary-data-table thead th:last-child {
            z-index: 4;
            background: #f6f8fa;
        }

        .salary-page .salary-data-table tbody tr:hover td:first-child,
        .salary-page .salary-data-table tbody tr:hover td:last-child {
            background: #f6f8fa;
        }

        /* employee cell */
        .salary-page .employee {
            min-width: 0 !important;
            gap: 9px;
        }

        .salary-page .employee-name {
            max-width: 148px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 650;
        }

        .salary-page .avatar {
            width: 30px;
            height: 30px;
            flex: 0 0 30px;
            font-size: .7rem;
        }

        /* ----- status ----- */
        .salary-page .salary-data-table .badge {
            max-width: 225px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding: 5px 9px;
            font-size: .69rem;
            font-weight: 600;
        }

        /* ----- action toolbar ----- */
        .salary-page .salary-row-actions,
        .salary-page .coefficient-row-actions {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
            padding: 3px;
            border: 1px solid #e1e5ea;
            border-radius: 10px;
            background: #f8f9fb;
            white-space: nowrap;
        }

        .salary-page .salary-icon-action,
        .salary-page .coefficient-icon-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            min-width: 30px;
            padding: 0 !important;
            border: 0 !important;
            border-radius: 7px !important;
            background: transparent !important;
            color: #57606a;
            box-shadow: none !important;
            transition:
                background-color .15s ease,
                color .15s ease,
                transform .15s ease;
        }

        .salary-page .salary-icon-action:hover,
        .salary-page .coefficient-icon-action:hover {
            transform: translateY(-1px);
            background: #fff !important;
            color: #0969da;
            box-shadow: 0 1px 3px rgba(31,35,40,.12) !important;
        }

        .salary-page .salary-icon-action[data-salary-action="edit"],
        .salary-page .coefficient-icon-action[data-coefficient-action="edit"] {
            color: #0969da;
        }

        .salary-page .salary-icon-action[data-salary-action="delete"],
        .salary-page .coefficient-icon-action[data-coefficient-action="delete"] {
            color: #cf222e;
        }

        .salary-page .salary-icon-action[data-salary-action="delete"]:hover,
        .salary-page .coefficient-icon-action[data-coefficient-action="delete"]:hover {
            background: #ffebe9 !important;
            color: #a40e26;
        }

        .salary-page .salary-icon-action[data-salary-action="coefficient"] {
            color: #8250df;
        }

        .salary-page .salary-icon-action svg,
        .salary-page .coefficient-icon-action svg {
            width: 15px;
            height: 15px;
            pointer-events: none;
        }

        .salary-page .salary-row-selected > *,
        .salary-page .coefficient-row-selected > * {
            background: rgba(9,105,218,.06) !important;
        }

        /* responsive */
        @media (max-width: 1199.98px) {
            .salary-page .salary-data-table {
                min-width: 1500px;
            }
        }

        @media (max-width: 767.98px) {
            .salary-page .salary-period-picker {
                width: 100%;
            }

            .salary-page .salary-month-select {
                flex: 1 1 96px;
                width: auto !important;
            }

            .salary-page .salary-year-input {
                flex: 0 0 82px;
            }
        }
    </style>
@endsection

@push('scripts')
    @vite([
        'resources/js/frontend/luong/luong.js',
        'resources/js/frontend/luong/luongCreateUpdate.js',
        'resources/js/frontend/luong/luongHeSo.js',
        'resources/js/frontend/luong/luongHeSoCreateUpdate.js'
    ])
@endpush
