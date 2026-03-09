<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Film ve dizi banner'larını yüksek çözünürlükte arayın ve indirin">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">

    <title>@yield('title', 'BannerArchive - Film & Dizi Banner Arayıcı')</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Styles & Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-neutral-950 text-white antialiased">
    <!-- Main Content -->
    @yield('content')

    <!-- Scripts -->
    <script>
        // Laravel CSRF Token ve API URL'i global olarak tanımla
        window.Laravel = {
            csrfToken: '{{ csrf_token() }}',
            apiUrl: '{{ url('/api') }}'
        };
    </script>
    @stack('scripts')
</body>
</html>