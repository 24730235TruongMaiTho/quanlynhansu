@extends('backend.layouts.app')
@section('title', 'Tổng quan - Quản lý nhân sự')

@section('content')
<div class="content-area">
    <x-backend.page-header
        title="Tổng quan"
        title-id="dashboard-page-title"
        icon="bi-speedometer2"
        :breadcrumbs="[['label' => 'Tổng quan']]"
    >
        <x-slot:actions>
            <button class="btn btn-primary" id="refreshDashboard">
                <i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i> Làm mới
            </button>
        </x-slot:actions>
    </x-backend.page-header>

    <!-- Loading -->
    <div id="dashboardLoading" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Đang tải...</span>
        </div>
        <p class="mt-2 text-muted">Đang tải dữ liệu tổng quan...</p>
    </div>

    <!-- Dashboard Content -->
    <div id="dashboardContent">

        <!-- ===== HÀNG 1: THỐNG KÊ NHANH (5 CARD) ===== -->
        <div class="row g-4 mb-4" id="statsCards">
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0 rounded-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-primary bg-opacity-10 rounded-3 p-3">
                                    <i class="bi bi-people fs-1 text-primary"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">Tổng nhân viên</h6>
                                <h2 class="mb-0 fw-bold" id="totalEmployees">0</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <a class="card h-100 shadow-sm border-0 rounded-3 text-decoration-none" href="{{ route('backend.nghiphep.duyet-nghi-phep') }}" aria-label="Mở danh sách nghỉ phép chờ duyệt">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-danger bg-opacity-10 rounded-3 p-3">
                                    <i class="bi bi-calendar-check fs-1 text-danger" aria-hidden="true"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">Nghỉ phép chờ duyệt</h6>
                                <h2 class="mb-0 fw-bold" id="pendingDepartmentLeaveCount">—</h2>
                                <small class="text-muted">Phòng ban của bạn</small>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0 rounded-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-success bg-opacity-10 rounded-3 p-3">
                                    <i class="bi bi-building fs-1 text-success"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">Phòng ban</h6>
                                <h2 class="mb-0 fw-bold" id="totalDepartments">0</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0 rounded-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-info bg-opacity-10 rounded-3 p-3">
                                    <i class="bi bi-person-check fs-1 text-info"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">Đi làm hôm nay</h6>
                                <h2 class="mb-0 fw-bold" id="todayAttendance">0</h2>
                                <small class="text-muted" id="attendancePercent">0%</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0 rounded-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-danger bg-opacity-10 rounded-3 p-3">
                                    <i class="bi bi-person-x fs-1 text-danger"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">Nghỉ hôm nay</h6>
                                <h2 class="mb-0 fw-bold" id="todayAbsent">0</h2>
                                <small class="text-muted" id="absentPercent">0%</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-3">
                <div class="card h-100 shadow-sm border-0 rounded-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="bg-warning bg-opacity-10 rounded-3 p-3">
                                    <i class="bi bi-file-earmark-text fs-1 text-warning"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="text-muted mb-1">Hợp đồng sắp hết hạn</h6>
                                <h2 class="mb-0 fw-bold" id="expiringContracts">0</h2>
                                <small class="text-muted">30 ngày tới</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== HÀNG 2: BIỂU ĐỒ ===== -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white border-0 pt-3">
                        <h5 class="mb-0">
                            <i class="bi bi-pie-chart-fill text-primary me-2"></i>
                            Nhân viên theo học vấn
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="educationChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white border-0 pt-3">
                        <h5 class="mb-0">
                            <i class="bi bi-bar-chart-fill text-success me-2"></i>
                            Nhân viên theo phòng ban
                        </h5>
                    </div>
                    <div class="card-body">
                        <canvas id="departmentChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== HÀNG 3: BÁO CÁO CHẤM CÔNG + HỢP ĐỒNG ===== -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-lg-7">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar-check text-info me-2"></i>
                            Báo cáo chấm công
                            <small class="text-muted fw-light" id="attendanceMonth">- Tháng hiện tại</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6 col-md-4">
                                <div class="p-3 bg-primary bg-opacity-10 rounded-3 text-center">
                                    <h6 class="text-muted mb-1 small">Tổng nhân viên</h6>
                                    <h3 class="mb-0 fw-bold text-primary" id="attTotalEmployees">0</h3>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="p-3 bg-success bg-opacity-10 rounded-3 text-center">
                                    <h6 class="text-muted mb-1 small">Số ca chấm công</h6>
                                    <h3 class="mb-0 fw-bold text-success" id="attTotalShifts">0</h3>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="p-3 bg-info bg-opacity-10 rounded-3 text-center">
                                    <h6 class="text-muted mb-1 small">Đi làm tháng này</h6>
                                    <h3 class="mb-0 fw-bold text-info" id="attPresent">0</h3>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="p-3 bg-danger bg-opacity-10 rounded-3 text-center">
                                    <h6 class="text-muted mb-1 small">Vắng tháng này</h6>
                                    <h3 class="mb-0 fw-bold text-danger" id="attAbsent">0</h3>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="p-3 bg-warning bg-opacity-10 rounded-3 text-center">
                                    <h6 class="text-muted mb-1 small">Đi muộn</h6>
                                    <h3 class="mb-0 fw-bold text-warning" id="attLate">0</h3>
                                    <small class="text-muted">lần</small>
                                </div>
                            </div>
                            <div class="col-6 col-md-4">
                                <div class="p-3 bg-secondary bg-opacity-10 rounded-3 text-center">
                                    <h6 class="text-muted mb-1 small">Về sớm</h6>
                                    <h3 class="mb-0 fw-bold" id="attEarly">0</h3>
                                    <small class="text-muted">lần</small>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="p-3 bg-light rounded-3 text-center">
                                    <h6 class="text-muted mb-1 small">Giờ làm trung bình</h6>
                                    <h3 class="mb-0 fw-bold" id="attAvgHours">0.0</h3>
                                    <small class="text-muted">giờ/ngày</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-clock-history text-warning me-2"></i>
                            Hợp đồng sắp hết hạn
                        </h5>
                        <span class="badge bg-warning text-dark">30 ngày tới</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">#</th>
                                        <th>Nhân viên</th>
                                        <th>Loại hợp đồng</th>
                                        <th>Ngày kết thúc</th>
                                        <th>Còn lại</th>
                                    </tr>
                                </thead>
                                <tbody id="contractsTableBody">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="bi bi-inbox me-2"></i> Không có hợp đồng nào
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== HÀNG 4: BÁO CÁO LƯƠNG ===== -->
        <div class="row g-4">
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-wallet2 text-success me-2"></i>
                            Báo cáo lương
                            <small class="text-muted fw-light" id="salaryReportMonth">- Tháng hiện tại</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="salaryReportContent">
                            <div class="row g-4">
                                <div class="col-12 col-md-4">
                                    <div class="p-4 bg-primary bg-opacity-10 rounded-3 text-center">
                                        <h6 class="text-muted mb-2">Số nhân viên</h6>
                                        <h2 class="mb-0 fw-bold text-primary" id="salEmployees">0</h2>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="p-4 bg-success bg-opacity-10 rounded-3 text-center">
                                        <h6 class="text-muted mb-2">Tổng lương</h6>
                                        <h2 class="mb-0 fw-bold text-success" id="salTotal">0 VND</h2>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="p-4 bg-warning bg-opacity-10 rounded-3 text-center">
                                        <h6 class="text-muted mb-2">Lương trung bình</h6>
                                        <h2 class="mb-0 fw-bold text-warning" id="salAvg">0 VND</h2>
                                    </div>
                                </div>
                            </div>
                            <div id="salaryError" class="alert alert-warning mt-3" style="display: none;">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <span id="salaryErrorMessage">Chưa có dữ liệu lương cho tháng này</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('styles')
<style>
    .content-area { padding: 1.5rem; }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }
    .page-header h1 { font-size: 1.75rem; font-weight: 600; }
    .breadcrumb { background: none; padding: 0; margin: 0; }
    .breadcrumb-item a { text-decoration: none; color: var(--bs-primary); }
    #statsCards .card {
        transition: transform 0.2s ease;
        border: none;
        border-radius: 12px;
    }
    #statsCards .card:hover { transform: translateY(-5px); }
    .table-responsive { max-height: 300px; overflow-y: auto; }
    .bg-opacity-10 { --bs-bg-opacity: 0.1; }
    @media (max-width: 576px) {
        .page-header h1 { font-size: 1.3rem; }
        .content-area { padding: 0.75rem; }
        #statsCards .card h2 { font-size: 1.5rem; }
    }
    @media (max-width: 768px) {
        .page-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
        .page-header .right { width: 100%; }
        .page-header .right .btn { width: 100%; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    let educationChart = null;
    let departmentChart = null;
    let refreshTimer = null;
    const REFRESH_INTERVAL = 5 * 60 * 1000;

    async function fetchDashboardData() {
        const loading = document.getElementById('dashboardLoading');
        const content = document.getElementById('dashboardContent');
        
        loading.style.display = 'block';
        content.style.display = 'none';

        try {
            const response = await fetch('/api/v1/dashboard/overview', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const result = await response.json();
            
            if (!result.success) {
                throw new Error(result.message || 'Lỗi không xác định');
            }

            renderDashboard(result.data);

        } catch (error) {
            console.error('[Dashboard] Lỗi tải dữ liệu:', error);
            showError('Không thể tải dữ liệu dashboard. Vui lòng thử lại sau.');
        } finally {
            loading.style.display = 'none';
            content.style.display = 'block';
        }
    }

    function renderDashboard(data) {
        // Thống kê nhanh
        const totalEmployees = data.nhan_vien_theo_hoc_van?.reduce((sum, item) => sum + item.total, 0) || 0;
        document.getElementById('totalEmployees').textContent = totalEmployees;

        const departments = data.nhan_vien_theo_phong_ban || [];
        document.getElementById('totalDepartments').textContent = departments.length || data.tong_phong_ban || 0;

        const contracts = data.hop_dong_sap_het_han || [];
        document.getElementById('expiringContracts').textContent = contracts.length;

        const pendingLeaveCount = data.pending_department_leave_count;
        document.getElementById('pendingDepartmentLeaveCount').textContent = pendingLeaveCount === null || pendingLeaveCount === undefined
            ? '—'
            : pendingLeaveCount;

        // Báo cáo chấm công
        renderAttendanceReport(data.bao_cao_cham_cong || {});
        
        // Báo cáo lương
        renderSalaryReport(data.bao_cao_luong || {});
        
        // Biểu đồ
        renderEducationChart(data.nhan_vien_theo_hoc_van || []);
        renderDepartmentChart(data.nhan_vien_theo_phong_ban || []);
        renderContractsTable(data.hop_dong_sap_het_han || []);
    }

    function renderAttendanceReport(data) {
        const month = data.thang || new Date().getMonth() + 1;
        const year = data.nam || new Date().getFullYear();
        document.getElementById('attendanceMonth').textContent = `- Tháng ${month}/${year}`;
        
        const totalEmployees = data.tong_nhan_vien || 0;
        const totalShifts = data.tong_ca_cham_cong || 0;
        const late = data.so_lan_vao_muon || 0;
        const early = data.so_lan_ve_som || 0;
        const avgHours = data.gio_lam_trung_binh || 0;
        
        // Tính số người đi làm và vắng trong tháng
        // Giả định mỗi nhân viên đi làm trung bình 22 ngày/tháng
        const workingDays = 22;
        const estimatedEmployees = Math.round(totalShifts / (workingDays || 1));
        const present = Math.min(estimatedEmployees, totalEmployees);
        const absent = totalEmployees - present;

        document.getElementById('attTotalEmployees').textContent = totalEmployees;
        document.getElementById('attTotalShifts').textContent = totalShifts;
        document.getElementById('attPresent').textContent = present;
        document.getElementById('attAbsent').textContent = absent;
        document.getElementById('attLate').textContent = late;
        document.getElementById('attEarly').textContent = early;
        document.getElementById('attAvgHours').textContent = avgHours.toFixed(1);

        // Cập nhật card thống kê nhanh
        document.getElementById('todayAttendance').textContent = present;
        document.getElementById('attendancePercent').textContent = totalEmployees > 0 ? Math.round((present / totalEmployees) * 100) + '%' : '0%';
        document.getElementById('todayAbsent').textContent = absent;
        document.getElementById('absentPercent').textContent = totalEmployees > 0 ? Math.round((absent / totalEmployees) * 100) + '%' : '0%';
    }

    function renderSalaryReport(data) {
        const month = data.thang || new Date().getMonth() + 1;
        const year = data.nam || new Date().getFullYear();
        document.getElementById('salaryReportMonth').textContent = `- Tháng ${month}/${year}`;
        
        const formatter = new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' });
        
        const employees = data.so_nguoi || 0;
        document.getElementById('salEmployees').textContent = employees;
        
        if (employees > 0) {
            document.getElementById('salTotal').textContent = formatter.format(data.tong_luong || 0);
            document.getElementById('salAvg').textContent = formatter.format(data.luong_trung_binh || 0);
            document.getElementById('salaryError').style.display = 'none';
        } else {
            document.getElementById('salTotal').textContent = '0 VND';
            document.getElementById('salAvg').textContent = '0 VND';
            const errorMsg = data.error || 'Chưa có dữ liệu lương cho tháng này';
            document.getElementById('salaryErrorMessage').textContent = errorMsg;
            document.getElementById('salaryError').style.display = 'block';
        }
    }

    function renderEducationChart(data) {
        const ctx = document.getElementById('educationChart').getContext('2d');
        const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
        
        if (educationChart) { educationChart.destroy(); educationChart = null; }

        if (!data || data.length === 0) {
            educationChart = new Chart(ctx, {
                type: 'pie',
                data: { labels: ['Chưa có dữ liệu'], datasets: [{ data: [1], backgroundColor: ['#e9ecef'], borderWidth: 0 }] },
                options: { responsive: true, plugins: { legend: { display: false }, tooltip: { enabled: false } } }
            });
            return;
        }

        const labels = data.map(item => item.hoc_van || 'Chưa xác định');
        const values = data.map(item => item.total);

        educationChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors.slice(0, values.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 10, usePointStyle: true, font: { size: 12 } } },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return `${context.label}: ${context.parsed} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    function renderDepartmentChart(data) {
        const ctx = document.getElementById('departmentChart').getContext('2d');
        const colors = ['#4BC0C0', '#36A2EB', '#FFCE56', '#FF6384', '#9966FF', '#FF9F40', '#C9CBCF'];
        
        if (departmentChart) { departmentChart.destroy(); departmentChart = null; }

        if (!data || data.length === 0) {
            departmentChart = new Chart(ctx, {
                type: 'bar',
                data: { labels: ['Chưa có dữ liệu'], datasets: [{ data: [0], backgroundColor: ['#e9ecef'], borderColor: ['#e9ecef'], borderWidth: 1 }] },
                options: { responsive: true, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { y: { beginAtZero: true } } }
            });
            return;
        }

        const labels = data.map(item => item.ten_pb || 'Chưa xác định');
        const values = data.map(item => item.total);

        departmentChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Số nhân viên',
                    data: values,
                    backgroundColor: colors.slice(0, values.length),
                    borderColor: colors.slice(0, values.length),
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value == null ? '' : String(value);
        return element.innerHTML;
    }

    function formatDisplayDate(value) {
        return window.qlns && typeof window.qlns.formatDisplayDate === 'function'
            ? window.qlns.formatDisplayDate(value)
            : '';
    }

    function renderContractsTable(contracts) {
        const tbody = document.getElementById('contractsTableBody');
        
        if (!contracts || contracts.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i> Không có hợp đồng nào sắp hết hạn</td></tr>`;
            return;
        }

        let html = '';
        contracts.forEach((contract, index) => {
            const daysLeft = contract.so_ngay_con_lai || 0;
            let badgeClass = 'bg-success', badgeText = `${daysLeft} ngày`;
            if (daysLeft <= 0) { badgeClass = 'bg-danger'; badgeText = '⚠️ Đã hết hạn'; }
            else if (daysLeft <= 7) { badgeClass = 'bg-danger'; badgeText = `⚠️ ${daysLeft} ngày`; }
            else if (daysLeft <= 15) { badgeClass = 'bg-warning text-dark'; badgeText = `📅 ${daysLeft} ngày`; }

            const startDate = formatDisplayDate(contract.ngay_bat_dau) || '-';
            const endDate = formatDisplayDate(contract.ngay_ket_thuc) || '-';

            html += `<tr>
                <td class="ps-3">${index + 1}</td>
                <td><strong>${escapeHtml(contract.ho_ten || 'Chưa cập nhật')}</strong></td>
                <td>${escapeHtml(contract.ten_loai_hop_dong || 'Chưa xác định')}</td>
                <td>${escapeHtml(startDate)} – ${escapeHtml(endDate)}</td>
                <td><span class="badge ${badgeClass} px-3 py-2">${escapeHtml(badgeText)}</span></td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    function showError(message) {
        const content = document.getElementById('dashboardContent');
        content.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>Lỗi!</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <div class="text-center py-5">
                <i class="bi bi-database-exclamation display-1 text-muted"></i>
                <p class="mt-3 text-muted">Vui lòng kiểm tra kết nối database hoặc thử lại sau.</p>
                <button class="btn btn-primary" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Tải lại trang</button>
            </div>
        `;
    }

    function setupAutoRefresh() {
        if (refreshTimer) clearInterval(refreshTimer);
        refreshTimer = setInterval(fetchDashboardData, REFRESH_INTERVAL);
    }

    fetchDashboardData();
    setupAutoRefresh();

    document.getElementById('refreshDashboard').addEventListener('click', function() {
        const originalText = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Đang tải...';
        fetchDashboardData().finally(() => {
            this.disabled = false;
            this.innerHTML = originalText;
        });
    });
});
</script>
@endpush
