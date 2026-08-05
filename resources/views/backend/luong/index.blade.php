@extends('layouts.app')
@section('title', 'Quản lý lương')

@section('content')
    <main class="hr-page" aria-labelledby="page-title">
        <section class="page-heading">
            <div>
                <div class="crumb"><a href="#">Nhân sự</a><span>/</span><span>Lương</span></div>
                <h1 class="page-title" id="page-title">Bảng lương</h1>
                <p class="page-desc">Theo dõi, đối soát và hoàn tất bảng lương theo từng kỳ.</p>
            </div>
            <div class="page-actions">
                <button class="btn" id="export-btn">
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 2v8"/>
                        <path d="m4.8 7.2 3.2 3.2 3.2-3.2"/>
                        <path d="M3 14h10"/>
                    </svg>
                    Xuất báo cáo
                </button>
                <button
                    class="btn btn-primary"
                    id="create-salary-btn"
                    type="button"
                >
                    + Thêm thông tin lương
                </button>
            </div>
        </section>

{{--        <section class="stats" aria-label="Tổng quan bảng lương">--}}
{{--            <article class="card stat">--}}
{{--                <div class="stat-label"><span>Tổng nhân viên</span><span>👥</span></div>--}}
{{--                <div class="stat-value" id="total-emp">0</div>--}}
{{--                <div class="stat-help"><span class="good" id="emp-change">0</span> so với tháng trước</div>--}}
{{--            </article>--}}
{{--            <article class="card stat">--}}
{{--                <div class="stat-label"><span>Đã tạo bảng lương</span><span>✓</span></div>--}}
{{--                <div class="stat-value" id="completed-salary">0</div>--}}
{{--                <div class="stat-help" id="completed-percent">0% nhân viên đã hoàn tất</div>--}}
{{--            </article>--}}
{{--            <article class="card stat">--}}
{{--                <div class="stat-label"><span>Chờ xử lý</span><span>◷</span></div>--}}
{{--                <div class="stat-value" id="pending-salary">0</div>--}}
{{--                <div class="stat-help bad" id="pending-help">Thiếu dữ liệu công hoặc hợp đồng</div>--}}
{{--            </article>--}}
{{--            <article class="card stat">--}}
{{--                <div class="stat-label"><span>Tổng thực nhận</span><span>₫</span></div>--}}
{{--                <div class="stat-value" id="total-received">0 ₫</div>--}}
{{--                <div class="stat-help" id="salary-period">Kỳ lương tháng 07/2026</div>--}}
{{--            </article>--}}
{{--        </section>--}}

        <section class="card filter-card" aria-label="Bộ lọc bảng lương">
            <div class="filters salary-filter-layout">

                <!-- Tìm kiếm -->
                <div class="input-wrap search-field">
                    <span class="search-icon">
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
                        class="control"
                        type="search"
                        id="search-field"
                        placeholder="Tìm mã hoặc tên nhân viên..."
                    >
                </div>

                <!-- Phòng ban -->
                <select
                    class="control"
                    id="department-filter"
                    aria-label="Phòng ban"
                >
                    <option value="">-- Tất cả phòng ban --</option>
                </select>

                <!-- Chức vụ -->
                <select
                    class="control"
                    id="position-filter"
                    aria-label="Chức vụ"
                >
                    <option value="">-- Tất cả chức vụ --</option>
                </select>

                <!-- Kỳ lương -->
                <div class="salary-period-group">
                    <label class="salary-period-label">
                        Kỳ lương
                    </label>
                    <select
                        class="control salary-month-select"
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
                        class="control salary-year-input"
                        type="search"
                        id="salary-year-input"
                        placeholder="năm ..."
                    >
                </div>
                <button
                    class="btn"
                    id="clear-filter-btn"
                    type="button"
                >
                    Xóa lọc
                </button>
            </div>

        </section>

        <section class="card table-card">
            <div class="table-head">
                <div><h2 id="table-title">Chi tiết kỳ lương</h2>
                    <p id="table-updated">Dữ liệu cập nhật lần cuối lúc 09:42, 03/08/2026.</p></div>
                <div class="table-tools"><span class="sub" id="table-stat">121/128 hoàn tất</span>
                    <button class="btn" id="reconcile-btn">Đối soát</button>
                </div>
            </div>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>
                            <input
                                class="checkbox"
                                type="checkbox"
                                aria-label="Chọn tất cả"
                            >
                        </th>

                        <th>Nhân viên</th>

                        <th>Phòng ban / Chức vụ</th>

                        <th class="numeric">Kỳ lương</th>

                        <th class="numeric">Thưởng</th>

                        <th class="numeric">Phạt</th>

                        <th class="numeric">Bảo hiểm</th>

                        <th class="numeric">Thuế</th>

                        <th class="numeric">Hệ số phụ cấp</th>

                        <th class="numeric">Thực nhận</th>

                        <th class="numeric">Ngày công</th>

                        <th class="numeric">Vào muộn</th>

                        <th class="numeric">Về sớm</th>

                        <th>Trạng thái</th>

                        <th aria-label="Thao tác"></th>
                    </tr>
                    </thead>
                    <tbody id="salary-tbody">
                    <tr class="empty-row">
                        <td colspan="10">Đang tải dữ liệu...</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div class="table-foot"><span id="page-info">Hiển thị 0 trên 0 nhân viên</span>
                <nav class="pagination" id="pagination"></nav>
            </div>
        </section>
    </main>

    <div class="toast" role="status" aria-live="polite"></div>

    <dialog
        class="salary-dialog"
        id="salary-modal"
        aria-labelledby="salary-modal-title"
    >
        <form id="salary-form" class="salary-modal-form">
            <header class="salary-modal-header">
                <div>
                    <h2 id="salary-modal-title">
                        Thêm thông tin lương
                    </h2>

                    <p id="salary-modal-description">
                        Nhập thông tin lương của nhân viên trong kỳ đã chọn.
                    </p>
                </div>

                <button
                    class="salary-modal-close"
                    id="salary-modal-close"
                    type="button"
                    aria-label="Đóng"
                >
                    ×
                </button>
            </header>

            <div
                class="salary-modal-message"
                id="salary-modal-message"
                hidden
            ></div>

            <div class="salary-form-grid">
                <!-- Mã lương -->
                <div class="salary-form-group">
                    <label for="salary-id">
                        Mã lương
                    </label>

                    <input
                        class="control"
                        id="salary-id"
                        type="text"
                        placeholder="Tự động"
                        readonly
                    >
                </div>

                <!-- Kỳ lương -->
                <div class="salary-form-group">
                    <label for="salary-period">
                        Kỳ lương
                        <span class="required">*</span>
                    </label>

                    <input
                        class="control"
                        id="salary-period"
                        type="date"
                        readonly
                        required
                    >
                </div>

                <!-- Mã nhân viên -->
                <div class="salary-form-group salary-form-full">
                    <label for="salary-employee-code">
                        Mã nhân viên
                        <span class="required">*</span>
                    </label>

                    <input
                        class="control"
                        id="salary-employee-code"
                        type="text"
                        maxlength="5"
                        placeholder="Ví dụ: NV001"
                        autocomplete="off"
                        required
                    >

                    <small>
                        Nhập mã nhân viên đã tồn tại trong hệ thống.
                    </small>
                </div>

                <!-- Thưởng -->
                <div class="salary-form-group">
                    <label for="salary-bonus">
                        Thưởng
                    </label>

                    <div class="money-input">
                        <input
                            class="control"
                            id="salary-bonus"
                            type="number"
                            min="0"
                            step="1000"
                            value="0"
                        >

                        <span>₫</span>
                    </div>
                </div>

                <!-- Phạt -->
                <div class="salary-form-group">
                    <label for="salary-penalty">
                        Phạt
                    </label>

                    <div class="money-input">
                        <input
                            class="control"
                            id="salary-penalty"
                            type="number"
                            min="0"
                            step="1000"
                            value="0"
                        >

                        <span>₫</span>
                    </div>
                </div>

                <!-- Bảo hiểm -->
                <div class="salary-form-group">
                    <label for="salary-insurance">
                        Bảo hiểm
                    </label>

                    <div class="money-input">
                        <input
                            class="control"
                            id="salary-insurance"
                            type="number"
                            min="0"
                            step="1000"
                            value="0"
                        >

                        <span>₫</span>
                    </div>
                </div>

                <!-- Thuế -->
                <div class="salary-form-group">
                    <label for="salary-tax">
                        Thuế
                    </label>

                    <div class="money-input">
                        <input
                            class="control"
                            id="salary-tax"
                            type="number"
                            min="0"
                            step="1000"
                            value="0"
                        >

                        <span>₫</span>
                    </div>
                </div>
            </div>

            <footer class="salary-modal-footer">
                <button
                    class="btn"
                    id="salary-modal-cancel"
                    type="button"
                >
                    Hủy
                </button>

                <button
                    class="btn btn-primary"
                    id="salary-modal-submit"
                    type="submit"
                >
                    Lưu thông tin
                </button>
            </footer>
        </form>
    </dialog>
@endsection

@push('scripts')
    @vite(['resources/js/frontend/luong/luong.js',
            'resources/js/frontend/luong/luongCreateUpdate.js'])
@endpush
