<!DOCTYPE html>
<html lang="vi">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Quản Lý Nhân Sự')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="{{asset('backend/css/style.css')}}">
    @stack('styles') <!-- Thêm CSS tùy chỉnh -->
    <body>
        <!-- ===== SIDEBAR BACKDROP ===== -->
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
        @include('backend.layouts.sidebar')
        <!-- ===== MAIN CONTENT ===== -->
        <div class="main-content">
            @include('backend.layouts.topbar')
            @yield('content')
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="{{asset('backend/js/script.js')}}"></script>
        @stack('scripts') <!-- Thêm JS tùy chỉnh -->
    </body>
</html>