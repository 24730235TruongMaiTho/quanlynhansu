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
                @php($avatarUrl = filled($authUser->anh_dai_dien ?? null) ? \Illuminate\Support\Facades\Storage::disk('public')->url($authUser->anh_dai_dien) : null)
                @php($avatarInitials = mb_strtoupper(mb_substr(trim($authUser->ho_ten ?: $authUser->getAuthIdentifier()), 0, 2)))
                <button class="user-dropdown" data-dropdown="userDropdown">
                    @if ($avatarUrl)
                        <img class="avatar" src="{{ $avatarUrl }}" alt="" aria-hidden="true">
                    @else
                        <div class="avatar" aria-hidden="true">{{ $avatarInitials }}</div>
                    @endif
                    <div class="user-info">
                        <div class="name">{{ $authUser->ho_ten ?: $authUser->getAuthIdentifier() }}</div>
                        <div class="role">{{ $authUser->ten_vt ?? 'Tài khoản' }}</div>
                    </div>
                    <i class="bi bi-chevron-down dropdown-arrow"></i>
                </button>
                <ul class="dropdown-menu-custom" id="userDropdown" style="list-style: none; padding: 8px; margin: 0;">
                    <li><a class="dropdown-item" href="{{ route('backend.profile.edit') }}">
                        <i class="bi bi-person-circle" aria-hidden="true"></i> Hồ sơ cá nhân
                    </a></li>
                    <li><a class="dropdown-item" href="{{ route('backend.profile.password.edit') }}">
                        <i class="bi bi-key" aria-hidden="true"></i> Đổi mật khẩu
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
