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
            <button class="btn" id="export-btn"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v8"/><path d="m4.8 7.2 3.2 3.2 3.2-3.2"/><path d="M3 14h10"/></svg> Xuất báo cáo</button>
            <button class="btn btn-primary" id="create-btn"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v10M3 8h10"/></svg> Tạo bảng lương</button>
        </div>
    </section>

    <section class="stats" aria-label="Tổng quan bảng lương">
        <article class="card stat"><div class="stat-label"><span>Tổng nhân viên</span><span>👥</span></div><div class="stat-value" id="total-emp">0</div><div class="stat-help"><span class="good" id="emp-change">0</span> so với tháng trước</div></article>
        <article class="card stat"><div class="stat-label"><span>Đã tạo bảng lương</span><span>✓</span></div><div class="stat-value" id="completed-salary">0</div><div class="stat-help" id="completed-percent">0% nhân viên đã hoàn tất</div></article>
        <article class="card stat"><div class="stat-label"><span>Chờ xử lý</span><span>◷</span></div><div class="stat-value" id="pending-salary">0</div><div class="stat-help bad" id="pending-help">Thiếu dữ liệu công hoặc hợp đồng</div></article>
        <article class="card stat"><div class="stat-label"><span>Tổng thực nhận</span><span>₫</span></div><div class="stat-value" id="total-received">0 ₫</div><div class="stat-help" id="salary-period">Kỳ lương tháng 07/2026</div></article>
    </section>

    <section class="card filter-card" aria-label="Bộ lọc">
        <div class="filters">
            <div class="input-wrap search-field"><span class="search-icon"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5 14 14"/></svg></span><input class="control" type="search" placeholder="Tìm theo mã hoặc tên nhân viên..." id="search-field"></div>
            <select class="control" id="salary-period-select" aria-label="Kỳ lương"></select>
            <select class="control" id="department-filter" aria-label="Phòng ban">
                <option value="">Tất cả phòng ban</option>
            </select>
            <select class="control" id="status-filter" aria-label="Trạng thái">
                <option value="">Tất cả trạng thái</option>
                <option value="completed">Đã hoàn tất</option>
                <option value="pending">Chờ xử lý</option>
                <option value="draft">Bản nháp</option>
            </select>
            <button class="btn" id="clear-filter-btn">Xóa lọc</button>
        </div>
    </section>

    <section class="card table-card">
        <div class="table-head">
            <div><h2 id="table-title">Chi tiết kỳ lương</h2><p id="table-updated">Dữ liệu cập nhật lần cuối lúc 09:42, 03/08/2026.</p></div>
            <div class="table-tools"><span class="sub" id="table-stat">121/128 hoàn tất</span><button class="btn" id="reconcile-btn">Đối soát</button></div>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th><input class="checkbox" type="checkbox" aria-label="Chọn tất cả"></th><th>Nhân viên</th><th>Phòng ban / Chức vụ</th><th class="numeric">Ngày công</th><th class="numeric">Lương cơ bản</th><th class="numeric">Phụ cấp</th><th class="numeric">Khấu trừ</th><th class="numeric">Thực nhận</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody id="salary-tbody">
                    <tr class="empty-row"><td colspan="10">Đang tải dữ liệu...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-foot"><span id="page-info">Hiển thị 0 trên 0 nhân viên</span><nav class="pagination" id="pagination"></nav></div>
    </section>
</main>

<div class="toast" role="status" aria-live="polite"></div>

@endsection

@push('scripts')
    @vite('resources/js/frontend/luong/luong.js')
@endpush
