@extends('layouts.app')

@section('content')
<main class="hr-page" aria-labelledby="page-title">
    <section class="page-heading">
        <div>
            <div class="crumb"><a href="#">Thời gian làm việc</a><span>/</span><span>Chấm công</span></div>
            <h1 class="page-title" id="page-title">Chấm công</h1>
            <p class="page-desc">Theo dõi ngày công, số giờ làm, vào muộn và về sớm theo từng nhân viên.</p>
        </div>
        <div class="page-actions">
            <button class="btn" id="import-btn"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v8"/><path d="m4.8 7.2 3.2 3.2 3.2-3.2"/><path d="M3 14h10"/></svg> Nhập dữ liệu</button>
            <button class="btn btn-primary" id="update-btn"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v10M3 8h10"/></svg> Cập nhật chấm công</button>
        </div>
    </section>

    <section class="stats" aria-label="Tổng quan chấm công">
        <article class="card stat"><div class="stat-label"><span>Ngày công trung bình</span><span>▣</span></div><div class="stat-value" id="avg-days">0</div><div class="stat-help">Trên 22 ngày công chuẩn</div></article>
        <article class="card stat"><div class="stat-label"><span>Tổng giờ làm</span><span>◷</span></div><div class="stat-value" id="total-hours">0</div><div class="stat-help"><span class="good">+3,2%</span> so với tháng trước</div></article>
        <article class="card stat"><div class="stat-label"><span>Vào muộn</span><span>!</span></div><div class="stat-value" id="late-count">0</div><div class="stat-help bad">5 trường hợp trên 15 phút</div></article>
        <article class="card stat"><div class="stat-label"><span>Về sớm</span><span>↙</span></div><div class="stat-value" id="early-count">0</div><div class="stat-help">Giảm 3 trường hợp so với tháng trước</div></article>
    </section>

    <section class="card filter-card" aria-label="Bộ lọc">
        <div class="filters">
            <div class="input-wrap search-field"><span class="search-icon"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5 14 14"/></svg></span><input class="control" type="search" placeholder="Tìm mã hoặc tên nhân viên..." id="search-field"></div>
            <select class="control" id="month-filter" aria-label="Tháng"></select>
            <select class="control" id="department-filter" aria-label="Phòng ban">
                <option value="">Tất cả phòng ban</option>
            </select>
            <select class="control" id="status-filter" aria-label="Trạng thái">
                <option value="">Tất cả trạng thái</option>
                <option value="good">Đủ công</option>
                <option value="warning">Cần kiểm tra</option>
                <option value="leave">Có nghỉ phép</option>
            </select>
            <button class="btn" id="clear-filter-btn">Xóa lọc</button>
        </div>
    </section>

    <section class="card table-card">
        <div class="table-head">
            <div><h2 id="table-title">Tổng hợp chấm công</h2><p>Quy đổi: 8 giờ = 1 công, 4 giờ = 0,5 công.</p></div>
            <div class="table-tools">
                <div class="segmented" role="tablist"><button aria-selected="true">Theo nhân viên</button><button aria-selected="false">Theo ngày</button></div>
                <button class="btn" id="export-btn">Xuất file</button>
            </div>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Nhân viên</th><th>Phòng ban</th><th class="numeric">Ngày công</th><th>Tiến độ tháng</th><th class="numeric">Tổng giờ</th><th class="numeric">Vào muộn</th><th class="numeric">Về sớm</th><th class="numeric">Nghỉ phép</th><th>Đánh giá</th><th></th></tr></thead>
                <tbody id="attendance-tbody">
                    <tr class="empty-row"><td colspan="10">Đang tải dữ liệu...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-foot"><span id="page-info">0 nhân viên · Cập nhật lúc 09:42</span><nav class="pagination" id="pagination"></nav></div>
    </section>
</main>

<div class="toast" role="status" aria-live="polite"></div>

@endsection

@push('scripts')
    @vite('resources/js/frontend/chamcong/chamcong.js')
@endpush
