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
            @php($sidebarUser = auth()->user())
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && config('nhanvien.enabled') === true
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'NhanVien'))
                <!-- Nhân sự -->
                <li class="nav-item">
                    <a href="#" class="nav-link" data-toggle="submenu">
                        <i class="bi bi-people-fill"></i>
                        <span class="nav-title">Nhân sự</span>
                        <i class="bi bi-chevron-down menu-arrow rotated"></i>
                    </a>
                    <ul class="sub-menu">
                        <li class="nav-item">
                            <a href="{{ route('backend.nhanvien.index') }}" class="nav-link">
                                <i class="bi bi-person-lines-fill"></i>
                                <span class="nav-title">Danh sách nhân viên</span>
                            </a>
                        </li>
                    </ul>
                </li>
            @endif
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'PhongBan'))
                <li class="nav-item">
                    <a href="{{ route('backend.phongban.index') }}" class="nav-link">
                        <i class="bi bi-building" aria-hidden="true"></i>
                        <span class="nav-title">Phòng ban</span>
                    </a>
                </li>
            @endif
            <!-- Chấm công -->
            <li class="nav-item">
                <a href="{{ route('backend.backend.chamcong.index') }}" class="nav-link">
                    <i class="bi bi-calendar-check-fill"></i>
                    <span class="nav-title">Chấm công</span>
                </a>
            </li>
        </ul>
    </div>
</nav>
