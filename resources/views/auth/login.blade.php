<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập | Quản lý nhân sự</title>
     <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            position: relative;
            padding: 20px;
        }

        /* Background decoration */
        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 50%, rgba(233, 69, 96, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 70% 50%, rgba(233, 69, 96, 0.05) 0%, transparent 50%);
            animation: bgFloat 20s ease-in-out infinite alternate;
            z-index: 0;
        }

        @keyframes bgFloat {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(2%, 2%) rotate(2deg); }
        }

        /* Floating particles */
        .particles {
            position: absolute;
            inset: 0;
            overflow: hidden;
            z-index: 0;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 6px;
            height: 6px;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 50%;
            animation: floatParticle 15s infinite linear;
        }

        .particle:nth-child(1) { left: 10%; top: 20%; animation-duration: 18s; width: 8px; height: 8px; }
        .particle:nth-child(2) { left: 20%; top: 70%; animation-duration: 22s; width: 5px; height: 5px; }
        .particle:nth-child(3) { left: 80%; top: 30%; animation-duration: 20s; width: 10px; height: 10px; }
        .particle:nth-child(4) { left: 90%; top: 80%; animation-duration: 25s; width: 7px; height: 7px; }
        .particle:nth-child(5) { left: 50%; top: 90%; animation-duration: 17s; width: 9px; height: 9px; }
        .particle:nth-child(6) { left: 60%; top: 10%; animation-duration: 23s; width: 6px; height: 6px; }
        .particle:nth-child(7) { left: 30%; top: 40%; animation-duration: 19s; width: 11px; height: 11px; }
        .particle:nth-child(8) { left: 70%; top: 60%; animation-duration: 21s; width: 4px; height: 4px; }

        @keyframes floatParticle {
            0% { transform: translateY(0) rotate(0deg); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) rotate(720deg); opacity: 0; }
        }

        /* ===== LOGIN CARD ===== */
        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 40px 35px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.35);
        }

        /* Logo & Brand */
        .login-brand {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-brand .logo-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #e94560, #c23152);
            border-radius: 18px;
            color: #fff;
            font-size: 36px;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px rgba(233, 69, 96, 0.3);
            transition: transform 0.3s ease;
        }

        .login-brand .logo-icon:hover {
            transform: scale(1.05) rotate(-3deg);
        }

        .login-brand h2 {
            font-weight: 700;
            color: #1a1a2e;
            font-size: 26px;
            margin-bottom: 4px;
        }

        .login-brand p {
            color: #6c757d;
            font-size: 14px;
            margin: 0;
        }

        /* Form */
        .login-form .form-group {
            margin-bottom: 20px;
        }

        .login-form .form-label {
            font-weight: 500;
            color: #495057;
            font-size: 14px;
            margin-bottom: 6px;
        }

        .login-form .form-label i {
            margin-right: 6px;
            color: #e94560;
        }

        .login-form .input-group {
            position: relative;
        }

        .login-form .input-group .form-control {
            border-radius: 12px;
            border: 1.5px solid #e0e0e0;
            padding: 12px 16px 12px 44px;
            font-size: 15px;
            transition: all 0.3s ease;
            background: #f8f9fa;
            height: 50px;
        }

        .login-form .input-group .form-control:focus {
            border-color: #e94560;
            box-shadow: 0 0 0 4px rgba(233, 69, 96, 0.1);
            background: #fff;
        }

        .login-form .input-group .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.1);
        }

        .login-form .input-group .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
            font-size: 20px;
            z-index: 10;
            transition: color 0.3s ease;
        }

        .login-form .input-group .form-control:focus + .input-icon,
        .login-form .input-group .form-control:focus ~ .input-icon {
            color: #e94560;
        }

        .login-form .input-group .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #adb5bd;
            font-size: 20px;
            cursor: pointer;
            padding: 4px;
            z-index: 10;
            transition: color 0.3s ease;
        }

        .login-form .input-group .toggle-password:hover {
            color: #e94560;
        }

        /* Options */
        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .login-options .form-check {
            margin: 0;
            padding-left: 0;
        }

        .login-options .form-check input[type="checkbox"] {
            display: none;
        }

        .login-options .form-check label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #495057;
            font-size: 14px;
            cursor: pointer;
            user-select: none;
        }

        .login-options .form-check label .checkmark {
            width: 20px;
            height: 20px;
            border: 2px solid #d0d0d0;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
            background: #fff;
        }

        .login-options .form-check label .checkmark i {
            font-size: 14px;
            color: #fff;
            opacity: 0;
            transform: scale(0.5);
            transition: all 0.2s ease;
        }

        .login-options .form-check input[type="checkbox"]:checked + label .checkmark {
            background: #e94560;
            border-color: #e94560;
        }

        .login-options .form-check input[type="checkbox"]:checked + label .checkmark i {
            opacity: 1;
            transform: scale(1);
        }

        .login-options .forgot-link {
            color: #e94560;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .login-options .forgot-link:hover {
            color: #c23152;
            text-decoration: underline;
        }

        /* Submit button */
        .login-form .btn-login {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #e94560, #c23152);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .login-form .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(233, 69, 96, 0.35);
        }

        .login-form .btn-login:active {
            transform: translateY(0);
        }

        .login-form .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .login-form .btn-login .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .login-form .btn-login.loading .spinner {
            display: inline-block;
        }

        .login-form .btn-login.loading .btn-text {
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Error message */
        .login-form .alert-error {
            display: none;
            border-radius: 12px;
            padding: 12px 16px;
            background: rgba(220, 53, 69, 0.08);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: #dc3545;
            font-size: 14px;
            margin-bottom: 20px;
            align-items: center;
            gap: 10px;
        }

        .login-form .alert-error.show {
            display: flex;
        }

        .login-form .alert-error i {
            font-size: 20px;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 24px;
            color: #6c757d;
            font-size: 13px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            .login-card {
                padding: 28px 20px;
                border-radius: 20px;
            }

            .login-brand .logo-icon {
                width: 60px;
                height: 60px;
                font-size: 30px;
            }

            .login-brand h2 {
                font-size: 22px;
            }

            .login-form .input-group .form-control {
                font-size: 14px;
                padding: 10px 14px 10px 40px;
                height: 46px;
            }

            .login-form .btn-login {
                height: 46px;
                font-size: 15px;
            }

            .login-options {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 380px) {
            .login-card {
                padding: 20px 16px;
            }
        }

        /* ===== DEMO NOTIFICATION ===== */
        .demo-credentials {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            color: #fff;
            padding: 12px 24px;
            border-radius: 16px;
            font-size: 13px;
            z-index: 100;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 90%;
        }

        .demo-credentials strong {
            color: #e94560;
        }

        .demo-credentials .demo-user {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255, 255, 255, 0.1);
            padding: 2px 12px;
            border-radius: 12px;
            margin: 0 4px;
        }

        @media (max-width: 600px) {
            .demo-credentials {
                font-size: 11px;
                padding: 10px 16px;
                bottom: 10px;
            }
            .demo-credentials .demo-user {
                padding: 1px 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Floating Particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    <!-- ===== LOGIN WRAPPER ===== -->
    <div class="login-wrapper">
        <div class="login-card">

            <!-- Brand -->
            <div class="login-brand">
                <div class="logo-icon">
                    <i class="bi bi-hexagon-fill"></i>
                </div>
                <h2>QUẢN LÝ NHÂN SỰ</h2>
            </div>

            <!-- Form -->
            <form class="login-form" id="loginForm" method="POST" action="{{ route('login.store') }}" data-login-form>
                @csrf

                <!-- Error Alert -->
                <div class="alert-error @if($errors->any()) show @endif" id="loginError" @if($errors->any()) role="alert" @endif>
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span id="errorMessage">{{ $errors->first() ?: 'Thông tin đăng nhập không hợp lệ.' }}</span>
                </div>

                <!-- Email / Username -->
                <div class="form-group">
                    <label class="form-label" for="loginMaNV">
                        <i class="bi bi-people"></i> Mã nhân viên
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control @error('dinh_danh') is-invalid @enderror" id="loginMaNV" name="dinh_danh" placeholder="Nhập mã nhân viên hoặc email" value="{{ old('dinh_danh') }}" autocomplete="username" required autofocus>
                        <i class="bi bi-people input-icon"></i>
                    </div>
                    <div class="invalid-feedback" id="emailFeedback">Vui lòng nhập mã nhân viên hợp lệ.</div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="loginPassword">
                        <i class="bi bi-lock"></i> Mật khẩu
                    </label>
                    <div class="input-group">
                        <input type="password" class="form-control @error('mat_khau') is-invalid @enderror" id="loginPassword" name="mat_khau" placeholder="Nhập mật khẩu" autocomplete="current-password" required>
                        <i class="bi bi-lock input-icon"></i>
                        <button type="button" class="toggle-password" id="togglePassword" tabindex="-1" aria-label="Hiển thị mật khẩu">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="passwordFeedback">Vui lòng nhập mật khẩu.</div>
                </div>

                <!-- Options -->
                <div class="login-options">
                    <div class="form-check">
                        <input type="checkbox" id="rememberMe" checked />
                        <label for="rememberMe">
                            <span class="checkmark"><i class="bi bi-check-lg"></i></span>
                            Ghi nhớ đăng nhập
                        </label>
                    </div>
                    <a href="#" class="forgot-link">Quên mật khẩu?</a>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-login" id="loginBtn" data-login-submit>
                    <span class="spinner"></span>
                    <span class="btn-text"><i class="bi bi-box-arrow-in-right"></i> Đăng nhập</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script 
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js">
    </script>
    <script>
        (function() {
            'use strict';

            // ===== HIỂN THỊ / ẨN MẬT KHẨU =====
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('loginPassword');
            const toggleIcon = document.getElementById('toggleIcon');

            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // Đổi icon
                    if (type === 'text') {
                        toggleIcon.classList.remove('bi-eye');
                        toggleIcon.classList.add('bi-eye-slash');
                        togglePassword.setAttribute('aria-label', 'Ẩn mật khẩu');
                    } else {
                        toggleIcon.classList.remove('bi-eye-slash');
                        toggleIcon.classList.add('bi-eye');
                        togglePassword.setAttribute('aria-label', 'Hiển thị mật khẩu');
                    }
                });
            }

            // ===== LOADING KHI SUBMIT =====
            const loginForm = document.querySelector('[data-login-form]');
            const loginBtn = document.querySelector('[data-login-submit]');
            
            if (loginForm && loginBtn) {
                loginForm.addEventListener('submit', function() {
                    loginBtn.disabled = true;
                    loginBtn.classList.add('loading');
                    loginBtn.setAttribute('aria-busy', 'true');
                });
            }

        })();
    </script>
</body>
</html>