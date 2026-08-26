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
            <!-- Liên kết tới trang tổng quan -->
            <li class="nav-item">
                <a href="{{ route('backend.tongquan.index') }}" class="nav-link">
                    <i class="bi bi-house-fill"></i>
                    <span class="nav-title">Tổng quan</span>
                </a>
            </li>
            @php($sidebarUser = auth()->user())
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'NhanVien'))
                <!-- Nhân sự -->
                <li class="nav-item">
                    <a href="#" class="nav-link" data-toggle="submenu">
                        <i class="bi bi-people-fill"></i>
                        <span class="nav-title">Quản lý nhân viên</span>
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
                    <a href="#" class="nav-link" data-toggle="submenu">
                        <i class="bi bi-building-fill" aria-hidden="true"></i>
                        <span class="nav-title">Quản lý phòng ban</span>
                        <i class="bi bi-chevron-down menu-arrow rotated"></i>
                    </a>
                    <ul class="sub-menu">
                        <li class="nav-item">
                            <a href="{{ route('backend.phongban.index') }}" class="nav-link">
                                <i class="bi bi-list-ul"></i>
                                <span class="nav-title">Danh sách phòng ban</span>
                            </a>
                        </li>
                    </ul>
                </li>
            @endif
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'ChucVu'))
                <li class="nav-item">
                    <a href="{{ route('backend.chucvu.index') }}" class="nav-link">
                        <i class="bi bi-person-badge" aria-hidden="true"></i>
                        <span class="nav-title">Chức vụ</span>
                    </a>
                </li>
            @endif
            <!-- Quản lý hợp đồng -->
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'HopDong'))
            <li class="nav-item">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <span class="nav-title">Quản lý hợp đồng</span>
                    <i class="bi bi-chevron-down menu-arrow rotated"></i>
                </a>
                <ul class="sub-menu">
                    <li class="nav-item"><a href="{{ route('backend.hopdong.index') }}" class="nav-link"><i class="bi bi-file-earmark-text"></i><span class="nav-title">Danh sách hợp đồng</span></a></li>
                </ul>
            </li>
            @endif
            <!-- Quản lý chấm công -->
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'ChamCong'))
            <li class="nav-item">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="bi bi-calendar-check-fill"></i>
                    <span class="nav-title">Quản lý chấm công</span>
                    <i class="bi bi-chevron-down menu-arrow rotated"></i>
                </a>
                <ul class="sub-menu">
                    <li class="nav-item"><a href="{{ route('backend.chamcong.index') }}" class="nav-link"><i class="bi bi-calendar3"></i><span class="nav-title">Danh sách chấm công</span></a></li>
                </ul>
            </li>
            @endif
            <!-- Quản lý nghỉ phép -->
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'NghiPhep'))
            <li class="nav-item">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="bi bi-calendar-x-fill"></i>
                    <span class="nav-title">Quản lý nghỉ phép</span>
                    <i class="bi bi-chevron-down menu-arrow rotated"></i>
                </a>
                <ul class="sub-menu">
                    <li class="nav-item"><a href="#" class="nav-link"><i class="bi bi-calendar-plus"></i><span class="nav-title">Tạo nghỉ phép</span></a></li>
                    <li class="nav-item"><a href="{{ route('backend.nghiphep.index') }}" class="nav-link"><i class="bi bi-calendar3"></i><span class="nav-title">Danh sách nghỉ phép</span></a></li>
                </ul>
            </li>
            @endif
            <!-- Quản lý lương -->
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'Luong'))
            <li class="nav-item">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="bi bi-cash-stack"></i>
                    <span class="nav-title">Quản lý lương</span>
                    <i class="bi bi-chevron-down menu-arrow rotated"></i>
                </a>
                <ul class="sub-menu">
                    <li class="nav-item"><a href="{{ route('backend.luong.index') }}" class="nav-link"><i class="bi bi-cash-coin"></i><span class="nav-title">Danh sách lương</span></a></li>
                    <li class="nav-item"><a href="#" class="nav-link"><i class="bi bi-graph-up-arrow"></i><span class="nav-title">Danh sách hệ số lương</span></a></li>
                </ul>
            </li>
            @endif
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && (app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'VaiTro')
                    || app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'PhanQuyen')))
            <li class="nav-item">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span class="nav-title">Vai trò và phân quyền</span>
                    <i class="bi bi-chevron-down menu-arrow rotated"></i>
                </a>
                <ul class="sub-menu">
                    @if (app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'VaiTro'))
                    <li class="nav-item"><a href="{{ route('backend.vaitro.index') }}" class="nav-link"><i class="bi bi-person-gear"></i><span class="nav-title">Danh sách vai trò</span></a></li>
                    @endif
                    @if (app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'PhanQuyen'))
                    <li class="nav-item"><a href="{{ route('backend.taikhoan.index') }}" class="nav-link"><i class="bi bi-key-fill"></i><span class="nav-title">Phân quyền tài khoản</span></a></li>
                    @endif
                </ul>
            </li>
            @endif
        </ul>
    </div>
</nav>
