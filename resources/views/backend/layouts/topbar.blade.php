<!-- ===== TOP BAR ===== -->
<div class="top-bar">
    <div class="left-section">
        <button class="hamburger-btn" id="hamburgerBtn" aria-label="Open menu">
            <i class="bi bi-list"></i>
        </button>
        <h2 class="page-title">
            <i class="bi bi-person-plus-fill"></i>Thêm nhân viên
        </h2>
    </div>
    <div class="right-section">
        <!-- Search Box -->
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" placeholder="Tìm kiếm..." id="searchInput">
                </div>

                <!-- Notifications -->
                <div class="dropdown-container">
                    <button class="icon-btn hide-mobile" data-dropdown="notificationDropdown">
                        <i class="bi bi-bell-fill"></i>
                        <span class="badge-number">3</span>
                    </button>
                    <div class="dropdown-menu-custom" id="notificationDropdown">
                        <div class="dropdown-header">Thông báo</div>
                        <a class="dropdown-item" href="#">
                            <i class="bi bi-person-plus"></i>
                            <span>Nhân viên mới đăng ký</span>
                        </a>
                        <a class="dropdown-item" href="#">
                            <i class="bi bi-envelope"></i>
                            <span>5 email chưa đọc</span>
                        </a>
                        <a class="dropdown-item" href="#">
                            <i class="bi bi-clock-history"></i>
                            <span>Đơn hàng chờ xử lý</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item text-primary" href="#">
                            <i class="bi bi-eye"></i>
                            <span>Xem tất cả</span>
                        </a>
                    </div>
                </div>

                <!-- Settings Dropdown -->
                <div class="dropdown-container">
                    <button class="icon-btn hide-mobile" data-dropdown="settingsDropdown">
                        <i class="bi bi-grid-3x3-gap-fill"></i>
                    </button>
                    <div class="dropdown-menu-custom settings-dropdown" id="settingsDropdown">
                        <div class="dropdown-header">Chức năng nhanh</div>
                        <div class="grid-items">
                            <a href="#" class="dropdown-item">
                                <i class="bi bi-person-plus"></i>
                                <span>Thêm NV</span>
                            </a>
                            <a href="#" class="dropdown-item">
                                <i class="bi bi-file-earmark-pdf"></i>
                                <span>Báo cáo</span>
                            </a>
                            <a href="#" class="dropdown-item">
                                <i class="bi bi-calendar-event"></i>
                                <span>Lịch</span>
                            </a>
                            <a href="#" class="dropdown-item">
                                <i class="bi bi-envelope"></i>
                                <span>Email</span>
                            </a>
                            <a href="#" class="dropdown-item">
                                <i class="bi bi-chat-dots"></i>
                                <span>Chat</span>
                            </a>
                            <a href="#" class="dropdown-item">
                                <i class="bi bi-gear"></i>
                                <span>Cài đặt</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- User Dropdown -->
                <div class="dropdown-container">
                @auth
                    @php($authUser = auth()->user())
                    <button class="user-dropdown" data-dropdown="userDropdown">
                        <div class="avatar">{{ mb_strtoupper(mb_substr($authUser->ho_ten ?: $authUser->getAuthIdentifier(), 0, 2)) }}</div>
                        <div class="user-info">
                            <div class="name">{{ $authUser->ho_ten ?: $authUser->getAuthIdentifier() }}</div>
                            <div class="role">Quản trị viên</div>
                        </div>
                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                    </button>
                    <ul class="dropdown-menu-custom" id="userDropdown" style="list-style: none; padding: 8px; margin: 0;">
                        <li><a class="dropdown-item" href="#">
                            <i class="bi bi-person-circle"></i> Hồ sơ cá nhân
                        </a></li>
                        <li><a class="dropdown-item" href="#">
                            <i class="bi bi-gear"></i> Cài đặt tài khoản
                        </a></li>
                        <li><a class="dropdown-item" href="#">
                            <i class="bi bi-shield-lock"></i> Bảo mật
                        </a></li>
                        <li><div class="dropdown-divider"></div></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger border-0 bg-transparent w-100 text-start">
                                    <i class="bi bi-box-arrow-right"></i> Đăng xuất
                                </button>
                            </form>
                        </li>
                    </ul>
                @endauth
                @guest
                    <a class="user-dropdown" href="{{ route('login') }}">
                        <div class="avatar">?</div>
                        <div class="user-info">
                            <div class="name">Đăng nhập</div>
                        </div>
                    </a>
                @endguest
                </div>
    </div>
</div>
