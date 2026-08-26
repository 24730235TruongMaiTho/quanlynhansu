@extends('backend.layouts.app')
@section('title', 'Quản lý lương')

@section('content')
    <main class="container-fluid container-xxl py-4 salary-page hr-page" aria-labelledby="page-title"
        data-luong-can-create="{{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\LuongPermission::Tao->value) ? '1' : '0' }}"
        data-luong-can-update="{{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\LuongPermission::Sua->value) ? '1' : '0' }}"
        data-luong-can-delete="{{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\LuongPermission::Xoa->value) ? '1' : '0' }}">
        {{-- Page heading --}}
        <section class="page-heading d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3 mb-4">
            <div>
                <nav class="crumb d-flex align-items-center gap-2 mb-1 small text-secondary" aria-label="Breadcrumb">
                    <a href="#" class="text-secondary text-decoration-none">Nhân sự</a>
                    <span aria-hidden="true">/</span>
                    <span>Lương</span>
                </nav>

                <h1 class="page-title h3 fw-semibold mb-1" id="page-title">Bảng lương</h1>
                <p class="page-desc text-secondary mb-0">
                    Theo dõi, đối soát và hoàn tất bảng lương theo từng kỳ.
                </p>
            </div>

            <div class="page-actions d-flex flex-wrap align-items-center gap-2">
                <button
                    class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2"
                    id="export-btn"
                    type="button"
                >
                    <svg
                        aria-hidden="true"
                        width="16"
                        height="16"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M8 2v8"/>
                        <path d="m4.8 7.2 3.2 3.2 3.2-3.2"/>
                        <path d="M3 14h10"/>
                    </svg>
                    Xuất báo cáo
                </button>

                <button
                    class="btn btn-success btn-sm"
                    id="create-salary-btn"
                    type="button"
                    {{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\LuongPermission::Tao->value) ? '' : 'disabled' }}
                >
                    + Thêm thông tin lương
                </button>
            </div>
        </section>

        {{--
        <section class="row g-3 mb-3 stats" aria-label="Tổng quan bảng lương">
            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card h-100 stat">
                    <div class="card-body">
                        <div class="stat-label d-flex justify-content-between">
                            <span>Tổng nhân viên</span><span>👥</span>
                        </div>
                        <div class="stat-value" id="total-emp">0</div>
                        <div class="stat-help">
                            <span class="good" id="emp-change">0</span> so với tháng trước
                        </div>
                    </div>
                </article>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card h-100 stat">
                    <div class="card-body">
                        <div class="stat-label d-flex justify-content-between">
                            <span>Đã tạo bảng lương</span><span>✓</span>
                        </div>
                        <div class="stat-value" id="completed-salary">0</div>
                        <div class="stat-help" id="completed-percent">0% nhân viên đã hoàn tất</div>
                    </div>
                </article>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card h-100 stat">
                    <div class="card-body">
                        <div class="stat-label d-flex justify-content-between">
                            <span>Chờ xử lý</span><span>◷</span>
                        </div>
                        <div class="stat-value" id="pending-salary">0</div>
                        <div class="stat-help bad" id="pending-help">
                            Thiếu dữ liệu công hoặc hợp đồng
                        </div>
                    </div>
                </article>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <article class="card h-100 stat">
                    <div class="card-body">
                        <div class="stat-label d-flex justify-content-between">
                            <span>Tổng thực nhận</span><span>₫</span>
                        </div>
                        <div class="stat-value" id="total-received">0 ₫</div>
                        <div class="stat-help" id="salary-period">Kỳ lương tháng 07/2026</div>
                    </div>
                </article>
            </div>
        </section>
        --}}

        {{-- Filters --}}
        <section class="card shadow-sm mb-3 filter-card" aria-label="Bộ lọc bảng lương">
            <div class="card-body py-3">
                <div class="row g-2 align-items-center filters salary-filter-layout">
                    {{-- Tìm kiếm --}}
                    <div class="col-12 col-lg-4 col-xl-3">
                        <div class="input-group input-group-sm input-wrap search-field">
                            <span class="input-group-text bg-white search-icon">
                                <svg
                                    aria-hidden="true"
                                    width="16"
                                    height="16"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <circle cx="7" cy="7" r="4.5"></circle>
                                    <path d="M10.5 10.5 14 14"></path>
                                </svg>
                            </span>

                            <input
                                class="form-control control"
                                type="search"
                                id="search-field"
                                placeholder="Tìm mã hoặc tên nhân viên..."
                                aria-label="Tìm mã hoặc tên nhân viên"
                            >
                        </div>
                    </div>

                    {{-- Phòng ban --}}
                    <div class="col-12 col-sm-6 col-lg-2">
                        <select
                            class="form-select form-select-sm control"
                            id="department-filter"
                            aria-label="Phòng ban"
                        >
                            <option value="">-- Tất cả phòng ban --</option>
                        </select>
                    </div>

                    {{-- Chức vụ --}}
                    <div class="col-12 col-sm-6 col-lg-2">
                        <select
                            class="form-select form-select-sm control"
                            id="position-filter"
                            aria-label="Chức vụ"
                        >
                            <option value="">-- Tất cả chức vụ --</option>
                        </select>
                    </div>

                    {{-- Kỳ lương --}}
                    <div class="col-12 col-lg-auto">
                        <div class="salary-period-group d-flex align-items-center flex-nowrap gap-2">
                            <label
                                class="salary-period-label text-secondary fw-semibold mb-0 text-nowrap"
                                for="salary-month-select"
                            >
                                Kỳ lương
                            </label>

                            <select
                                class="form-select form-select-sm control salary-month-select"
                                id="salary-month-select"
                                aria-label="Tháng lương"
                            >
                                <option value="01">1</option>
                                <option value="02">2</option>
                                <option value="03">3</option>
                                <option value="04">4</option>
                                <option value="05">5</option>
                                <option value="06">6</option>
                                <option value="07" selected>7</option>
                                <option value="08">8</option>
                                <option value="09">9</option>
                                <option value="10">10</option>
                                <option value="11">11</option>
                                <option value="12">12</option>
                            </select>

                            <input
                                class="form-control form-control-sm control salary-year-input"
                                type="search"
                                id="salary-year-input"
                                placeholder="Năm"
                                inputmode="numeric"
                                maxlength="4"
                                aria-label="Năm lương"
                            >
                        </div>
                    </div>

                    {{-- Xóa lọc --}}
                    <div class="col-12 col-sm-auto ms-lg-auto">
                        <button
                            class="btn btn-outline-secondary btn-sm w-100 text-nowrap"
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
        <section class="card shadow-sm overflow-hidden table-card">
            <div class="card-header bg-white table-head d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 py-3">
                <div>
                    <h2 class="h6 fw-semibold mb-1" id="table-title">Chi tiết kỳ lương</h2>
                    <p class="small text-secondary mb-0" id="table-updated">
                        Dữ liệu cập nhật lần cuối lúc 09:42, 03/08/2026.
                    </p>
                </div>

                <div class="table-tools d-flex flex-wrap align-items-center gap-2">
                    <span class="small text-secondary sub" id="table-stat">121/128 hoàn tất</span>
                    <button
                        class="btn btn-outline-secondary btn-sm"
                        id="reconcile-btn"
                        type="button"
                        {{ \Illuminate\Support\Facades\Gate::allows(\App\Enums\LuongPermission::Sua->value) ? '' : 'disabled' }}
                    >
                        Đối soát
                    </button>
                </div>
            </div>

            <div class="table-responsive table-scroll">
                <table class="table table-hover align-middle mb-0 data-table">
                    <thead class="table-light">
                    <tr>
                        <th>Nhân viên</th>
                        <th>Phòng ban / Chức vụ</th>
                        <th class="numeric text-end">Kỳ lương</th>
                        <th class="numeric text-end">Thưởng</th>
                        <th class="numeric text-end">Phạt</th>
                        <th class="numeric text-end">Bảo hiểm</th>
                        <th class="numeric text-end">Thuế</th>
                        <th class="numeric text-end">Hệ số phụ cấp</th>
                        <th class="numeric text-end">Thực nhận</th>
                        <th class="numeric text-end">Ngày công</th>
                        <th class="numeric text-end">Vào muộn</th>
                        <th class="numeric text-end">Về sớm</th>
                        <th>Trạng thái</th>
                        <th class="text-end" aria-label="Thao tác"></th>
                    </tr>
                    </thead>

                    <tbody id="salary-tbody">
                    <tr class="empty-row">
                        <td colspan="15" class="text-center text-secondary py-5">
                            Đang tải dữ liệu...
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white table-foot d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 py-3">
                <div class="d-flex align-items-center flex-wrap gap-3">
        <span class="small text-secondary" id="page-info">
            Hiển thị 0 trên 0 nhân viên
        </span>

                    <div class="d-flex align-items-center gap-2">
                        <label
                            class="small text-secondary mb-0 text-nowrap"
                            for="salary-per-page"
                        >
                            Số dòng
                        </label>

                        <select
                            class="form-select form-select-sm salary-per-page-select"
                            id="salary-per-page"
                            aria-label="Số dòng mỗi trang"
                        >
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="15" selected>15</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>

                        <span class="small text-secondary text-nowrap">
                / trang
            </span>
                    </div>
                </div>

                <nav
                    class="pagination salary-pagination mb-0"
                    id="pagination"
                    aria-label="Phân trang bảng lương"
                ></nav>
            </div>
        </section>

        <section
            class="card shadow-sm mt-3 overflow-hidden"
            id="salary-coefficient-card"
            aria-labelledby="salary-coefficient-title"
        >
            <div class="card-header bg-white d-flex flex-column flex-md-row
                align-items-md-center justify-content-between gap-3 py-3">
                <div>
                    <h2
                        class="h6 fw-semibold mb-1"
                        id="salary-coefficient-title"
                    >
                        Lịch sử hệ số lương
                    </h2>

                    <p
                        class="small text-secondary mb-0"
                        id="salary-coefficient-description"
                    >
                        Chọn một nhân viên trong danh sách lương để xem dữ liệu.
                    </p>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
            <span
                class="badge text-bg-light border coefficient-employee-badge"
                id="coefficient-selected-employee"
            >
                Chưa chọn nhân viên
            </span>

                    <button
                        class="btn btn-success btn-sm"
                        id="add-coefficient-btn"
                        type="button"
                        disabled
                    >
                        + Thêm hệ số
                    </button>
                    <button
                        class="d-none"
                        id="delete-coefficient-btn"
                        type="button"
                        hidden
                        aria-hidden="true"
                    ></button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 coefficient-table">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 42px">
                            <input
                                class="form-check-input"
                                id="coefficient-check-all"
                                type="checkbox"
                                aria-label="Chọn tất cả hệ số lương"
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
                        <td
                            colspan="7"
                            class="text-center text-secondary py-5"
                        >
                            Chưa chọn nhân viên.
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white d-flex flex-column flex-sm-row
                align-items-sm-center justify-content-between gap-2 py-3">
        <span
            class="small text-secondary"
            id="coefficient-info"
        >
            Hiển thị 0 bản ghi
        </span>

                <div class="d-flex align-items-center gap-2">
                    <button
                        class="btn btn-outline-secondary btn-sm"
                        id="edit-coefficient-btn"
                        type="button"
                        disabled
                    >
                        Sửa hệ số
                    </button>

                    <button
                        class="btn btn-outline-danger btn-sm"
                        id="delete-coefficient-btn"
                        type="button"
                        disabled
                    >
                        Xóa hệ số
                    </button>
                </div>
            </div>
        </section>
    </main>

    {{-- Toast: giữ class .toast để JavaScript hiện tại tiếp tục tìm thấy --}}
    <div class="toast salary-toast" role="status" aria-live="polite"></div>

    {{-- Native dialog: giữ nguyên API showModal()/close() của luongCreateUpdate.js --}}
    <dialog
        class="salary-dialog"
        id="salary-modal"
        aria-labelledby="salary-modal-title"
    >
        <form id="salary-form" class="salary-modal-form">
            <header class="salary-modal-header d-flex align-items-start justify-content-between gap-3">
                <div>
                    <h2 class="h5 fw-semibold mb-1" id="salary-modal-title">
                        Thêm thông tin lương
                    </h2>

                    <p class="small text-secondary mb-0" id="salary-modal-description">
                        Nhập thông tin lương của nhân viên trong kỳ đã chọn.
                    </p>
                </div>

                <button
                    class="btn-close salary-modal-close"
                    id="salary-modal-close"
                    type="button"
                    aria-label="Đóng"
                ></button>
            </header>

            <div
                class="alert alert-danger salary-modal-message"
                id="salary-modal-message"
                role="alert"
                hidden
            ></div>

            <div class="salary-form-grid row g-3">
                {{-- Mã lương --}}
                <div class="salary-form-group col-12 col-md-6">
                    <label class="form-label fw-semibold" for="salary-id">
                        Mã lương
                    </label>

                    <input
                        class="form-control form-control-sm control"
                        id="salary-id"
                        type="text"
                        placeholder="Tự động"
                        readonly
                    >
                </div>

                {{-- Kỳ lương --}}
                <div class="salary-form-group col-12 col-md-6">
                    <label class="form-label fw-semibold" for="salary-period">
                        Kỳ lương
                        <span class="required text-danger">*</span>
                    </label>

                    <input
                        class="form-control form-control-sm control"
                        id="salary-period"
                        type="text"
                        readonly
                        required
                    >
                </div>

                {{-- Mã nhân viên --}}
                <div class="salary-form-group salary-form-full col-12">
                    <label class="form-label fw-semibold" for="salary-employee-code">
                        Mã nhân viên
                        <span class="required text-danger">*</span>
                    </label>

                    <input
                        class="form-control form-control-sm control"
                        id="salary-employee-code"
                        type="text"
                        maxlength="5"
                        placeholder="Ví dụ: 00001"
                        autocomplete="off"
                        required
                    >

                    <div class="form-text">
                        Nhập mã nhân viên đã tồn tại trong hệ thống.
                    </div>
                </div>

                {{-- Thưởng --}}
                <div class="salary-form-group col-12 col-md-6">
                    <label class="form-label fw-semibold" for="salary-bonus">
                        Thưởng
                    </label>

                    <div class="input-group input-group-sm money-input">
                        <input
                            class="form-control control"
                            id="salary-bonus"
                            type="number"
                            min="0"
                            step="1000"
                            value="0"
                        >
                        <span class="input-group-text">₫</span>
                    </div>
                </div>

                {{-- Phạt --}}
                <div class="salary-form-group col-12 col-md-6">
                    <label class="form-label fw-semibold" for="salary-penalty">
                        Phạt
                    </label>

                    <div class="input-group input-group-sm money-input">
                        <input
                            class="form-control control"
                            id="salary-penalty"
                            type="number"
                            min="0"
                            step="1000"
                            value="0"
                        >
                        <span class="input-group-text">₫</span>
                    </div>
                </div>

                {{-- Bảo hiểm --}}
                <div class="salary-form-group col-12 col-md-6">
                    <label class="form-label fw-semibold" for="salary-insurance">
                        Bảo hiểm
                    </label>

                    <div class="input-group input-group-sm money-input">
                        <input
                            class="form-control control"
                            id="salary-insurance"
                            type="number"
                            min="0"
                            step="1000"
                            value="0"
                        >
                        <span class="input-group-text">₫</span>
                    </div>
                </div>

                {{-- Thuế --}}
                <div class="salary-form-group col-12 col-md-6">
                    <label class="form-label fw-semibold" for="salary-tax">
                        Thuế
                    </label>

                    <div class="input-group input-group-sm money-input">
                        <input
                            class="form-control control"
                            id="salary-tax"
                            type="number"
                            min="0"
                            step="1000"
                            value="0"
                        >
                        <span class="input-group-text">₫</span>
                    </div>
                </div>
            </div>

            <footer class="salary-modal-footer d-flex align-items-center justify-content-end gap-2">
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

    <dialog
        class="coefficient-dialog"
        id="coefficient-modal"
        aria-labelledby="coefficient-modal-title"
    >
        <form
            class="coefficient-modal-form"
            id="coefficient-form"
        >
            <header
                class="coefficient-modal-header
                   d-flex align-items-start
                   justify-content-between gap-3"
            >
                <div>
                    <h2
                        class="h5 fw-semibold mb-1"
                        id="coefficient-modal-title"
                    >
                        Thêm hệ số lương
                    </h2>

                    <p
                        class="small text-secondary mb-0"
                        id="coefficient-modal-description"
                    >
                        Thiết lập hệ số lương theo thời gian hiệu lực.
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
                role="alert"
                hidden
            ></div>

            <div class="row g-3 coefficient-form-body">
                <!-- Mã nhân viên -->
                <div class="col-12 col-md-6">
                    <label
                        class="form-label fw-semibold"
                        for="coefficient-employee-code"
                    >
                        Mã nhân viên
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        class="form-control form-control-sm"
                        id="coefficient-employee-code"
                        type="text"
                        maxlength="5"
                        readonly
                        required
                    >
                </div>

                <!-- Mã lịch sử -->
                <div class="col-12 col-md-6">
                    <label
                        class="form-label fw-semibold"
                        for="coefficient-id"
                    >
                        Mã lịch sử hệ số lương
                    </label>

                    <input
                        class="form-control form-control-sm"
                        id="coefficient-id"
                        type="text"
                        placeholder="Tự động"
                        readonly
                    >
                </div>

                <!-- Tên nhân viên -->
                <div class="col-12">
                    <label
                        class="form-label fw-semibold"
                        for="coefficient-employee-name"
                    >
                        Nhân viên
                    </label>

                    <input
                        class="form-control form-control-sm"
                        id="coefficient-employee-name"
                        type="text"
                        readonly
                    >
                </div>

                <!-- Hệ số lương -->
                <div class="col-12">
                    <label
                        class="form-label fw-semibold"
                        for="coefficient-value"
                    >
                        Hệ số lương
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        class="form-control form-control-sm"
                        id="coefficient-value"
                        type="number"
                        min="0.01"
                        max="99.99"
                        step="0.01"
                        placeholder="Ví dụ: 2.34"
                        required
                    >

                    <div class="form-text">
                        Giá trị phải lớn hơn 0.
                    </div>
                </div>

                <!-- Từ ngày -->
                <div class="col-12 col-md-6">
                    <label
                        class="form-label fw-semibold"
                        for="coefficient-from-date"
                    >
                        Từ ngày
                        <span class="text-danger">*</span>
                    </label>

                    <input
                        class="form-control form-control-sm"
                        id="coefficient-from-date"
                        type="date"
                        required
                    >
                </div>

                <!-- Đến ngày -->
                <div class="col-12 col-md-6">
                    <label
                        class="form-label fw-semibold"
                        for="coefficient-to-date"
                    >
                        Đến ngày
                    </label>

                    <input
                        class="form-control form-control-sm"
                        id="coefficient-to-date"
                        type="date"
                    >

                    <div class="form-text">
                        Để trống nếu chưa xác định ngày kết thúc.
                    </div>
                </div>
            </div>

            <footer
                class="coefficient-modal-footer
                   d-flex align-items-center
                   justify-content-end gap-2"
            >
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
@endsection

@push('scripts')
    @vite([
        'resources/js/frontend/luong/luong.js',
        'resources/js/frontend/luong/luongCreateUpdate.js',
        'resources/js/frontend/luong/luongHeSo.js',
        'resources/js/frontend/luong/luongHeSoCreateUpdate.js'
    ])
@endpush
