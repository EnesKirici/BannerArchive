<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Kapak görseli yükle - BannerArchive</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-neutral-950 text-white antialiased min-h-screen flex items-center justify-center p-4">
    @php($labels = ['afis' => 'Afiş (dikey)', 'arka_plan' => 'Arka plan (yatay)'])

    <main class="w-full max-w-md bg-neutral-900 rounded-xl border border-white/5 p-6 space-y-5">
        <div>
            <h1 class="text-xl font-bold">Kapak görseli yükle</h1>
            <p class="text-sm text-neutral-400 mt-1">Yükledikten sonra Claude sohbetine dönüp “yükledim” demen yeterli.</p>
        </div>

        @if($saved !== [])
            <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
                Kaydedildi: {{ collect($saved)->map(fn ($kind) => $labels[$kind])->implode(', ') }}. Sohbete dönebilirsin.
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ request()->fullUrl() }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            @foreach($labels as $name => $label)
                <label class="block space-y-1.5">
                    <span class="text-sm font-semibold text-neutral-200">
                        {{ $label }}
                        @if(in_array($name, $uploads, true))
                            <span class="ml-1 text-xs font-normal text-emerald-400">· yüklü, yenisi yerine geçer</span>
                        @endif
                    </span>
                    <input type="file" name="{{ $name }}" accept="image/jpeg,image/png,image/webp"
                           class="block w-full text-sm text-neutral-300 file:mr-3 file:rounded-lg file:border-0 file:bg-white/10 file:px-4 file:py-2 file:text-white hover:file:bg-white/15">
                </label>
            @endforeach

            <p class="text-xs text-neutral-500">Shorts kapağı afişten, video kapağı arka plandan beslenir; ikisinden biri yeterli. JPG, PNG ya da WebP, en fazla 10 MB.</p>

            <button type="submit" class="w-full px-5 py-3 rounded-lg bg-fuchsia-600 hover:bg-fuchsia-500 font-semibold transition-colors">
                Yükle
            </button>
        </form>
    </main>
</body>
</html>
