<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Quản Lý Nhân Sự')</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="{{ asset('backend/css/style.css') }}">

    <style data-backend-shared-ui>
        :root {
            --backend-accent: #e94560;
            --backend-border: #e5e7eb;
        }

        .page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .page-header .left,
        .page-header__title-row {
            min-width: 0;
        }

        .page-header__title-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .625rem;
        }

        .page-header__title-row h1 {
            margin-bottom: 0;
        }

        .page-header__icon {
            color: var(--backend-accent);
            flex: 0 0 auto;
            font-size: 1.25em;
        }

        .page-header__actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .breadcrumb-item__link {
            color: var(--backend-accent);
            text-decoration: none;
        }

        .breadcrumb-item__link:hover,
        .breadcrumb-item__link:focus-visible {
            color: var(--backend-accent);
            text-decoration: underline;
        }

        .filter-card .card-header {
            border-bottom-color: var(--backend-border);
        }

        .filter-bar {
            display: grid;
            gap: 1rem;
        }

        .filter-bar__fields {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-bar__field {
            min-width: 0;
        }

        .filter-bar .filter-bar__field > .form-label {
            display: block;
            margin-bottom: .5rem;
        }

        .filter-bar .form-control,
        .filter-bar .form-select {
            height: 2.375rem !important;
            min-height: 2.375rem !important;
            padding-block: .375rem !important;
        }

        .filter-bar .input-group-text {
            display: inline-flex;
            align-items: center;
            height: 2.375rem !important;
            min-height: 2.375rem !important;
        }

        .filter-bar .filter-bar__field--period {
            grid-column: span 2;
            max-width: 32rem;
        }

        .filter-bar .filter-bar__field--period > .filter-period-controls {
            display: flex;
            align-items: stretch;
            width: min(100%, 18rem) !important;
            max-width: 18rem !important;
            height: 2.375rem !important;
            min-height: 2.375rem !important;
            gap: 0 !important;
            overflow: hidden;
            border: 1px solid var(--backend-border);
            border-radius: .5rem;
            background: #fff;
        }

        .filter-bar .filter-bar__field--period > .filter-period-controls > .form-select,
        .filter-bar .filter-bar__field--period > .filter-period-controls > .form-control {
            height: 2.375rem !important;
            min-height: 2.375rem !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }

        .filter-bar .filter-bar__field--period > .filter-period-controls > .form-select {
            flex: 1 1 auto;
            min-width: 0;
        }

        .filter-bar .filter-bar__field--period > .filter-period-controls > .form-control {
            flex: 0 0 5.25rem;
            border-left: 1px solid var(--backend-border) !important;
            text-align: center;
        }

        .filter-bar .filter-bar__field--period > .filter-period-controls:focus-within {
            border-color: var(--backend-accent);
            box-shadow: 0 0 0 .2rem rgba(233, 69, 96, .14);
        }

        .filter-bar__field--toggle {
            align-self: end;
        }

        .filter-bar__actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .table-actions,
        .salary-row-actions,
        .coefficient-row-actions,
        .leave-log-row-actions {
            display: inline-flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: .5rem;
            white-space: nowrap;
        }

        .table-actions > .btn,
        .table-actions > form > .btn,
        .salary-row-actions > .btn,
        .salary-row-actions > form > .btn,
        .coefficient-row-actions > .btn,
        .coefficient-row-actions > form > .btn,
        .leave-log-row-actions > .btn,
        .leave-log-row-actions > form > .btn {
            height: 2.375rem;
            min-height: 2.375rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon-action {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 2.375rem;
            min-width: 2.375rem;
            height: 2.375rem;
            min-height: 2.375rem;
            padding: 0 !important;
        }

        .table-state {
            padding: 2rem 1rem;
            text-align: center;
        }

        .date-field {
            display: grid;
            gap: .5rem;
        }

        .backend-pagination {
            overflow-x: auto;
        }

        @media (max-width: 767.98px) {
            .page-header {
                flex-direction: column;
            }

            .page-header__actions {
                width: 100%;
            }

            .filter-bar__fields {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filter-bar .filter-bar__field--period {
                grid-column: 1 / -1;
                max-width: none;
            }

            .filter-bar .filter-bar__field--period > .filter-period-controls {
                width: 100% !important;
                max-width: none !important;
            }

            .filter-bar__actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 575.98px) {
            .filter-bar__fields {
                grid-template-columns: 1fr;
            }

            .filter-bar .filter-bar__field--period {
                width: 100%;
            }

            .filter-bar__field--period .attendance-period-picker,
            .filter-bar__field--period .salary-period-picker {
                width: 100%;
            }
        }
    </style>

    @stack('styles')
</head>
<body>
    <!-- ===== SIDEBAR BACKDROP ===== -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- ===== SIDEBAR ===== -->
    @include('backend.layouts.sidebar')

    <!-- ===== MAIN CONTENT ===== -->
    <div class="main-content">
        <!-- ===== TOPBAR ===== -->
        @include('backend.layouts.topbar')

        <!-- ===== PAGE CONTENT ===== -->
        @yield('content')
    </div>

    <!-- ===== SCRIPTS ===== -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('backend/js/script.js') }}"></script>

    @stack('scripts')
</body>
</html>
