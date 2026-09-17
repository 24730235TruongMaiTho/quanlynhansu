@extends('backend.layouts.app')

@php
    $canEmployeeRead = \Illuminate\Support\Facades\Gate::allows('NhanVien.Read');
    $canDepartmentRead = \Illuminate\Support\Facades\Gate::allows('PhongBan.Read');
    $canContractRead = \Illuminate\Support\Facades\Gate::allows('HopDong.Read');
    $canAttendanceRead = \Illuminate\Support\Facades\Gate::allows('ChamCong.Read');
    $canSalaryRead = \Illuminate\Support\Facades\Gate::allows('Luong.Read');
    $canLeaveOverview = \Illuminate\Support\Facades\Gate::allows(\App\Enums\NghiPhepPermission::Xem->value)
        && \Illuminate\Support\Facades\Gate::allows(\App\Enums\NghiPhepPermission::Duyet->value);
    $hasCompanyWidgets = $canEmployeeRead || $canDepartmentRead || $canContractRead
        || $canAttendanceRead || $canSalaryRead || $canLeaveOverview;
@endphp

@section('title', 'Tổng quan - Quản lý nhân sự')

@section('content')
<div class="content-area dashboard-page">
    <x-backend.page-header
        title="Tổng quan"
        title-id="dashboard-page-title"
        icon="bi-speedometer2"
        :breadcrumbs="[['label' => 'Tổng quan']]"
    >
        <x-slot:actions>
            <button class="btn btn-primary btn-icon-text" id="refreshDashboard" type="button">
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i><span>Làm mới</span>
            </button>
        </x-slot:actions>
    </x-backend.page-header>

    <div id="dashboardLoading" class="alert alert-info d-none" role="status" aria-live="polite">
        <i class="bi bi-hourglass-split me-2" aria-hidden="true"></i>Đang tải dữ liệu tổng quan...
    </div>

    <main id="dashboardContent">
        <section aria-labelledby="personal-dashboard-title" class="mb-5" id="personalDashboardSection">
            <div class="mb-3">
                <h2 class="h4 mb-1" id="personal-dashboard-title">Tổng quan cá nhân</h2>
                <p class="text-muted mb-0">Thông tin của chính bạn, được giới hạn theo mã nhân viên đang đăng nhập.</p>
            </div>

            <div class="row g-3" id="personalWidgets">
                <div class="col-12 col-md-6 col-xl-4">
                    <a class="card dashboard-widget h-100 text-decoration-none" data-personal-widget="profile" href="{{ route('backend.profile.edit') }}">
                        <div class="card-body">
                            <div class="dashboard-widget-icon text-primary"><i class="bi bi-person-vcard" aria-hidden="true"></i></div>
                            <h3 class="h6 text-body mb-1">Hồ sơ</h3>
                            <p class="mb-1 text-body" data-personal-field="profile.ho_ten">Đang tải...</p>
                            <small class="text-muted" data-personal-field="profile.vai_tro">Đang tải vai trò...</small>
                            <span class="dashboard-widget-state visually-hidden" data-personal-state role="status"></span>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-xl-4">
                    <a class="card dashboard-widget h-100 text-decoration-none" data-personal-widget="attendance" href="{{ route('backend.selfservice.chamcong.index') }}">
                        <div class="card-body">
                            <div class="dashboard-widget-icon text-info"><i class="bi bi-calendar-check" aria-hidden="true"></i></div>
                            <h3 class="h6 text-body mb-1">Chấm công tháng hiện tại</h3>
                            <p class="mb-1 text-body" data-personal-field="attendance.summary">Đang tải...</p>
                            <small class="text-muted" data-personal-field="attendance.flags">Đang tải đi muộn/về sớm...</small>
                            <span class="dashboard-widget-state visually-hidden" data-personal-state role="status"></span>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-xl-4">
                    <a class="card dashboard-widget h-100 text-decoration-none" data-personal-widget="leave" href="{{ route('backend.nghiphep.create') }}">
                        <div class="card-body">
                            <div class="dashboard-widget-icon text-warning"><i class="bi bi-calendar2-heart" aria-hidden="true"></i></div>
                            <h3 class="h6 text-body mb-1">Nghỉ phép</h3>
                            <p class="mb-1 text-body" data-personal-field="leave.pending">Đang tải...</p>
                            <small class="text-muted" data-personal-field="leave.latest">Đang tải đơn gần nhất...</small>
                            <span class="dashboard-widget-state visually-hidden" data-personal-state role="status"></span>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-xl-4">
                    <a class="card dashboard-widget h-100 text-decoration-none" data-personal-widget="contract" href="{{ route('backend.selfservice.hopdong.index') }}">
                        <div class="card-body">
                            <div class="dashboard-widget-icon text-success"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></div>
                            <h3 class="h6 text-body mb-1">Hợp đồng hiện tại</h3>
                            <p class="mb-1 text-body" data-personal-field="contract.type">Đang tải...</p>
                            <small class="text-muted" data-personal-field="contract.expiry">Đang tải ngày hết hạn...</small>
                            <span class="dashboard-widget-state visually-hidden" data-personal-state role="status"></span>
                        </div>
                    </a>
                </div>
                <div class="col-12 col-md-6 col-xl-4">
                    <a class="card dashboard-widget h-100 text-decoration-none" data-personal-widget="salary" href="{{ route('backend.selfservice.luong.index') }}">
                        <div class="card-body">
                            <div class="dashboard-widget-icon text-secondary"><i class="bi bi-wallet2" aria-hidden="true"></i></div>
                            <h3 class="h6 text-body mb-1">Lương</h3>
                            <p class="mb-1 text-body" data-personal-field="salary.period">Đang tải kỳ gần nhất...</p>
                            <small class="text-muted" data-personal-field="salary.status">Đang tải trạng thái...</small>
                            <span class="dashboard-widget-state visually-hidden" data-personal-state role="status"></span>
                        </div>
                    </a>
                </div>
            </div>
        </section>

        @if ($hasCompanyWidgets)
            <section aria-labelledby="company-dashboard-title" id="companyDashboardSection">
                <div class="mb-3">
                    <h2 class="h4 mb-1" id="company-dashboard-title">Tổng quan công ty</h2>
                    <p class="text-muted mb-0">Các chỉ số hiển thị theo quyền module của tài khoản.</p>
                </div>

                <div class="row g-3 mb-4" id="statsCards">
                    @if ($canEmployeeRead)
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card dashboard-widget h-100">
                                <div class="card-body"><div class="dashboard-widget-icon text-primary"><i class="bi bi-people" aria-hidden="true"></i></div><h3 class="h6 text-muted">Tổng nhân viên</h3><p class="display-6 mb-0" id="totalEmployees">—</p></div>
                            </div>
                        </div>
                    @endif
                    @if ($canDepartmentRead)
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card dashboard-widget h-100">
                                <div class="card-body"><div class="dashboard-widget-icon text-success"><i class="bi bi-building" aria-hidden="true"></i></div><h3 class="h6 text-muted">Tổng phòng ban</h3><p class="display-6 mb-0" id="totalDepartments">—</p></div>
                            </div>
                        </div>
                    @endif
                    @if ($canAttendanceRead)
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card dashboard-widget h-100">
                                <div class="card-body"><div class="dashboard-widget-icon text-info"><i class="bi bi-person-check" aria-hidden="true"></i></div><h3 class="h6 text-muted">Đi làm tháng này</h3><p class="display-6 mb-0" id="todayAttendance">—</p></div>
                            </div>
                        </div>
                    @endif
                    @if ($canContractRead)
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card dashboard-widget h-100">
                                <div class="card-body"><div class="dashboard-widget-icon text-warning"><i class="bi bi-clock-history" aria-hidden="true"></i></div><h3 class="h6 text-muted">Hợp đồng sắp hết hạn</h3><p class="display-6 mb-0" id="expiringContracts">—</p><small class="text-muted">30 ngày tới</small></div>
                            </div>
                        </div>
                    @endif
                    @if ($canLeaveOverview)
                        <div class="col-12 col-sm-6 col-xl-3">
                                <a class="card dashboard-widget h-100 text-decoration-none" href="{{ route('backend.nghiphep.index') . '#leave-table-card' }}">
                                <div class="card-body"><div class="dashboard-widget-icon text-danger"><i class="bi bi-calendar-check" aria-hidden="true"></i></div><h3 class="h6 text-muted">Nghỉ phép chờ duyệt</h3><p class="display-6 mb-0" id="pendingLeaveCount">—</p><small class="text-muted">Toàn công ty</small></div>
                            </a>
                        </div>
                    @endif
                </div>

                <div class="row g-3">
                    @if ($canEmployeeRead)
                        <div class="col-12 col-lg-6">
                            <div class="card h-100">
                                <div class="card-header bg-white"><h3 class="h6 mb-0"><i class="bi bi-pie-chart text-primary me-2" aria-hidden="true"></i>Nhân viên theo học vấn</h3></div>
                                <div class="card-body"><canvas id="educationChart" height="250" aria-label="Biểu đồ nhân viên theo học vấn"></canvas><div class="small text-muted" id="educationEmpty"></div></div>
                            </div>
                        </div>
                    @endif
                    @if ($canEmployeeRead && $canDepartmentRead)
                        <div class="col-12 col-lg-6">
                            <div class="card h-100">
                                <div class="card-header bg-white"><h3 class="h6 mb-0"><i class="bi bi-bar-chart text-success me-2" aria-hidden="true"></i>Nhân viên theo phòng ban</h3></div>
                                <div class="card-body"><canvas id="departmentChart" height="250" aria-label="Biểu đồ nhân viên theo phòng ban"></canvas><div class="small text-muted" id="departmentEmpty"></div></div>
                            </div>
                        </div>
                    @endif
                    @if ($canAttendanceRead)
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-white"><h3 class="h6 mb-0"><i class="bi bi-calendar-check text-info me-2" aria-hidden="true"></i>Báo cáo chấm công <small class="text-muted fw-normal" id="attendanceMonth"></small></h3></div>
                                <div class="card-body"><div class="row g-3"><div class="col-6 col-md-3"><span class="text-muted small">Nhân viên</span><strong class="d-block fs-4" id="attTotalEmployees">—</strong></div><div class="col-6 col-md-3"><span class="text-muted small">Ca chấm công</span><strong class="d-block fs-4" id="attTotalShifts">—</strong></div><div class="col-6 col-md-3"><span class="text-muted small">Đi muộn</span><strong class="d-block fs-4" id="attLate">—</strong></div><div class="col-6 col-md-3"><span class="text-muted small">Về sớm</span><strong class="d-block fs-4" id="attEarly">—</strong></div></div></div>
                            </div>
                        </div>
                    @endif
                    @if ($canContractRead)
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-white"><h3 class="h6 mb-0"><i class="bi bi-file-earmark-text text-warning me-2" aria-hidden="true"></i>Hợp đồng sắp hết hạn</h3></div>
                                <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Nhân viên</th><th>Loại hợp đồng</th><th>Ngày bắt đầu</th><th>Ngày kết thúc</th><th>Còn lại</th></tr></thead><tbody id="contractsTableBody"><tr><td colspan="5" class="text-muted text-center py-3">Đang tải...</td></tr></tbody></table></div>
                            </div>
                        </div>
                    @endif
                    @if ($canSalaryRead)
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-white"><h3 class="h6 mb-0"><i class="bi bi-wallet2 text-secondary me-2" aria-hidden="true"></i>Báo cáo lương <small class="text-muted fw-normal" id="salaryReportMonth"></small></h3></div>
                                <div class="card-body"><p class="mb-0" id="salaryReportContent">Đang tải báo cáo lương...</p></div>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
        @endif
    </main>
</div>
@endsection

@push('styles')
<style>
    .dashboard-page { padding: 1.5rem; }
    .dashboard-widget { border: 1px solid var(--bs-border-color); transition: transform .2s ease, box-shadow .2s ease; }
    a.dashboard-widget:hover, a.dashboard-widget:focus-visible { transform: translateY(-2px); box-shadow: 0 .25rem .75rem rgba(0,0,0,.08); }
    .dashboard-widget-icon { font-size: 1.5rem; line-height: 1; margin-bottom: .75rem; }
    .dashboard-widget-state { display: block; }
    @media (max-width: 576px) { .dashboard-page { padding: .75rem; } }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    let educationChart = null;
    let departmentChart = null;
    const personalFields = {
        profile: ['profile.ho_ten', 'profile.vai_tro'],
        attendance: ['attendance.summary', 'attendance.flags'],
        leave: ['leave.pending', 'leave.latest'],
        contract: ['contract.type', 'contract.expiry'],
        salary: ['salary.period', 'salary.status']
    };

    const setText = (selector, value) => {
        const element = document.querySelector(selector);
        if (element) element.textContent = value == null ? '' : String(value);
    };

    const formatDisplayDate = (value) => window.qlns && typeof window.qlns.formatDisplayDate === 'function'
        ? window.qlns.formatDisplayDate(value) : '';

    const stateMessage = (state) => {
        if (state === 'error') return 'Không thể tải dữ liệu lúc này.';
        if (state === 'empty') return 'Chưa có dữ liệu.';
        return '';
    };

    function renderPersonalWidget(name, widget) {
        const root = document.querySelector(`[data-personal-widget="${name}"]`);
        if (!root) return;
        const state = widget && typeof widget.state === 'string' ? widget.state : 'error';
        const data = widget && widget.data && typeof widget.data === 'object' ? widget.data : null;
        const stateElement = root.querySelector('[data-personal-state]');
        if (stateElement) stateElement.textContent = stateMessage(state);
        if (state !== 'ready' || !data) {
            (personalFields[name] || []).forEach((field) => setText(`[data-personal-field="${field}"]`, stateMessage(state)));
            return;
        }
        if (name === 'profile') {
            setText('[data-personal-field="profile.ho_ten"]', data.ho_ten);
            setText('[data-personal-field="profile.vai_tro"]', data.vai_tro);
        } else if (name === 'attendance') {
            setText('[data-personal-field="attendance.summary"]', `${data.so_ngay ?? 0} ngày · ${data.tong_gio ?? 0} giờ`);
            setText('[data-personal-field="attendance.flags"]', `Đi muộn: ${data.so_lan_vao_muon ?? 0} · Về sớm: ${data.so_lan_ve_som ?? 0}`);
        } else if (name === 'leave') {
            setText('[data-personal-field="leave.pending"]', `${data.so_don_cho ?? 0} đơn chờ duyệt`);
            const latest = data.don_gan_nhat;
            setText('[data-personal-field="leave.latest"]', latest ? `${latest.loai_phep || 'Đơn nghỉ phép'} · ${latest.trang_thai || 'Chưa xác định'}` : 'Chưa có đơn gần đây');
        } else if (name === 'contract') {
            setText('[data-personal-field="contract.type"]', data.loai_hop_dong || 'Chưa xác định');
            setText('[data-personal-field="contract.expiry"]', data.ngay_het_han ? `Hết hạn: ${formatDisplayDate(data.ngay_het_han)}` : 'Không thời hạn');
        } else if (name === 'salary') {
            setText('[data-personal-field="salary.period"]', data.ky_luong ? `Kỳ gần nhất: ${formatDisplayDate(data.ky_luong)}` : 'Chưa có kỳ lương');
            setText('[data-personal-field="salary.status"]', data.trang_thai || 'Chưa xác định');
        }
    }

    async function fetchJson(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const result = await response.json();
        if (!result || result.success !== true) throw new Error('dashboard_request_failed');
        return result.data && typeof result.data === 'object' ? result.data : {};
    }

    async function fetchPersonalData() {
        try {
            const data = await fetchJson('/api/v1/dashboard/personal');
            Object.keys(personalFields).forEach((name) => renderPersonalWidget(name, data[name]));
        } catch {
            Object.keys(personalFields).forEach((name) => renderPersonalWidget(name, { state: 'error' }));
        }
    }

    function renderContractsTable(contracts) {
        const tbody = document.getElementById('contractsTableBody');
        if (!tbody) return;
        tbody.replaceChildren();
        if (!Array.isArray(contracts) || contracts.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5; cell.className = 'text-muted text-center py-3'; cell.textContent = 'Không có hợp đồng nào sắp hết hạn';
            row.appendChild(cell); tbody.appendChild(row); return;
        }
        contracts.forEach((contract) => {
            const row = document.createElement('tr');
            [contract.ho_ten || 'Chưa cập nhật', contract.ten_loai_hop_dong || 'Chưa xác định', formatDisplayDate(contract.ngay_bat_dau) || '-', formatDisplayDate(contract.ngay_ket_thuc) || '-', `${contract.so_ngay_con_lai ?? 0} ngày`].forEach((value) => {
                const cell = document.createElement('td'); cell.textContent = String(value); row.appendChild(cell);
            });
            tbody.appendChild(row);
        });
    }

    function renderEducationChart(data) {
        const canvas = document.getElementById('educationChart');
        if (!canvas || typeof Chart === 'undefined') return;
        if (educationChart) educationChart.destroy();
        const rows = Array.isArray(data) ? data : [];
        setText('#educationEmpty', rows.length ? '' : 'Chưa có dữ liệu học vấn.');
        educationChart = new Chart(canvas.getContext('2d'), { type: 'pie', data: { labels: rows.map((item) => String(item.hoc_van || 'Chưa xác định')), datasets: [{ data: rows.map((item) => Number(item.total) || 0), backgroundColor: ['#e94560', '#36a2eb', '#ffce56', '#4bc0c0', '#9966ff'] }] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } } } });
    }

    function renderDepartmentChart(data) {
        const canvas = document.getElementById('departmentChart');
        if (!canvas || typeof Chart === 'undefined') return;
        if (departmentChart) departmentChart.destroy();
        const rows = Array.isArray(data) ? data : [];
        setText('#departmentEmpty', rows.length ? '' : 'Chưa có dữ liệu phòng ban.');
        departmentChart = new Chart(canvas.getContext('2d'), { type: 'bar', data: { labels: rows.map((item) => String(item.ten_pb || 'Chưa xác định')), datasets: [{ data: rows.map((item) => Number(item.total) || 0), backgroundColor: '#4bc0c0' }] }, options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } } });
    }

    function renderCompany(data) {
        if (!data || typeof data !== 'object') return;
        setText('#totalEmployees', data.tong_nhan_vien);
        setText('#totalDepartments', data.tong_phong_ban);
        setText('#expiringContracts', Array.isArray(data.hop_dong_sap_het_han) ? data.hop_dong_sap_het_han.length : 0);
        const pendingLeaveCountElement = document.getElementById('pendingLeaveCount');
        if (pendingLeaveCountElement) {
            pendingLeaveCountElement.textContent = data.pending_leave_count == null ? '' : String(data.pending_leave_count);
        }
        const attendance = data.bao_cao_cham_cong;
        if (attendance && typeof attendance === 'object') {
            setText('#attendanceMonth', `Tháng ${attendance.thang ?? ''}/${attendance.nam ?? ''}`);
            setText('#attTotalEmployees', attendance.tong_nhan_vien);
            setText('#attTotalShifts', attendance.tong_ca_cham_cong);
            setText('#attLate', attendance.so_lan_vao_muon);
            setText('#attEarly', attendance.so_lan_ve_som);
        }
        const salary = data.bao_cao_luong;
        if (salary && typeof salary === 'object') {
            setText('#salaryReportMonth', `Tháng ${salary.thang ?? ''}/${salary.nam ?? ''}`);
            setText('#salaryReportContent', salary.error || `Có ${salary.so_nguoi ?? 0} nhân viên trong kỳ.`);
        }
        renderEducationChart(data.nhan_vien_theo_hoc_van);
        renderDepartmentChart(data.nhan_vien_theo_phong_ban);
        renderContractsTable(data.hop_dong_sap_het_han);
    }

    async function fetchCompanyData() {
        try { renderCompany(await fetchJson('/api/v1/dashboard/overview')); }
        catch { renderCompany({}); }
    }

    const hasCompanyWidgets = @json($hasCompanyWidgets);

    async function refreshDashboard() {
        const loading = document.getElementById('dashboardLoading');
        if (loading) loading.classList.remove('d-none');
        const tasks = [fetchPersonalData()];
        if (hasCompanyWidgets) tasks.push(fetchCompanyData());
        await Promise.allSettled(tasks);
        if (loading) loading.classList.add('d-none');
    }

    const refreshButton = document.getElementById('refreshDashboard');
    if (refreshButton) refreshButton.addEventListener('click', async () => {
        refreshButton.disabled = true;
        await refreshDashboard();
        refreshButton.disabled = false;
    });
    refreshDashboard();
});
</script>
@endpush
