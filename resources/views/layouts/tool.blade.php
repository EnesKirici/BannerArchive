<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Resim formatlarını kolayca dönüştürün - PNG, WebP, JPG">
    <link rel="icon" type="image/jpeg" href="{{ asset('images/elw.jpg') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/elw.jpg') }}">

    <title>{{ $title ?? 'Araçlar' }} - elw BannerArchive</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-ED90TC6XVC"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-ED90TC6XVC');
    </script>

    <!-- Styles & Scripts -->
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="bg-neutral-950 text-white antialiased min-h-screen flex flex-col">
    <!-- Navbar -->
    <nav class="border-b border-white/5 bg-neutral-900/80 backdrop-blur-md sticky top-0 z-40">
        <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                <img src="{{ asset('images/elw.jpg') }}" alt="elw" class="w-8 h-8 rounded-lg elw-logo-hover">
                <span class="text-lg font-bold bg-clip-text text-transparent bg-linear-to-r from-white to-neutral-400 tracking-tight">BannerArchive</span>
            </a>
            <div class="flex items-center gap-2 text-sm text-neutral-500">
                <a href="{{ route('home') }}" class="hover:text-white transition-colors">Ana Sayfa</a>
                <span>/</span>
                <span class="text-neutral-300">{{ $title ?? 'Araçlar' }}</span>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1">
        {{ $slot }}
    </main>

    <!-- Footer -->
    @include('partials.footer')

    @livewireScripts
</body>
</html>
