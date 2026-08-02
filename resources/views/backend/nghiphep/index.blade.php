@extends('layouts.app')

@section('content')
<main class="hr-page" aria-labelledby="page-title">
    <section class="page-heading">
        <div>
            <div class="crumb"><a href="#">Thời gian làm việc</a><span>/</span><span>Nghỉ phép</span></div>
            <h1 class="page-title" id="page-title">Nghỉ phép</h1>
            <p class="page-desc">Quản lý yêu cầu nghỉ phép, phê duyệt và theo dõi số ngày nghỉ của nhân viên.</p>
        </div>
        <div class="page-actions">
            <button class="btn" id="calendar-btn"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="3.5" width="11" height="10" rx="1.5"/><path d="M5 2v3M11 2v3M2.5 6.5h11"/></svg> Lịch nghỉ</button>
            <button class="btn btn-primary" id="create-btn"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3v10M3 8h10"/></svg> Tạo đơn nghỉ phép</button>
        </div>
    </section>

    <section class="stats" aria-label="Tổng quan nghỉ phép">
        <article class="card stat"><div class="stat-label"><span>Chờ duyệt</span><span>◷</span></div><div class="stat-value" id="pending-count">0</div><div class="stat-help bad" id="overdue-help">2 đơn chờ quá 24 giờ</div></article>
        <article class="card stat"><div class="stat-label"><span>Đã duyệt tháng này</span><span>✓</span></div><div class="stat-value" id="approved-count">0</div><div class="stat-help" id="approved-days">Tổng cộng 0 ngày nghỉ</div></article>
        <article class="card stat"><div class="stat-label"><span>Đang nghỉ hôm nay</span><span>👥</span></div><div class="stat-value" id="today-count">0</div><div class="stat-help">Thuộc 0 phòng ban</div></article>
        <article class="card stat"><div class="stat-label"><span>Tỷ lệ phê duyệt</span><span>%</span></div><div class="stat-value" id="approval-rate">0%</div><div class="stat-help"><span class="good">+2%</span> so với tháng trước</div></article>
    </section>

    <section class="card filter-card" aria-label="Bộ lọc">
        <div class="filters">
            <div class="input-wrap search-field"><span class="search-icon"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5 14 14"/></svg></span><input class="control" type="search" placeholder="Tìm nhân viên hoặc lý do nghỉ..." id="search-field"></div>
            <select class="control" id="period-filter" aria-label="Khoảng thời gian"></select>
            <select class="control" id="department-filter" aria-label="Phòng ban">
                <option value="">Tất cả phòng ban</option>
            </select>
            <select class="control" id="status-filter" aria-label="Trạng thái">
                <option value="">Tất cả trạng thái</option>
                <option value="pending">Chờ duyệt</option>
                <option value="approved">Đã duyệt</option>
                <option value="rejected">Từ chối</option>
            </select>
            <button class="btn" id="clear-filter-btn">Xóa lọc</button>
        </div>
    </section>

    <section class="card table-card">
        <div class="table-head">
            <div><h2>Yêu cầu nghỉ phép</h2><p>Ưu tiên xử lý những đơn có ngày bắt đầu gần nhất.</p></div>
            <div class="table-tools">
                <div class="segmented" role="tablist">
                    <button aria-selected="true" data-tab="all">Tất cả</button>
                    <button aria-selected="false" data-tab="pending">Chờ duyệt</button>
                    <button aria-selected="false" data-tab="approved">Đã duyệt</button>
                    <button aria-selected="false" data-tab="rejected">Từ chối</button>
                </div>
            </div>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Nhân viên</th><th>Loại nghỉ</th><th>Thời gian</th><th class="numeric">Số ngày</th><th>Lý do</th><th>Ngày gửi</th><th>Trạng thái</th><th></th></tr></thead>
                <tbody id="leave-tbody">
                    <tr class="empty-row"><td colspan="8">Đang tải dữ liệu...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-foot"><span id="page-info">Hiển thị 0 trên 0 yêu cầu</span><nav class="pagination" id="pagination"></nav></div>
    </section>
</main>

<div class="toast" role="status" aria-live="polite"></div>

@endsection


@push('scripts')
    @vite('resources/js/frontend/nghiphep/nghiphep.js')
@endpush

