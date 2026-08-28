@extends('backend.layouts.app')

@section('title', 'Tạo đơn nghỉ phép')

@section('content')
    <main class="container-fluid container-xxl py-4 leave-create-page" aria-labelledby="page-title">
        <section class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3 mb-4">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1 small text-secondary">
                    <a href="{{ url('/user/nghi-phep') }}" class="text-secondary text-decoration-none">Nghỉ phép</a>
                    <span>/</span>
                    <span>Tạo đơn</span>
                </div>

                <h1 class="h3 fw-semibold mb-1" id="page-title">Đơn xin nghỉ phép</h1>
                <p class="text-secondary mb-0">
                    Tạo yêu cầu nghỉ phép cho tài khoản đang đăng nhập.
                </p>
            </div>

            <a href="{{ url('/user/nghi-phep') }}"
               class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
                <svg aria-hidden="true" width="15" height="15" viewBox="0 0 16 16"
                     fill="none" stroke="currentColor" stroke-width="1.5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.5 3.5 6 8l4.5 4.5"/>
                </svg>
                Quay lại
            </a>
        </section>

        <section class="alert alert-light border shadow-sm mb-3" id="leave-create-auth-loading">
            <div class="d-flex align-items-center gap-2">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <span>Đang kiểm tra tài khoản và quyền tạo đơn...</span>
            </div>
        </section>

        <section class="alert alert-danger border shadow-sm mb-3"
                 id="leave-create-access-denied"
                 hidden>
            <div class="fw-semibold mb-1">Không thể tạo đơn nghỉ phép</div>
            <div class="small" id="leave-create-access-denied-message">
                Tài khoản hiện tại chưa có quyền NghiPhep.Insert.
            </div>
        </section>

        <section class="card shadow-sm leave-create-card"
                 id="leave-create-content"
                 hidden>
            <div class="card-header bg-white px-4 py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="leave-create-icon">
                        <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="1.7"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M7 3h10v4H7z"/>
                            <path d="M5 5v16h14V5"/>
                            <path d="M8 11h8M8 15h8"/>
                        </svg>
                    </div>

                    <div>
                        <h2 class="h6 fw-semibold mb-1" id="leave-form-title">Thông tin đơn nghỉ phép</h2>
                        <p class="small text-secondary mb-0" id="leave-form-description">
                            Các trường có dấu <span class="text-danger">*</span> là bắt buộc.
                        </p>
                    </div>
                </div>
            </div>

            <form id="leave-create-form" novalidate>
                <input id="leave-edit-id" type="hidden">
                <div class="card-body p-4">
                    <div class="alert alert-danger mb-4"
                         id="leave-create-message"
                         hidden></div>

                    <div class="alert alert-success mb-4"
                         id="leave-create-success"
                         hidden></div>

                    <div class="row g-4">
                        <div class="col-12 col-lg-5">
                            <div class="leave-create-section">
                                <div class="leave-create-section-title">
                                    Thông tin nhân viên
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold"
                                           for="leave-employee-code">
                                        Mã nhân viên
                                    </label>

                                    <input class="form-control"
                                           id="leave-employee-code"
                                           type="text"
                                           readonly
                                           aria-readonly="true">

                                    <div class="form-text">
                                        Tự động lấy từ tài khoản đang đăng nhập.
                                    </div>
                                </div>

                                <div class="mb-0">
                                    <label class="form-label fw-semibold"
                                           for="leave-department">
                                        Phòng ban
                                    </label>

                                    <input
                                        class="form-control"
                                        id="leave-department"
                                        type="text"
                                        readonly
                                        aria-readonly="true"
                                        placeholder="Đang tải phòng ban..."
                                    >

                                    <input
                                        id="leave-department-code"
                                        type="hidden"
                                    >

                                    <div class="form-text">
                                        Tự động lấy theo thông tin nhân viên đang đăng nhập.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-lg-7">
                            <div class="leave-create-section">
                                <div class="leave-create-section-title">
                                    Nội dung nghỉ phép
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold"
                                           for="leave-type">
                                        Loại nghỉ
                                        <span class="text-danger">*</span>
                                    </label>

                                    <select class="form-select"
                                            id="leave-type"
                                            required>
                                        <option value="">-- Chọn loại nghỉ --</option>
                                    </select>

                                    <div class="invalid-feedback">
                                        Vui lòng chọn loại nghỉ.
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold"
                                           for="leave-reason">
                                        Lý do nghỉ
                                        <span class="text-danger">*</span>
                                    </label>

                                    <textarea class="form-control"
                                              id="leave-reason"
                                              rows="5"
                                              maxlength="255"
                                              placeholder="Nhập lý do nghỉ..." required></textarea>

                                    <div class="form-text text-end"
                                         id="leave-reason-counter">
                                        0 / 255
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold"
                                               for="leave-from-date">
                                            Ngày bắt đầu
                                            <span class="text-danger">*</span>
                                        </label>

                                        <input class="form-control"
                                               id="leave-from-date"
                                               type="date"
                                               required>

                                        <div class="invalid-feedback">
                                            Vui lòng chọn ngày bắt đầu.
                                        </div>
                                    </div>

                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold"
                                               for="leave-to-date">
                                            Ngày kết thúc
                                            <span class="text-danger">*</span>
                                        </label>

                                        <input class="form-control"
                                               id="leave-to-date"
                                               type="date"
                                               required>

                                        <div class="invalid-feedback">
                                            Vui lòng chọn ngày kết thúc.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light d-flex flex-column flex-sm-row
                        align-items-sm-center justify-content-between gap-3 px-4 py-3">
                    <div class="small text-secondary">
                        Đơn mới sẽ được tạo với trạng thái
                        <span class="fw-semibold">Chờ duyệt</span>.
                    </div>

                    <div class="d-flex gap-2">
                        <button
                            class="btn btn-outline-secondary btn-sm"
                            id="leave-create-clear"
                            type="button"
                        >
                            Làm mới
                        </button>

                        <button class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"
                                id="leave-create-submit"
                                type="submit">
                            <svg aria-hidden="true" width="15" height="15" viewBox="0 0 16 16"
                                 fill="none" stroke="currentColor" stroke-width="1.5"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <path d="m3 8 3 3 7-7"/>
                            </svg>

                            <span id="leave-create-submit-label">
                            Gửi đơn nghỉ phép
                        </span>
                        </button>
                    </div>
                </div>
            </form>
        </section>


        {{-- =====================================================
             LEAVE LOG OF CURRENT USER
        ====================================================== --}}
        <section
            class="card shadow-sm leave-create-log-card mt-4"
            id="leave-create-log-card"
            hidden
        >
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                    <div>
                        <h2 class="h6 fw-semibold mb-1">
                            Danh sách nghỉ phép của tôi
                        </h2>
                        <p class="small text-secondary mb-0">
                            Theo dõi các đơn nghỉ phép đã tạo bằng tài khoản hiện tại.
                        </p>
                    </div>

                    <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                        <span class="badge text-bg-light border fw-normal"
                              id="leave-create-log-employee">
                            —
                        </span>

                        <div class="leave-log-date-filter d-flex flex-column flex-md-row align-items-md-center gap-2">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white text-secondary fw-semibold">
                                    Từ ngày
                                </span>

                                <input
                                    class="form-control"
                                    id="leave-log-from-date"
                                    type="date"
                                    aria-label="Lọc từ ngày"
                                >
                            </div>

                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white text-secondary fw-semibold">
                                    Đến ngày
                                </span>

                                <input
                                    class="form-control"
                                    id="leave-log-to-date"
                                    type="date"
                                    aria-label="Lọc đến ngày"
                                >
                            </div>

                            <button
                                class="btn btn-outline-secondary btn-sm text-nowrap"
                                id="leave-log-clear-filter"
                                type="button"
                            >
                                Xóa lọc
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <ul class="nav nav-tabs" id="leave-create-log-tabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active"
                                    id="leave-create-pending-tab"
                                    type="button"
                                    role="tab"
                                    aria-selected="true"
                                    data-tab="pending">
                                Chờ duyệt
                                <span class="badge text-bg-warning ms-1"
                                      id="leave-create-pending-count">0</span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link"
                                    id="leave-create-history-tab"
                                    type="button"
                                    role="tab"
                                    aria-selected="false"
                                    data-tab="history">
                                Đã xử lý
                                <span class="badge text-bg-secondary ms-1"
                                      id="leave-create-history-count">0</span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 leave-create-log-table">
                    <thead class="table-light">
                    <tr>
                        <th>Mã nghỉ phép</th>
                        <th>Từ ngày</th>
                        <th>Đến ngày</th>
                        <th>Loại phép</th>
                        <th>Lý do</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                    </thead>
                    <tbody id="leave-create-log-tbody">
                    <tr>
                        <td colspan="7" class="text-center text-secondary py-5">
                            Đang tải dữ liệu nghỉ phép...
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 py-3">
                <span class="small text-secondary"
                      id="leave-create-log-page-info">
                    Hiển thị 0 yêu cầu
                </span>

                <button class="btn btn-outline-secondary btn-sm"
                        id="leave-create-log-refresh"
                        type="button">
                    Làm mới
                </button>
            </div>
        </section>

    </main>

    <style>
        .leave-create-page .leave-create-card {
            max-width: 1040px;
            margin: 0 auto;
            border-color: #d8dee4;
            border-radius: 12px;
            overflow: hidden;
        }

        .leave-create-page .leave-create-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border: 1px solid #d8dee4;
            border-radius: 10px;
            background: #f6f8fa;
            color: #0969da;
        }

        .leave-create-page .leave-create-section {
            height: 100%;
            padding: 20px;
            border: 1px solid #eaeef2;
            border-radius: 10px;
            background: #fff;
        }

        .leave-create-page .leave-create-section-title {
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eaeef2;
            color: #24292f;
            font-size: .875rem;
            font-weight: 700;
        }

        .leave-create-page .form-label {
            margin-bottom: 6px;
            font-size: .8125rem;
        }

        .leave-create-page .form-control,
        .leave-create-page .form-select {
            min-height: 40px;
            border-color: #d0d7de;
            border-radius: 8px;
            font-size: .875rem;
        }

        .leave-create-page textarea.form-control {
            min-height: 132px;
            resize: vertical;
        }

        .leave-create-page .form-control:focus,
        .leave-create-page .form-select:focus {
            border-color: #0969da;
            box-shadow: 0 0 0 .2rem rgba(9,105,218,.12);
        }

        .leave-create-page .form-control[readonly] {
            background: #f6f8fa;
            color: #57606a;
            cursor: default;
        }

        .leave-create-page .card-footer {
            border-top-color: #eaeef2;
        }

        @media (max-width: 991.98px) {
            .leave-create-page .leave-create-card {
                max-width: 760px;
            }
        }


        .leave-create-page .leave-create-log-card {
            max-width: 1040px;
            margin-left: auto;
            margin-right: auto;
            border-color: #d8dee4;
            border-radius: 12px;
            overflow: hidden;
        }

        .leave-create-page .leave-create-log-table {
            min-width: 850px;
            font-size: .8125rem;
        }

        .leave-create-page .leave-create-log-table thead th {
            color: #57606a;
            font-size: .75rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .leave-create-page .leave-create-log-table > :not(caption) > * > * {
            padding: 10px;
            border-bottom-color: #eaeef2;
            vertical-align: middle;
        }

        .leave-create-page .leave-create-log-reason {
            display: block;
            max-width: 260px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: help;
        }

        .leave-create-page .leave-create-log-status {
            cursor: help;
        }

        .leave-create-page #leave-create-log-tabs .nav-link {
            color: #57606a;
            font-size: .8125rem;
            font-weight: 600;
        }

        .leave-create-page #leave-create-log-tabs .nav-link.active {
            color: #0969da;
        }


        .leave-create-page #leave-department[readonly] {
            background: #f6f8fa;
            color: #57606a;
            cursor: default;
        }


        .leave-create-page .leave-log-filter-group {
            width: auto;
            min-width: 310px;
            flex-wrap: nowrap;
        }

        .leave-create-page .leave-log-filter-group .input-group-text {
            font-size: .75rem;
            white-space: nowrap;
        }

        .leave-create-page .leave-log-month {
            width: 110px;
            min-width: 110px;
            flex: 0 0 110px;
            font-size: .8125rem;
            font-weight: 600;
        }

        .leave-create-page .leave-log-year {
            width: 78px;
            min-width: 78px;
            flex: 0 0 78px;
            text-align: center;
            font-size: .8125rem;
            font-weight: 600;
        }

        .leave-create-page .leave-log-edit-btn {
            white-space: nowrap;
        }

        @media (max-width: 767.98px) {
            .leave-create-page .leave-log-filter-group {
                width: 100%;
                min-width: 0;
            }

            .leave-create-page .leave-log-month {
                flex: 1 1 110px;
                width: auto;
            }

            .leave-create-page .leave-log-year {
                flex: 0 0 82px;
            }
        }


        .leave-create-page .leave-log-date-filter {
            flex: 0 1 auto;
        }

        .leave-create-page .leave-log-date-filter .input-group {
            width: 195px;
        }

        .leave-create-page .leave-log-date-filter .input-group-text {
            min-width: 68px;
            justify-content: center;
            font-size: .75rem;
            white-space: nowrap;
        }

        .leave-create-page .leave-log-date-filter .form-control {
            min-width: 120px;
            font-size: .8125rem;
        }

        @media (max-width: 767.98px) {
            .leave-create-page .leave-log-date-filter {
                width: 100%;
            }

            .leave-create-page .leave-log-date-filter .input-group {
                width: 100%;
            }
        }

    </style>
@endsection

@push('scripts')
    @vite('resources/js/frontend/nghiphep/create.js')
@endpush
