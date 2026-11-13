<!DOCTYPE html>
<html lang="id" data-theme="light" x-data="appRoot()" x-bind:data-theme="theme">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>@yield('title', 'Ratio Coffee ERP')</title>
    <meta name="description" content="@yield('meta_desc', 'Sistem ERP Coffee Shop modern untuk mengelola inventory, POS, keuangan, dan operasional bisnis kopi.')" />

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" />

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

    <!-- Ratio Coffee ERP Custom CSS (with Cache-Busting Versioning) -->
    <link href="{{ asset('css/erp.css') }}?v={{ @filemtime(public_path('css/erp.css')) ?: time() }}" rel="stylesheet" />
    <style>
        /* Dark Mode Datepicker: Border Langsung Terang & Soft Saat Hover */
        [data-theme="dark"] input[type="date"],
        [data-theme="dark"] input[type="datetime-local"],
        [data-theme="dark"] input[type="month"],
        [data-theme="dark"] input[type="time"],
        [data-theme="dark"] .date-picker-input,
        [data-theme="dark"] .form-input[type="date"],
        html[data-theme="dark"] input[type="date"] {
            color-scheme: dark !important;
            color: #ffffff !important;
            background-color: rgba(15, 23, 42, 0.95) !important;
            border: 1.5px solid #f97316 !important; /* Border Langsung Terang */
            box-shadow: 0 0 10px rgba(249, 115, 22, 0.4) !important;
            transition: all 0.25s ease-in-out !important;
        }

        [data-theme="dark"] input[type="date"]:hover,
        [data-theme="dark"] input[type="datetime-local"]:hover,
        [data-theme="dark"] input[type="month"]:hover,
        [data-theme="dark"] input[type="time"]:hover,
        [data-theme="dark"] .date-picker-input:hover,
        [data-theme="dark"] .form-input[type="date"]:hover,
        html[data-theme="dark"] input[type="date"]:hover {
            border-color: rgba(249, 115, 22, 0.45) !important; /* Menjadi Soft saat Hover */
            box-shadow: 0 0 4px rgba(249, 115, 22, 0.15) !important;
        }

        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator,
        [data-theme="dark"] input[type="datetime-local"]::-webkit-calendar-picker-indicator,
        [data-theme="dark"] input[type="month"]::-webkit-calendar-picker-indicator,
        [data-theme="dark"] input[type="time"]::-webkit-calendar-picker-indicator,
        [data-theme="dark"] .date-picker-input::-webkit-calendar-picker-indicator,
        [data-theme="dark"] .form-input::-webkit-calendar-picker-indicator,
        html[data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator,
        html[data-theme="dark"] .date-picker-input::-webkit-calendar-picker-indicator {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' fill='%23ffffff' viewBox='0 0 16 16'%3E%3Cpath d='M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H1a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z'/%3E%3C/svg%3E") !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
            background-size: 16px 16px !important;
            background-color: transparent !important;
            border: none !important;
            filter: none !important;
            -webkit-filter: none !important;
            opacity: 1 !important;
            cursor: pointer !important;
            transition: all 0.25s ease-in-out !important;
        }

        [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator:hover,
        [data-theme="dark"] .date-picker-input::-webkit-calendar-picker-indicator:hover,
        html[data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator:hover {
            opacity: 0.7 !important;
        }
    </style>

    <!-- Favicon & PWA Manifest -->
    <link id="app-favicon" rel="icon" type="image/png" href="{{ asset('storage/brand/ratio_coffee_logo_putih02.png') }}" />
    <link rel="manifest" href="{{ asset('manifest.json') }}" />
    <meta name="theme-color" content="#ea580c" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <meta name="apple-mobile-web-app-title" content="Ratio ERP" />
    <!-- Thermal Printer & ESC/POS Manager (Head loaded for persistent auto-resume) -->
    <script src="{{ asset('js/thermal-printer.js') }}?v={{ file_exists(public_path('js/thermal-printer.js')) ? filemtime(public_path('js/thermal-printer.js')) : time() }}"></script>

    @stack('styles')
</head>
<body>
    @yield('content')

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

    <!-- Axios -->
    <script src="https://cdn.jsdelivr.net/npm/axios@1.7.2/dist/axios.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

    <!-- Global App Bootstrap -->
    <script src="{{ asset('js/erp-app.js') }}"></script>

    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then((reg) => console.log('[PWA] Service Worker registered:', reg.scope))
                    .catch((err) => console.warn('[PWA] Service Worker registration failed:', err));
            });
        }
    </script>

    @stack('scripts')
</body>
</html>
