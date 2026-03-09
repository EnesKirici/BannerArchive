<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <title>Giriş - BannerArchive Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
</head>
<body class="bg-neutral-950 text-white antialiased min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-linear-to-br from-fuchsia-600 to-purple-700 shadow-lg shadow-fuchsia-500/20 mb-4">
                <span class="text-2xl font-bold">B</span>
            </div>
            <h1 class="text-2xl font-bold">BannerArchive</h1>
            <p class="text-neutral-500 text-sm mt-1">Admin Panel</p>
        </div>
        
        <!-- Login Form -->
        <div class="bg-neutral-900 rounded-2xl border border-white/5 p-8">
            <form action="{{ route('login') }}" method="POST" class="space-y-6">
                @csrf
                
                <div>
                    <label for="email" class="block text-sm font-medium text-neutral-300 mb-2">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full px-4 py-3 bg-neutral-800 border border-neutral-700 rounded-lg focus:outline-none focus:border-fuchsia-500 focus:ring-1 focus:ring-fuchsia-500 transition-colors placeholder-neutral-500"
                           placeholder="admin@example.com">
                    @error('email')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-neutral-300 mb-2">Şifre</label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-4 py-3 bg-neutral-800 border border-neutral-700 rounded-lg focus:outline-none focus:border-fuchsia-500 focus:ring-1 focus:ring-fuchsia-500 transition-colors placeholder-neutral-500"
                           placeholder="••••••••">
                    @error('password')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" id="remember" name="remember" 
                           class="w-4 h-4 rounded border-neutral-700 bg-neutral-800 text-fuchsia-500 focus:ring-fuchsia-500 focus:ring-offset-0">
                    <label for="remember" class="ml-2 text-sm text-neutral-400">Beni hatırla</label>
                </div>
                
                <button type="submit" 
                        class="w-full py-3 px-4 bg-fuchsia-600 hover:bg-fuchsia-500 text-white font-semibold rounded-lg transition-colors">
                    Giriş Yap
                </button>
            </form>
        </div>
        
        <p class="text-center text-neutral-600 text-sm mt-6">
            <a href="{{ route('home') }}" class="hover:text-white transition-colors">← Siteye Dön</a>
        </p>
    </div>
</body>
</html>
