<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Quản lý nhân sự')</title>
    <meta name="description" content="Hệ thống quản lý nhân sự hiện đại, tối giản và trang trọng cho doanh nghiệp.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="site-shell">
    @yield('content')
    @stack('scripts')
</body>
</html>
