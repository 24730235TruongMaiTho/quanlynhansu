<!-- ===== TOP BAR ===== -->
<div class="top-bar">
    <div class="left-section">
        <button class="hamburger-btn" id="hamburgerBtn" aria-label="Open menu">
            <i class="bi bi-list"></i>
        </button>
        
    </div>
    <div class="right-section">          
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
