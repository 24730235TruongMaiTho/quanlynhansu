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
            @php
                $employeeGroupActive = request()->routeIs('backend.nhanvien.*');
                $departmentGroupActive = request()->routeIs('backend.phongban.*');
                $positionGroupActive = request()->routeIs('backend.chucvu.*');
                $contractGroupActive = request()->routeIs('backend.hopdong.*');
                $attendanceGroupActive = request()->routeIs('backend.chamcong.*');
                $leaveGroupActive = request()->routeIs('backend.nghiphep.*');
                $salaryGroupActive = request()->routeIs('backend.luong.*');
                $authorizationGroupActive = request()->routeIs('backend.vaitro.*', 'backend.taikhoan.*');
            @endphp
            <!-- Liên kết tới trang tổng quan -->
            <li class="nav-item">
                <a href="{{ route('backend.tongquan.index') }}" class="nav-link {{ request()->routeIs('backend.tongquan.index') ? 'active' : '' }}"
                    @if (request()->routeIs('backend.tongquan.index')) aria-current="page" @endif>
                    <i class="bi bi-house-fill"></i>
                    <span class="nav-title">Tổng quan</span>
                </a>
            </li>
            @php
                $sidebarUser = auth()->user();
            @endphp
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'NhanVien'))
                <!-- Nhân sự -->
                <li class="nav-item" data-sidebar-group="employees" data-route-active="{{ $employeeGroupActive ? 'true' : 'false' }}">
                    <a href="#" class="nav-link" data-toggle="submenu" aria-expanded="{{ $employeeGroupActive ? 'true' : 'false' }}">
                        <i class="bi bi-people-fill"></i>
                        <span class="nav-title">Quản lý nhân viên</span>
                        <i class="bi bi-chevron-down menu-arrow {{ $employeeGroupActive ? 'rotated' : '' }}" aria-hidden="true"></i>
                    </a>
                    <ul class="sub-menu {{ $employeeGroupActive ? 'open' : '' }}">
                        <li class="nav-item">
                            <a href="{{ route('backend.nhanvien.index') }}" class="nav-link {{ request()->routeIs('backend.nhanvien.index') ? 'active' : '' }}"
                                @if (request()->routeIs('backend.nhanvien.index')) aria-current="page" @endif>
                                <i class="bi bi-person-lines-fill"></i>
                                <span class="nav-title">Danh sách nhân viên</span>
                            </a>
                        </li>
                    </ul>
                </li>
            @endif
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'PhongBan'))
                <li class="nav-item" data-sidebar-group="departments" data-route-active="{{ $departmentGroupActive ? 'true' : 'false' }}">
                    <a href="#" class="nav-link" data-toggle="submenu" aria-expanded="{{ $departmentGroupActive ? 'true' : 'false' }}">
                        <i class="bi bi-building-fill" aria-hidden="true"></i>
                        <span class="nav-title">Quản lý phòng ban</span>
                        <i class="bi bi-chevron-down menu-arrow {{ $departmentGroupActive ? 'rotated' : '' }}" aria-hidden="true"></i>
                    </a>
                    <ul class="sub-menu {{ $departmentGroupActive ? 'open' : '' }}">
                        <li class="nav-item">
                            <a href="{{ route('backend.phongban.index') }}" class="nav-link {{ request()->routeIs('backend.phongban.index') ? 'active' : '' }}"
                                @if (request()->routeIs('backend.phongban.index')) aria-current="page" @endif>
                                <i class="bi bi-list-ul"></i>
                                <span class="nav-title">Danh sách phòng ban</span>
                            </a>
                        </li>
                    </ul>
                </li>
            @endif
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'ChucVu'))
                <li class="nav-item" data-sidebar-group="positions" data-route-active="{{ $positionGroupActive ? 'true' : 'false' }}">
                    <a href="#" class="nav-link" data-toggle="submenu" aria-expanded="{{ $positionGroupActive ? 'true' : 'false' }}">
                        <i class="bi bi-person-badge" aria-hidden="true"></i>
                        <span class="nav-title">Quản lý chức vụ</span>
                        <i class="bi bi-chevron-down menu-arrow {{ $positionGroupActive ? 'rotated' : '' }}" aria-hidden="true"></i>
                    </a>
                    <ul class="sub-menu {{ $positionGroupActive ? 'open' : '' }}">
                        <li class="nav-item">
                                <a href="{{ route('backend.chucvu.index') }}" class="nav-link {{ request()->routeIs('backend.chucvu.index') ? 'active' : '' }}"
                                    @if (request()->routeIs('backend.chucvu.index')) aria-current="page" @endif>
                                <i class="bi bi-list-ul" aria-hidden="true"></i>
                                <span class="nav-title">Danh sách chức vụ</span>
                            </a>
                        </li>
                        @can(\App\Enums\ChucVuPermission::Tao->value)
                            <li class="nav-item">
                                <a href="{{ route('backend.chucvu.create') }}" class="nav-link {{ request()->routeIs('backend.chucvu.create') ? 'active' : '' }}"
                                    @if (request()->routeIs('backend.chucvu.create')) aria-current="page" @endif>
                                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                                    <span class="nav-title">Thêm chức vụ</span>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>
            @endif
            <!-- Quản lý hợp đồng -->
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'HopDong'))
            <li class="nav-item" data-sidebar-group="contracts" data-route-active="{{ $contractGroupActive ? 'true' : 'false' }}">
                <a href="#" class="nav-link" data-toggle="submenu" aria-expanded="{{ $contractGroupActive ? 'true' : 'false' }}">
                    <i class="bi bi-file-earmark-text-fill"></i>
                    <span class="nav-title">Quản lý hợp đồng</span>
                    <i class="bi bi-chevron-down menu-arrow {{ $contractGroupActive ? 'rotated' : '' }}" aria-hidden="true"></i>
                </a>
                <ul class="sub-menu {{ $contractGroupActive ? 'open' : '' }}">
                    <li class="nav-item"><a href="{{ route('backend.hopdong.index') }}" class="nav-link {{ request()->routeIs('backend.hopdong.index') ? 'active' : '' }}" @if (request()->routeIs('backend.hopdong.index')) aria-current="page" @endif><i class="bi bi-file-earmark-text"></i><span class="nav-title">Danh sách hợp đồng</span></a></li>
                </ul>
            </li>
            @endif
            <!-- Quản lý chấm công -->
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'ChamCong'))
            <li class="nav-item" data-sidebar-group="attendance" data-route-active="{{ $attendanceGroupActive ? 'true' : 'false' }}">
                <a href="#" class="nav-link" data-toggle="submenu" aria-expanded="{{ $attendanceGroupActive ? 'true' : 'false' }}">
                    <i class="bi bi-calendar-check-fill"></i>
                    <span class="nav-title">Quản lý chấm công</span>
                    <i class="bi bi-chevron-down menu-arrow {{ $attendanceGroupActive ? 'rotated' : '' }}" aria-hidden="true"></i>
                </a>
                <ul class="sub-menu {{ $attendanceGroupActive ? 'open' : '' }}">
                    <li class="nav-item"><a href="{{ route('backend.chamcong.index') }}" class="nav-link {{ request()->routeIs('backend.chamcong.index') ? 'active' : '' }}" @if (request()->routeIs('backend.chamcong.index')) aria-current="page" @endif><i class="bi bi-calendar3"></i><span class="nav-title">Danh sách chấm công</span></a></li>
                </ul>
            </li>
            @endif
            <!-- Quản lý nghỉ phép -->
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'NghiPhep'))
            <li class="nav-item" data-sidebar-group="leave" data-route-active="{{ $leaveGroupActive ? 'true' : 'false' }}">
                <a href="#" class="nav-link" data-toggle="submenu" aria-expanded="{{ $leaveGroupActive ? 'true' : 'false' }}">
                    <i class="bi bi-calendar-x-fill"></i>
                    <span class="nav-title">Quản lý nghỉ phép</span>
                    <i class="bi bi-chevron-down menu-arrow {{ $leaveGroupActive ? 'rotated' : '' }}" aria-hidden="true"></i>
                </a>
                <ul class="sub-menu {{ $leaveGroupActive ? 'open' : '' }}">
                    @if (app(\App\Services\PermissionService::class)->allows($sidebarUser, \App\Enums\NghiPhepPermission::Tao->value))
                        <li class="nav-item"><a href="{{ route('backend.nghiphep.create') }}" class="nav-link {{ request()->routeIs('backend.nghiphep.create') ? 'active' : '' }}" @if (request()->routeIs('backend.nghiphep.create')) aria-current="page" @endif><i class="bi bi-calendar-plus"></i><span class="nav-title">Tạo nghỉ phép</span></a></li>
                    @endif
                    <li class="nav-item"><a href="{{ route('backend.nghiphep.index') }}" class="nav-link {{ request()->routeIs('backend.nghiphep.index') ? 'active' : '' }}" @if (request()->routeIs('backend.nghiphep.index')) aria-current="page" @endif><i class="bi bi-calendar3"></i><span class="nav-title">Danh sách nghỉ phép</span></a></li>
                    @can('department-manager')
                        @can(\App\Enums\NghiPhepPermission::Sua->value)
                            <li class="nav-item"><a href="{{ route('backend.nghiphep.duyet-nghi-phep') }}" class="nav-link {{ request()->routeIs('backend.nghiphep.duyet-nghi-phep') ? 'active' : '' }}" @if (request()->routeIs('backend.nghiphep.duyet-nghi-phep')) aria-current="page" @endif><i class="bi bi-check2-square"></i><span class="nav-title">Duyệt nghỉ phép</span></a></li>
                        @endcan
                    @endcan
                </ul>
            </li>
            @endif
            <!-- Quản lý lương -->
            @if ($sidebarUser instanceof \App\Models\NhanVien)
                @php
                    $canSeeSalary = app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'Luong')
                        && app(\App\Services\PermissionService::class)->allows($sidebarUser, \App\Enums\LuongPermission::Xem->value);
                    $canSeeSalaryCoefficients = app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'HeSoLuong')
                        && app(\App\Services\PermissionService::class)->allows($sidebarUser, \App\Enums\HeSoLuongPermission::Xem->value);
                @endphp
                @if ($canSeeSalary || $canSeeSalaryCoefficients)
                    <li class="nav-item" data-sidebar-group="salary" data-route-active="{{ $salaryGroupActive ? 'true' : 'false' }}">
                        <a href="#" class="nav-link" data-toggle="submenu" aria-expanded="{{ $salaryGroupActive ? 'true' : 'false' }}">
                            <i class="bi bi-cash-stack"></i>
                            <span class="nav-title">Quản lý lương</span>
                            <i class="bi bi-chevron-down menu-arrow {{ $salaryGroupActive ? 'rotated' : '' }}" aria-hidden="true"></i>
                        </a>
                        <ul class="sub-menu {{ $salaryGroupActive ? 'open' : '' }}">
                            @if ($canSeeSalary)
                                <li class="nav-item"><a href="{{ route('backend.luong.index') }}" class="nav-link {{ request()->routeIs('backend.luong.index') ? 'active' : '' }}" @if (request()->routeIs('backend.luong.index')) aria-current="page" @endif><i class="bi bi-cash-coin"></i><span class="nav-title">Danh sách lương</span></a></li>
                            @endif
                            @if ($canSeeSalaryCoefficients)
                                <li class="nav-item"><a href="{{ route('backend.luong.index') }}#salary-coefficient-card" class="nav-link {{ request()->routeIs('backend.luong.index') ? 'active' : '' }}" @if (request()->routeIs('backend.luong.index')) aria-current="page" @endif><i class="bi bi-graph-up-arrow"></i><span class="nav-title">Danh sách hệ số lương</span></a></li>
                            @endif
                        </ul>
                    </li>
                @endif
            @endif
            @if ($sidebarUser instanceof \App\Models\NhanVien
                && (app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'VaiTro')
                    || app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'PhanQuyen')))
            <li class="nav-item" data-sidebar-group="authorization" data-route-active="{{ $authorizationGroupActive ? 'true' : 'false' }}">
                <a href="#" class="nav-link" data-toggle="submenu" aria-expanded="{{ $authorizationGroupActive ? 'true' : 'false' }}">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span class="nav-title">Vai trò và phân quyền</span>
                    <i class="bi bi-chevron-down menu-arrow {{ $authorizationGroupActive ? 'rotated' : '' }}" aria-hidden="true"></i>
                </a>
                <ul class="sub-menu {{ $authorizationGroupActive ? 'open' : '' }}">
                    @if (app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'VaiTro'))
                    <li class="nav-item"><a href="{{ route('backend.vaitro.index') }}" class="nav-link {{ request()->routeIs('backend.vaitro.*') ? 'active' : '' }}" @if (request()->routeIs('backend.vaitro.*')) aria-current="page" @endif><i class="bi bi-person-gear"></i><span class="nav-title">Danh sách vai trò</span></a></li>
                    @endif
                    @if (app(\App\Services\PermissionService::class)->canSeeModule($sidebarUser, 'PhanQuyen'))
                    <li class="nav-item"><a href="{{ route('backend.taikhoan.index') }}" class="nav-link {{ request()->routeIs('backend.taikhoan.*') ? 'active' : '' }}" @if (request()->routeIs('backend.taikhoan.*')) aria-current="page" @endif><i class="bi bi-person-check-fill"></i><span class="nav-title">Phân Quyền</span></a></li>
                    @endif
                </ul>
            </li>
            @endif
        </ul>
    </div>
</nav>
