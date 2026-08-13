<!-- Sidebar -->
<nav id="sidebar" class="sidebar">
    <!-- Header -->
    <div class="sidebar-header">
        <a href="#" class="brand">
            <i class="bi bi-hexagon-fill"></i>
            <span>QLNS</span>
        </a>
        <button class="toggle-btn" id="toggleSidebar" aria-label="Toggle sidebar">
            <i class="bi bi-chevron-left" id="toggleIcon"></i>
        </button>
    </div>

    <!-- Menu wrapper -->
    <div class="sidebar-menu-wrapper">
        <ul class="sidebar-menu">
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="/admin/bang-dieu-khien" class="nav-link">
                    <i class="bi bi-house-fill"></i>
                    <span class="nav-title">Bảng điều khiển</span>
                </a>
            </li>
            <!-- Nhân sự -->
            <li class="nav-item">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="bi bi-people-fill"></i>
                    <span class="nav-title">Nhân sự</span>
                    <i class="bi bi-chevron-down menu-arrow rotated"></i>
                </a>
                <ul class="sub-menu">
                    <li class="nav-item">
                        <a href="/admin/nhan-vien/danh-sach-nhan-vien" class="nav-link">
                            <i class="bi bi-person-lines-fill"></i>
                            <span class="nav-title">Danh sách nhân viên</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/admin/nhan-vien/them-nhan-vien" class="nav-link">
                            <i class="bi bi-person-plus-fill"></i>
                            <span class="nav-title">Thêm mới</span>
                        </a>
                    </li>
                </ul>
            </li>
        <!-- Chấm công -->
        <li class="nav-item">
            <a href="/admin/cham-cong" class="nav-link">
                <i class="bi bi-calendar-check-fill"></i>
                <span class="nav-title">Chấm công</span>
            </a>
        </li>
        </ul>
    </div>
</nav>