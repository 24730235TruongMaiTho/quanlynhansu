@extends('backend.layouts.app')
@section('title', 'Danh sách nhân viên - Quản lý nhân sự')
@section('content')
<!-- ===== CONTENT AREA ===== -->
<div class="content-area">
    <!-- Page Header -->
    <div class="page-header">
        <div class="left">
            <div>
                <h1><i class="bi bi-people-fill text-danger me-2"></i>Danh sách nhân viên</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="#">Trang chủ</a></li>
                        <li class="breadcrumb-item"><a href="#">Nhân sự</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Danh sách nhân viên</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div>
            <a href="#" class="btn-primary-custom" id="addEmployeeBtn">
                <i class="bi bi-person-plus-fill"></i> Thêm nhân viên
            </a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <div class="filter-group">
            <label for="filterDepartment"><i class="bi bi-building"></i> Phòng ban</label>
            <select class="form-select" id="filterDepartment">
                <option value="">Tất cả</option>
                <option value="it">Kỹ thuật</option>
                <option value="sales">Kinh doanh</option>
                <option value="hr">Nhân sự</option>
                <option value="finance">Tài chính</option>
                <option value="marketing">Marketing</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="filterStatus"><i class="bi bi-circle"></i> Trạng thái</label>
            <select class="form-select" id="filterStatus">
                <option value="">Tất cả</option>
                <option value="active">Đang làm việc</option>
                <option value="probation">Thử việc</option>
                <option value="inactive">Đã nghỉ</option>
            </select>
        </div>
        <div class="filter-group" style="flex: 1; min-width: 200px;">
            <input type="text" class="search-input" id="searchEmployee" placeholder="Tìm kiếm nhân viên...">
        </div>
        <div class="filter-group">
            <button class="btn-filter" id="filterBtn">Lọc</button>
            <button class="btn-reset" id="resetFilterBtn">Đặt lại</button>
        </div>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
        <div class="table-header">
            <div class="info">
                Hiển thị <strong id="startCount">1</strong> - <strong id="endCount">10</strong> trong tổng số <strong id="totalCount">0</strong> nhân viên
            </div>
            <div>
                <select class="form-select" id="pageSizeSelect" style="width: auto; display: inline-block; padding: 6px 30px 6px 14px; font-size: 14px; border-radius: 10px; border: 1.5px solid #e0e0e0;">
                    <option value="5">5 / trang</option>
                    <option value="10" selected>10 / trang</option>
                    <option value="20">20 / trang</option>
                    <option value="50">50 / trang</option>
                </select>
            </div>
        </div>

        <table class="table" id="employeeTable">
            <thead>
                <tr>
                    <th data-sort="name" style="min-width: 200px;">
                        Nhân viên <i class="bi bi-arrow-up-short sort-icon"></i>
                    </th>
                    <th data-sort="department">
                        Phòng ban <i class="bi bi-arrow-up-short sort-icon"></i>
                    </th>
                    <th data-sort="position">
                        Chức vụ <i class="bi bi-arrow-up-short sort-icon"></i>
                    </th>
                    <th data-sort="status">
                        Trạng thái <i class="bi bi-arrow-up-short sort-icon"></i>
                    </th>
                    <th data-sort="salary">
                        Lương <i class="bi bi-arrow-up-short sort-icon"></i>
                    </th>
                    <th style="text-align: center;">Thao tác</th>
                </tr>
            </thead>
            <tbody id="employeeTableBody">
                <!-- Dữ liệu sẽ được render bằng JavaScript -->
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="pagination-wrapper">
            <div class="info" id="paginationInfo">
                Hiển thị 1-10 trong tổng số 0 nhân viên
            </div>
            <ul class="pagination" id="pagination">
                <!-- Pagination sẽ được render bằng JavaScript -->
            </ul>
        </div>
    </div>
</div>
@endsection
@push('styles')
<style>
    /* Filter Section */
        .filter-section {
            background: #fff;
            padding: 20px 25px;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .filter-section .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-section .filter-group label {
            font-weight: 500;
            color: #495057;
            font-size: 14px;
            margin: 0;
            white-space: nowrap;
        }

        .filter-section .form-select {
            border-radius: 10px;
            border: 1.5px solid #e0e0e0;
            padding: 8px 30px 8px 14px;
            font-size: 14px;
            min-width: 150px;
            background-color: #f8f9fa;
            cursor: pointer;
        }

        .filter-section .form-select:focus {
            border-color: #e94560;
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.1);
            outline: none;
        }

        .filter-section .btn-filter {
            background: #e94560;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-section .btn-filter:hover {
            background: #d63851;
        }

        .filter-section .btn-reset {
            background: #f8f9fa;
            border: 1.5px solid #e0e0e0;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            color: #495057;
        }

        .filter-section .btn-reset:hover {
            background: #f0f0f0;
        }

        .filter-section .search-input {
            padding: 8px 16px 8px 36px;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            min-width: 200px;
            background: #f8f9fa url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236c757d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'%3E%3C/circle%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'%3E%3C/line%3E%3C/svg%3E") no-repeat 12px center;
            transition: all 0.3s ease;
        }

        .filter-section .search-input:focus {
            border-color: #e94560;
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.1);
            outline: none;
            background-color: #fff;
            min-width: 250px;
        }

        /* Table */
        .table-wrapper {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            overflow-x: auto;
        }

        .table-wrapper .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .table-wrapper .table-header .info {
            font-size: 14px;
            color: #6c757d;
        }

        .table-wrapper .table-header .info strong {
            color: #1a1a2e;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .table thead th {
            background: #f8f9fa;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
            position: relative;
        }

        .table thead th:hover {
            background: #f0f0f0;
        }

        .table thead th .sort-icon {
            margin-left: 4px;
            font-size: 12px;
            opacity: 0.3;
        }

        .table thead th.active .sort-icon {
            opacity: 1;
            color: #e94560;
        }

        .table tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
            color: #495057;
        }

        .table tbody tr:hover {
            background: rgba(233, 69, 96, 0.03);
        }

        .table .avatar-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .table .avatar-cell .avatar-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e94560;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            flex-shrink: 0;
        }

        .table .avatar-cell .avatar-img img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .table .avatar-cell .name-info .name {
            font-weight: 500;
            color: #1a1a2e;
        }

        .table .avatar-cell .name-info .code {
            font-size: 12px;
            color: #adb5bd;
        }

        .table .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .table .badge-status.active {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }

        .table .badge-status.inactive {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }

        .table .badge-status.probation {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }

        .table .btn-action {
            background: transparent;
            border: none;
            padding: 4px 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            color: #6c757d;
            font-size: 16px;
        }

        .table .btn-action:hover {
            background: rgba(233, 69, 96, 0.08);
            color: #e94560;
        }

        .table .btn-action.delete:hover {
            background: rgba(220, 53, 69, 0.08);
            color: #dc3545;
        }

        /* Pagination */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .pagination-wrapper .info {
            font-size: 14px;
            color: #6c757d;
        }

        .pagination-wrapper .pagination {
            display: flex;
            gap: 6px;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .pagination-wrapper .pagination li {
            display: inline-block;
        }

        .pagination-wrapper .pagination a {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            color: #495057;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }

        .pagination-wrapper .pagination a:hover {
            background: rgba(233, 69, 96, 0.08);
            border-color: #e94560;
            color: #e94560;
        }

        .pagination-wrapper .pagination .active a {
            background: #e94560;
            border-color: #e94560;
            color: #fff;
        }

        .pagination-wrapper .pagination .active a:hover {
            background: #d63851;
            border-color: #d63851;
        }

        .pagination-wrapper .pagination .disabled a {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
</style>
@endpush
@push('scripts')
<script>
(function(){
// ===== DATA =====
    const employees = [
        { id: 1, name: 'Nguyễn Văn An', code: 'EMP001', department: 'it', departmentLabel: 'Kỹ thuật', position: 'Trưởng phòng', status: 'active', statusLabel: 'Đang làm việc', salary: 25000000, avatar: '' },
        { id: 2, name: 'Trần Thị Bình', code: 'EMP002', department: 'sales', departmentLabel: 'Kinh doanh', position: 'Nhân viên', status: 'active', statusLabel: 'Đang làm việc', salary: 15000000, avatar: '' },
        { id: 3, name: 'Lê Văn Cường', code: 'EMP003', department: 'hr', departmentLabel: 'Nhân sự', position: 'Nhân viên', status: 'probation', statusLabel: 'Thử việc', salary: 12000000, avatar: '' },
        { id: 4, name: 'Phạm Thị Dung', code: 'EMP004', department: 'finance', departmentLabel: 'Tài chính', position: 'Trưởng phòng', status: 'active', statusLabel: 'Đang làm việc', salary: 22000000, avatar: '' },
        { id: 5, name: 'Hoàng Văn Em', code: 'EMP005', department: 'marketing', departmentLabel: 'Marketing', position: 'Nhân viên', status: 'active', statusLabel: 'Đang làm việc', salary: 14000000, avatar: '' },
        { id: 6, name: 'Vũ Thị Phương', code: 'EMP006', department: 'it', departmentLabel: 'Kỹ thuật', position: 'Nhân viên', status: 'active', statusLabel: 'Đang làm việc', salary: 18000000, avatar: '' },
        { id: 7, name: 'Đặng Văn Giang', code: 'EMP007', department: 'sales', departmentLabel: 'Kinh doanh', position: 'Trưởng phòng', status: 'inactive', statusLabel: 'Đã nghỉ', salary: 20000000, avatar: '' },
        { id: 8, name: 'Ngô Thị Hạnh', code: 'EMP008', department: 'hr', departmentLabel: 'Nhân sự', position: 'Trưởng phòng', status: 'active', statusLabel: 'Đang làm việc', salary: 21000000, avatar: '' },
        { id: 9, name: 'Lý Văn Ích', code: 'EMP009', department: 'finance', departmentLabel: 'Tài chính', position: 'Nhân viên', status: 'probation', statusLabel: 'Thử việc', salary: 13000000, avatar: '' },
        { id: 10, name: 'Mai Thị Kim', code: 'EMP010', department: 'marketing', departmentLabel: 'Marketing', position: 'Trưởng phòng', status: 'active', statusLabel: 'Đang làm việc', salary: 23000000, avatar: '' },
        { id: 11, name: 'Trịnh Văn Lâm', code: 'EMP011', department: 'it', departmentLabel: 'Kỹ thuật', position: 'Nhân viên', status: 'active', statusLabel: 'Đang làm việc', salary: 16000000, avatar: '' },
        { id: 12, name: 'Hoàng Thị Mai', code: 'EMP012', department: 'sales', departmentLabel: 'Kinh doanh', position: 'Nhân viên', status: 'active', statusLabel: 'Đang làm việc', salary: 14000000, avatar: '' },
        { id: 13, name: 'Phan Văn Nam', code: 'EMP013', department: 'hr', departmentLabel: 'Nhân sự', position: 'Nhân viên', status: 'inactive', statusLabel: 'Đã nghỉ', salary: 11000000, avatar: '' },
        { id: 14, name: 'Lê Thị Oanh', code: 'EMP014', department: 'finance', departmentLabel: 'Tài chính', position: 'Nhân viên', status: 'active', statusLabel: 'Đang làm việc', salary: 15000000, avatar: '' },
        { id: 15, name: 'Trần Văn Phúc', code: 'EMP015', department: 'marketing', departmentLabel: 'Marketing', position: 'Nhân viên', status: 'probation', statusLabel: 'Thử việc', salary: 12000000, avatar: '' }
    ];

    // ===== STATE =====
    let currentPage = 1;
    let pageSize = 10;
    let sortField = 'name';
    let sortDirection = 'asc';
    let filteredData = [...employees];

    // ===== DOM ELEMENTS =====
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggleSidebar');
    const toggleIcon = document.getElementById('toggleIcon');
    const backdrop = document.getElementById('sidebarBackdrop');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const employeeTableBody = document.getElementById('employeeTableBody');
    const pagination = document.getElementById('pagination');
    const paginationInfo = document.getElementById('paginationInfo');
    const startCount = document.getElementById('startCount');
    const endCount = document.getElementById('endCount');
    const totalCount = document.getElementById('totalCount');
    const pageSizeSelect = document.getElementById('pageSizeSelect');
    const filterDepartment = document.getElementById('filterDepartment');
    const filterStatus = document.getElementById('filterStatus');
    const searchEmployee = document.getElementById('searchEmployee');
    const filterBtn = document.getElementById('filterBtn');
    const resetFilterBtn = document.getElementById('resetFilterBtn');
    const addEmployeeBtn = document.getElementById('addEmployeeBtn');


    // ===== TABLE FUNCTIONS =====
            function getStatusBadge(status) {
                const statusMap = {
                    'active': '<span class="badge-status active">● Đang làm việc</span>',
                    'probation': '<span class="badge-status probation">● Thử việc</span>',
                    'inactive': '<span class="badge-status inactive">● Đã nghỉ</span>'
                };
                return statusMap[status] || status;
            }

            function getAvatarInitials(name) {
                const parts = name.split(' ');
                if (parts.length >= 2) {
                    return parts[0].charAt(0) + parts[parts.length - 1].charAt(0);
                }
                return name.charAt(0);
            }

            function formatSalary(salary) {
                return new Intl.NumberFormat('vi-VN', {
                    style: 'currency',
                    currency: 'VND',
                    minimumFractionDigits: 0
                }).format(salary);
            }

            function renderTable() {
                // Filter
                const department = filterDepartment.value;
                const status = filterStatus.value;
                const search = searchEmployee.value.toLowerCase().trim();

                filteredData = employees.filter(function(emp) {
                    let match = true;
                    if (department && emp.department !== department) match = false;
                    if (status && emp.status !== status) match = false;
                    if (search) {
                        const searchStr = (emp.name + ' ' + emp.code + ' ' + emp.departmentLabel).toLowerCase();
                        if (!searchStr.includes(search)) match = false;
                    }
                    return match;
                });

                // Sort
                filteredData.sort(function(a, b) {
                    let valA = a[sortField];
                    let valB = b[sortField];
                    if (typeof valA === 'string') {
                        valA = valA.toLowerCase();
                        valB = valB.toLowerCase();
                    }
                    if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
                    if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
                    return 0;
                });

                // Paginate
                const total = filteredData.length;
                const totalPages = Math.ceil(total / pageSize) || 1;
                if (currentPage > totalPages) currentPage = totalPages;
                const start = (currentPage - 1) * pageSize;
                const end = Math.min(start + pageSize, total);
                const pageData = filteredData.slice(start, end);

                // Update info
                startCount.textContent = total > 0 ? start + 1 : 0;
                endCount.textContent = end;
                totalCount.textContent = total;
                paginationInfo.textContent = 'Hiển thị ' + (total > 0 ? start + 1 : 0) + '-' + end + ' trong tổng số ' + total + ' nhân viên';

                // Render table rows
                if (pageData.length === 0) {
                    employeeTableBody.innerHTML = `
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px 20px; color: #6c757d;">
                                <i class="bi bi-inbox" style="font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.3;"></i>
                                Không có nhân viên nào phù hợp
                            </td>
                        </tr>
                    `;
                } else {
                    employeeTableBody.innerHTML = pageData.map(function(emp) {
                        return `
                            <tr>
                                <td>
                                    <div class="avatar-cell">
                                        <div class="avatar-img">
                                            ${emp.avatar ? `<img src="${emp.avatar}" alt="${emp.name}">` : getAvatarInitials(emp.name)}
                                        </div>
                                        <div class="name-info">
                                            <div class="name">${emp.name}</div>
                                            <div class="code">${emp.code}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>${emp.departmentLabel}</td>
                                <td>${emp.position}</td>
                                <td>${getStatusBadge(emp.status)}</td>
                                <td>${formatSalary(emp.salary)}</td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <button class="btn-action" data-id="${emp.id}" title="Xem chi tiết">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn-action" data-id="${emp.id}" title="Sửa">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn-action delete" data-id="${emp.id}" title="Xóa">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    }).join('');
                }

                // Render pagination
                renderPagination(currentPage, totalPages);

                // Update sort indicators
                document.querySelectorAll('#employeeTable thead th').forEach(function(th) {
                    th.classList.remove('active');
                    const field = th.getAttribute('data-sort');
                    if (field === sortField) {
                        th.classList.add('active');
                        const icon = th.querySelector('.sort-icon');
                        if (icon) {
                            icon.className = sortDirection === 'asc' ? 'bi bi-arrow-up-short sort-icon' : 'bi bi-arrow-down-short sort-icon';
                        }
                    }
                });
            }

            function renderPagination(current, totalPages) {
                let html = '';
                // Previous
                html += `<li class="${current <= 1 ? 'disabled' : ''}">
                    <a href="#" data-page="${current - 1}"><i class="bi bi-chevron-left"></i></a>
                </li>`;

                // Pages
                let startPage = Math.max(1, current - 2);
                let endPage = Math.min(totalPages, current + 2);
                if (startPage > 1) {
                    html += `<li><a href="#" data-page="1">1</a></li>`;
                    if (startPage > 2) {
                        html += `<li class="disabled"><a href="#">...</a></li>`;
                    }
                }
                for (let i = startPage; i <= endPage; i++) {
                    html += `<li class="${i === current ? 'active' : ''}">
                        <a href="#" data-page="${i}">${i}</a>
                    </li>`;
                }
                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) {
                        html += `<li class="disabled"><a href="#">...</a></li>`;
                    }
                    html += `<li><a href="#" data-page="${totalPages}">${totalPages}</a></li>`;
                }

                // Next
                html += `<li class="${current >= totalPages ? 'disabled' : ''}">
                    <a href="#" data-page="${current + 1}"><i class="bi bi-chevron-right"></i></a>
                </li>`;

                pagination.innerHTML = html;

                // Pagination click handlers
                pagination.querySelectorAll('a[data-page]').forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        e.preventDefault();
                        const page = parseInt(this.getAttribute('data-page'));
                        if (page >= 1 && page <= totalPages && page !== currentPage) {
                            currentPage = page;
                            renderTable();
                        }
                    });
                });
            }

            // ===== TABLE EVENT LISTENERS =====
            // Sort
            document.querySelectorAll('#employeeTable thead th[data-sort]').forEach(function(th) {
                th.addEventListener('click', function() {
                    const field = this.getAttribute('data-sort');
                    if (field === sortField) {
                        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortField = field;
                        sortDirection = 'asc';
                    }
                    currentPage = 1;
                    renderTable();
                });
            });

            // Page size
            pageSizeSelect.addEventListener('change', function() {
                pageSize = parseInt(this.value);
                currentPage = 1;
                renderTable();
            });

            // Filter
            filterBtn.addEventListener('click', function() {
                currentPage = 1;
                renderTable();
            });

            resetFilterBtn.addEventListener('click', function() {
                filterDepartment.value = '';
                filterStatus.value = '';
                searchEmployee.value = '';
                currentPage = 1;
                renderTable();
            });

            // Search with Enter key
            searchEmployee.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    currentPage = 1;
                    renderTable();
                }
            });

            // Add employee
            addEmployeeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                alert('📝 Chuyển đến trang thêm nhân viên mới!');
            });

            // Row actions (delegation)
            employeeTableBody.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-action');
                if (!btn) return;

                const id = parseInt(btn.getAttribute('data-id'));
                const employee = employees.find(function(emp) { return emp.id === id; });
                if (!employee) return;

                if (btn.classList.contains('delete')) {
                    if (confirm('Bạn có chắc chắn muốn xóa nhân viên "' + employee.name + '"?')) {
                        const index = employees.indexOf(employee);
                        if (index > -1) {
                            employees.splice(index, 1);
                            renderTable();
                            alert('✅ Đã xóa nhân viên "' + employee.name + '"');
                        }
                    }
                } else if (btn.querySelector('.bi-eye')) {
                    alert('👁️ Xem chi tiết nhân viên: ' + employee.name);
                } else if (btn.querySelector('.bi-pencil')) {
                    alert('✏️ Sửa thông tin nhân viên: ' + employee.name);
                }
            });
});
</script>
@endpush