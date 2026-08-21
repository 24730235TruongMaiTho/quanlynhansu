<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập | Quản lý nhân sự</title>
</head>
<body>
    <main>
        <h1>Đăng nhập</h1>
        <form method="POST" action="{{ route('login.store') }}" data-login-form>
            @csrf

            <div>
                <label for="dinh_danh">Mã nhân viên hoặc email</label>
                <input id="dinh_danh" name="dinh_danh" type="text" value="{{ old('dinh_danh') }}"
                       autocomplete="username" required @error('dinh_danh') aria-invalid="true" autofocus @enderror>
                @error('dinh_danh')
                    <p role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="mat_khau">Mật khẩu</label>
                <input id="mat_khau" name="mat_khau" type="password" autocomplete="current-password" required
                       @error('mat_khau') aria-invalid="true" autofocus @enderror>
                @error('mat_khau')
                    <p role="alert">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" data-login-submit>Đăng nhập</button>
        </form>
    </main>
    <script>
        document.querySelector('[data-login-form]')?.addEventListener('submit', function () {
            const button = this.querySelector('[data-login-submit]');
            if (!button) return;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.textContent = 'Đang đăng nhập...';
        });
    </script>
</body>
</html>
