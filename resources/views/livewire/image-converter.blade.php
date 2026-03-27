<?php

use App\Services\ImageConverterService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.tool')] #[Title('Resim Dönüştürücü')] class extends Component
{
    use WithFileUploads;

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $photos = [];

    public string $targetFormat = 'webp';

    public int $quality = 85;

    /** @var array<int, array<string, mixed>> */
    public array $convertedFiles = [];

    public string $message = '';

    public string $messageType = '';

    public function updatedPhotos(): void
    {
        $this->message = '';
        $this->messageType = '';

        $this->validate([
            'photos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'photos.*.max' => 'Her dosya en fazla 10MB olabilir.',
            'photos.*.mimes' => 'Sadece JPG, PNG ve WebP formatları desteklenir.',
        ]);

        if (count($this->convertedFiles) + count($this->photos) > 20) {
            $this->message = 'En fazla 20 dosya yükleyebilirsiniz.';
            $this->messageType = 'error';
            $this->photos = [];

            return;
        }

        $converter = app(ImageConverterService::class);

        foreach ($this->photos as $photo) {
            try {
                $tempPath = $photo->getRealPath();
                $info = $converter->getImageInfo($tempPath);

                $previewUrl = null;
                try {
                    $previewUrl = $photo->temporaryUrl();
                } catch (\Throwable) {
                }

                $this->convertedFiles[] = [
                    'id' => uniqid('f_'),
                    'originalName' => $photo->getClientOriginalName(),
                    'originalSize' => $info['size'],
                    'originalWidth' => $info['width'],
                    'originalHeight' => $info['height'],
                    'originalFormat' => $info['format'],
                    'previewUrl' => $previewUrl,
                    'convertedSize' => null,
                    'convertedWidth' => null,
                    'convertedHeight' => null,
                    'convertedFormat' => null,
                    'tempUploadPath' => $tempPath,
                    'tempPath' => null,
                    'status' => 'pending',
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                $this->message = $photo->getClientOriginalName() . ': ' . $e->getMessage();
                $this->messageType = 'error';
            }
        }

        $this->photos = [];
    }

    public function convertWithOptions(string $format, int $quality): void
    {
        $this->targetFormat = $format;
        $this->quality = max(1, min(100, $quality));
        $this->convert();
    }

    public function convert(): void
    {
        if (empty($this->convertedFiles)) {
            $this->message = 'Lütfen önce dosya yükleyin.';
            $this->messageType = 'error';

            return;
        }

        $converter = app(ImageConverterService::class);
        $convertedCount = 0;

        foreach ($this->convertedFiles as $index => &$file) {
            if ($file['status'] === 'done') {
                continue;
            }

            if ($file['tempPath'] && file_exists($file['tempPath'])) {
                $converter->cleanup($file['tempPath']);
            }

            $file['status'] = 'converting';

            try {
                $sourcePath = $file['tempUploadPath'];

                if (! file_exists($sourcePath)) {
                    $file['status'] = 'error';
                    $file['error'] = 'Dosya bulunamadı, lütfen tekrar yükleyin.';

                    continue;
                }

                $outputPath = $converter->convert(
                    $sourcePath,
                    $file['originalFormat'],
                    $this->targetFormat,
                    $this->quality
                );

                $outputInfo = $converter->getImageInfo($outputPath);

                $file['convertedSize'] = $outputInfo['size'];
                $file['convertedWidth'] = $outputInfo['width'];
                $file['convertedHeight'] = $outputInfo['height'];
                $file['convertedFormat'] = $this->targetFormat;
                $file['tempPath'] = $outputPath;
                $file['status'] = 'done';
                $convertedCount++;
            } catch (\Throwable $e) {
                $file['status'] = 'error';
                $file['error'] = $e->getMessage();
            }
        }
        unset($file);

        if ($convertedCount > 0) {
            $this->message = $convertedCount . ' dosya başarıyla dönüştürüldü.';
            $this->messageType = 'success';
        }
    }

    public function downloadSingle(string $id): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $file = collect($this->convertedFiles)->firstWhere('id', $id);

        if (! $file || ! $file['tempPath'] || ! file_exists($file['tempPath'])) {
            $this->message = 'Dosya bulunamadı, lütfen tekrar dönüştürün.';
            $this->messageType = 'error';

            return null;
        }

        $originalName = pathinfo($file['originalName'], PATHINFO_FILENAME);
        $downloadName = $originalName . '.' . $file['convertedFormat'];

        return response()->streamDownload(function () use ($file) {
            echo file_get_contents($file['tempPath']);
        }, $downloadName);
    }

    public function downloadAllAsZip(): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $doneFiles = array_filter($this->convertedFiles, fn ($f) => $f['status'] === 'done');

        if (count($doneFiles) < 1) {
            $this->message = 'İndirilecek dönüştürülmüş dosya yok.';
            $this->messageType = 'error';

            return null;
        }

        $converter = app(ImageConverterService::class);

        try {
            $zipPath = $converter->createZipFromFiles($doneFiles);

            return response()->streamDownload(function () use ($zipPath, $converter) {
                echo file_get_contents($zipPath);
                $converter->cleanup($zipPath);
            }, 'donusturulen-resimler.zip');
        } catch (\Throwable $e) {
            $this->message = 'ZIP oluşturulamadı: ' . $e->getMessage();
            $this->messageType = 'error';

            return null;
        }
    }

    public function removeFile(string $id): void
    {
        $converter = app(ImageConverterService::class);

        foreach ($this->convertedFiles as $index => $file) {
            if ($file['id'] === $id) {
                if ($file['tempPath'] && file_exists($file['tempPath'])) {
                    $converter->cleanup($file['tempPath']);
                }
                array_splice($this->convertedFiles, $index, 1);
                break;
            }
        }
    }

    public function clearAll(): void
    {
        $converter = app(ImageConverterService::class);

        foreach ($this->convertedFiles as $file) {
            if ($file['tempPath'] && file_exists($file['tempPath'])) {
                $converter->cleanup($file['tempPath']);
            }
        }

        $this->convertedFiles = [];
        $this->message = '';
        $this->messageType = '';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        return number_format($bytes / 1024, 1) . ' KB';
    }

    private function calculateSavings(int $original, int $converted): string
    {
        if ($original <= 0) {
            return '';
        }

        $diff = $original - $converted;
        $percent = round(abs($diff) / $original * 100);

        if ($diff > 0) {
            return '↓' . $percent . '%';
        }

        if ($diff < 0) {
            return '↑' . $percent . '%';
        }

        return '0%';
    }
};
?>

<div
    class="relative max-w-5xl mx-auto px-4 sm:px-6 py-8 md:py-12"
    x-data="{
        selectedId: null,
        fmt: '{{ $targetFormat }}',
        qty: {{ $quality }}
    }"
    x-effect="
        const files = $wire.convertedFiles;
        if (!files || files.length === 0) { selectedId = null; return; }
        const ids = files.map(f => f.id);
        if (selectedId && !ids.includes(selectedId)) selectedId = ids[0];
        if (!selectedId) selectedId = ids[0];
    "
>
    <style>
        .quality-slider {
            -webkit-appearance: none;
            appearance: none;
            height: 4px;
            border-radius: 9999px;
            background: linear-gradient(to right, #d946ef var(--value-percent, 85%), #262626 var(--value-percent, 85%));
            outline: none;
            transition: background 0.1s;
        }
        .quality-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #d946ef;
            cursor: pointer;
            box-shadow: 0 0 10px rgba(217, 70, 239, 0.5);
            border: 2px solid #fff;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .quality-slider::-webkit-slider-thumb:hover {
            transform: scale(1.2);
            box-shadow: 0 0 16px rgba(217, 70, 239, 0.7);
        }
        .quality-slider::-moz-range-thumb {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #d946ef;
            cursor: pointer;
            box-shadow: 0 0 10px rgba(217, 70, 239, 0.5);
            border: 2px solid #fff;
        }
        .quality-slider::-moz-range-track {
            height: 4px;
            border-radius: 9999px;
            background: #262626;
        }
        .quality-slider::-moz-range-progress {
            height: 4px;
            border-radius: 9999px;
            background: #d946ef;
        }
        @keyframes bgFloat1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(60px, -80px) scale(1.1); }
            66% { transform: translate(-40px, 50px) scale(0.95); }
        }
        @keyframes bgFloat2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(-70px, 60px) scale(1.15); }
            66% { transform: translate(50px, -70px) scale(0.9); }
        }
        @keyframes bgFloat3 {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; }
            50% { transform: translate(-50%, -50%) scale(1.3); opacity: 1; }
        }
    </style>

    {{-- Animated Background --}}
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-fuchsia-600/8 blur-[130px]" style="animation: bgFloat1 25s ease-in-out infinite"></div>
        <div class="absolute -bottom-32 -right-32 w-[450px] h-[450px] rounded-full bg-purple-700/8 blur-[120px]" style="animation: bgFloat2 30s ease-in-out infinite"></div>
        <div class="absolute top-1/2 left-1/2 w-[350px] h-[350px] rounded-full bg-fuchsia-500/5 blur-[100px]" style="animation: bgFloat3 20s ease-in-out infinite"></div>
    </div>

    {{-- Hidden file input (always in DOM) --}}
    <input
        type="file"
        id="ic-file-input"
        wire:model="photos"
        multiple
        accept="image/png,image/jpeg,image/webp"
        class="hidden"
    >

    {{-- Hero --}}
    <div class="text-center mb-10">
        <h1 class="text-4xl md:text-5xl font-black tracking-tight mb-3 bg-clip-text text-transparent bg-linear-to-r from-white via-fuchsia-200 to-fuchsia-500">
            Resim Dönüştürücü
        </h1>
        <p class="text-neutral-400 max-w-lg mx-auto">
            PNG, WebP ve JPG formatları arasında hızlı ve kolay dönüşüm yapın.
            Tekli veya çoklu dosya desteği ile boyut bilgilerini anında görün.
        </p>
    </div>

    {{-- Flash Mesajları --}}
    @if($message !== '')
        <div class="mb-6 p-4 rounded-xl flex items-center gap-3
            {{ $messageType === 'success' ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400' : 'bg-red-500/10 border border-red-500/20 text-red-400' }}">
            @if($messageType === 'success')
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            @else
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            @endif
            <span class="text-sm">{{ $message }}</span>
        </div>
    @endif

    {{-- Validasyon Hataları --}}
    @if($errors->any())
        <div class="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-400">
            <ul class="list-disc list-inside text-sm space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Yükleme spinner (dosya varken) --}}
    @if(count($convertedFiles) > 0)
        <div wire:loading wire:target="photos" class="mb-6 flex items-center gap-3 p-4 bg-fuchsia-500/5 border border-fuchsia-500/20 rounded-xl">
            <div class="w-5 h-5 border-2 border-neutral-700 border-t-fuchsia-500 rounded-full animate-spin shrink-0"></div>
            <span class="text-sm text-fuchsia-300">Dosyalar yükleniyor...</span>
        </div>
    @endif

    @if(count($convertedFiles) === 0)
        {{-- Upload Zone (sadece dosya yokken) --}}
        <div
            x-data="{ isDragging: false, dragCounter: 0 }"
            x-on:dragenter.prevent="dragCounter++; isDragging = true"
            x-on:dragleave.prevent="dragCounter--; if (dragCounter === 0) isDragging = false"
            x-on:dragover.prevent
            x-on:drop.prevent="isDragging = false; dragCounter = 0; document.getElementById('ic-file-input').files = $event.dataTransfer.files; document.getElementById('ic-file-input').dispatchEvent(new Event('change', { bubbles: true }))"
            class="relative border-2 border-dashed rounded-2xl p-8 md:p-12 text-center transition-all duration-300 mb-8"
            :class="isDragging
                ? 'border-fuchsia-500 bg-fuchsia-500/5 scale-[1.01]'
                : 'border-neutral-700 hover:border-neutral-600 bg-neutral-900/50'"
        >
            <div wire:loading.remove wire:target="photos">
                <div class="mb-4">
                    <svg class="w-12 h-12 mx-auto text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <p class="text-neutral-300 font-medium mb-1">Dosyalarınızı sürükleyin</p>
                <p class="text-neutral-500 text-sm mb-4">veya bilgisayarınızdan seçmek için tıklayın</p>
                <button
                    onclick="document.getElementById('ic-file-input').click()"
                    type="button"
                    class="bg-fuchsia-600 hover:bg-fuchsia-500 text-white rounded-lg px-6 py-2.5 font-semibold text-sm transition-colors"
                >
                    Dosya Seç
                </button>
                <p class="text-neutral-600 text-xs mt-4">PNG, JPG, WebP &bull; Maks 10MB &bull; 20 dosya</p>
            </div>

            <div wire:loading wire:target="photos" class="py-4">
                <div class="w-10 h-10 mx-auto border-2 border-neutral-700 border-t-fuchsia-500 rounded-full animate-spin mb-3"></div>
                <p class="text-neutral-400 text-sm">Yükleniyor...</p>
            </div>
        </div>
    @endif

    @if(count($convertedFiles) > 0)
        {{-- Resim Önizleme Galerisi --}}
        <div class="bg-neutral-900 rounded-2xl border border-white/5 overflow-hidden mb-6">
            {{-- Ana Önizleme --}}
            <div class="relative bg-[#0c0c0c] flex items-center justify-center" style="min-height: 240px;">
                @foreach($convertedFiles as $file)
                    <div x-show="selectedId === '{{ $file['id'] }}'" x-cloak class="w-full flex items-center justify-center p-4 md:p-6">
                        @if($file['previewUrl'])
                            <img
                                src="{{ $file['previewUrl'] }}"
                                alt="{{ $file['originalName'] }}"
                                class="max-h-72 md:max-h-96 max-w-full object-contain rounded-lg shadow-2xl shadow-black/50"
                            >
                        @else
                            <div class="flex flex-col items-center gap-3 py-8 text-neutral-600">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-sm">Önizleme yüklenemedi</span>
                            </div>
                        @endif
                    </div>
                @endforeach

                {{-- Dosya bilgi overlay --}}
                @foreach($convertedFiles as $file)
                    <div x-show="selectedId === '{{ $file['id'] }}'" x-cloak
                         class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 via-black/40 to-transparent px-5 pb-4 pt-12 pointer-events-none">
                        <p class="text-sm font-medium truncate text-white/90">{{ $file['originalName'] }}</p>
                        <div class="flex items-center gap-3 mt-1 text-xs text-neutral-300/80">
                            <span>{{ $file['originalWidth'] }} &times; {{ $file['originalHeight'] }}</span>
                            <span class="text-neutral-600">&bull;</span>
                            <span>{{ $this->formatBytes($file['originalSize']) }}</span>
                            <span class="px-1.5 py-0.5 rounded bg-white/10 font-mono uppercase tracking-wider text-[10px]">{{ $file['originalFormat'] }}</span>
                            @if($file['status'] === 'done')
                                <span class="px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-400 font-mono uppercase tracking-wider text-[10px]">Dönüştürüldü</span>
                            @elseif($file['status'] === 'error')
                                <span class="px-1.5 py-0.5 rounded bg-red-500/20 text-red-400 font-mono uppercase tracking-wider text-[10px]">Hata</span>
                            @endif
                        </div>
                    </div>
                @endforeach

                {{-- Dosya sayacı --}}
                <div class="absolute top-3 right-3 px-2.5 py-1 rounded-lg bg-black/60 backdrop-blur-sm text-xs text-neutral-300 font-mono pointer-events-none">
                    {{ count($convertedFiles) }} dosya
                </div>
            </div>

            {{-- Thumbnail Strip --}}
            @if(count($convertedFiles) > 1)
                <div class="p-3 border-t border-white/5 bg-neutral-900/80">
                    <div class="flex gap-2 overflow-x-auto scrollbar-hide pb-1">
                        @foreach($convertedFiles as $file)
                            <button
                                @click="selectedId = '{{ $file['id'] }}'"
                                :class="selectedId === '{{ $file['id'] }}'
                                    ? 'ring-2 ring-fuchsia-500 ring-offset-1 ring-offset-neutral-900 opacity-100 scale-105'
                                    : 'ring-1 ring-white/10 hover:ring-white/30 opacity-50 hover:opacity-90'"
                                class="shrink-0 rounded-lg overflow-hidden transition-all duration-200 relative group"
                                type="button"
                            >
                                @if($file['previewUrl'])
                                    <img src="{{ $file['previewUrl'] }}" class="w-20 h-14 object-cover" alt="{{ $file['originalName'] }}">
                                @else
                                    <div class="w-20 h-14 bg-neutral-800 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                @endif
                                @if($file['status'] === 'done')
                                    <div class="absolute bottom-0.5 right-0.5 w-3 h-3 rounded-full bg-emerald-500 border border-neutral-900"></div>
                                @elseif($file['status'] === 'error')
                                    <div class="absolute bottom-0.5 right-0.5 w-3 h-3 rounded-full bg-red-500 border border-neutral-900"></div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Kontrol Paneli --}}
        <div class="p-4 bg-neutral-900/80 backdrop-blur-sm rounded-2xl border border-white/5 mb-6">
            {{-- Format + Kalite tek satır --}}
            <div class="flex flex-col sm:flex-row sm:items-center gap-4 mb-4">
                {{-- Format Seçimi --}}
                <div class="flex items-center gap-2">
                    <span class="text-[10px] uppercase tracking-widest text-neutral-500 font-bold">Format</span>
                    <div class="flex gap-1">
                        @foreach(['png', 'webp', 'jpg'] as $fmt)
                            <button
                                @click="fmt = '{{ $fmt }}'"
                                :class="fmt === '{{ $fmt }}'
                                    ? 'bg-fuchsia-600 text-white shadow-lg shadow-fuchsia-600/25'
                                    : 'bg-neutral-800 text-neutral-400 hover:bg-neutral-700 hover:text-neutral-300 border border-white/5'"
                                class="px-3 py-1.5 rounded-lg text-xs font-mono font-bold uppercase transition-all duration-200"
                                type="button"
                            >
                                {{ strtoupper($fmt) }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Kalite Slider (kompakt) --}}
                <div x-show="fmt !== 'png'" x-transition.opacity.duration.200ms class="flex items-center gap-3 flex-1 min-w-0">
                    <span class="text-[10px] uppercase tracking-widest text-neutral-500 font-bold shrink-0">Kalite</span>
                    <input
                        type="range"
                        x-model="qty"
                        min="1"
                        max="100"
                        step="1"
                        class="quality-slider flex-1 min-w-0"
                        :style="`--value-percent: ${qty}%`"
                    >
                    <span class="text-sm font-mono font-bold text-fuchsia-400 w-7 text-right shrink-0" x-text="qty"></span>
                </div>
            </div>

            {{-- Aksiyon Satırı --}}
            <div class="flex items-center gap-3 pt-3 border-t border-white/5">
                <div class="flex items-center gap-2 text-xs text-neutral-500">
                    <span class="font-bold text-neutral-300">{{ count($convertedFiles) }}</span> dosya
                    @php $doneCount = count(array_filter($convertedFiles, fn($f) => $f['status'] === 'done')); @endphp
                    @if($doneCount > 0)
                        &bull; <span class="font-bold text-emerald-400">{{ $doneCount }}</span> dönüştürüldü
                    @endif
                </div>
                <div class="ml-auto flex items-center gap-2">
                    {{-- Dosya Ekle butonu --}}
                    <button
                        onclick="document.getElementById('ic-file-input').click()"
                        wire:loading.attr="disabled"
                        wire:target="photos"
                        type="button"
                        class="px-3 py-2 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 text-sm rounded-lg transition-all border border-white/5 flex items-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span wire:loading.remove wire:target="photos">Dosya Ekle</span>
                        <span wire:loading wire:target="photos">Yükleniyor...</span>
                    </button>
                    <button
                        @click="selectedId = null; $wire.clearAll()"
                        type="button"
                        class="px-3 py-2 bg-neutral-800 hover:bg-red-600/20 text-neutral-400 hover:text-red-400 text-sm rounded-lg transition-all border border-white/5"
                    >
                        Temizle
                    </button>
                    <button
                        @click="$wire.convertWithOptions(fmt, parseInt(qty))"
                        wire:loading.attr="disabled"
                        wire:target="convertWithOptions, convert"
                        type="button"
                        class="bg-fuchsia-600 hover:bg-fuchsia-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl px-5 py-2 font-bold text-sm flex items-center gap-2 transition-all shadow-lg shadow-fuchsia-600/20"
                    >
                        <span wire:loading.remove wire:target="convertWithOptions, convert">
                            <svg class="w-4 h-4 inline -mt-0.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Dönüştür
                        </span>
                        <span wire:loading wire:target="convertWithOptions, convert" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Dönüştürülüyor...
                        </span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Dosya Listesi --}}
        <div class="space-y-2 mb-6">
            @foreach($convertedFiles as $file)
                @php
                    $statusColor = match($file['status']) {
                        'done' => ($file['convertedSize'] < $file['originalSize'] ? 'bg-emerald-500' : 'bg-amber-500'),
                        'converting' => 'bg-fuchsia-500 animate-pulse',
                        'error' => 'bg-red-500',
                        default => 'bg-neutral-700',
                    };
                @endphp
                <div wire:key="file-{{ $file['id'] }}" class="flex rounded-xl overflow-hidden border border-white/5 hover:border-white/10 bg-neutral-900/80 backdrop-blur-sm transition-all group">
                    {{-- Status accent bar --}}
                    <div class="w-1 shrink-0 {{ $statusColor }}"></div>

                    {{-- Content --}}
                    <div class="flex items-center gap-3 p-3 flex-1 min-w-0">
                        {{-- Thumbnail --}}
                        <button
                            @click="selectedId = '{{ $file['id'] }}'"
                            type="button"
                            class="shrink-0 w-11 h-11 rounded-lg overflow-hidden transition-all"
                            :class="selectedId === '{{ $file['id'] }}' ? 'ring-2 ring-fuchsia-500 ring-offset-1 ring-offset-neutral-900' : 'ring-1 ring-white/10 hover:ring-fuchsia-500/50'"
                        >
                            @if($file['previewUrl'])
                                <img src="{{ $file['previewUrl'] }}" class="w-full h-full object-cover" alt="">
                            @else
                                <div class="w-full h-full bg-neutral-800 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16"/>
                                    </svg>
                                </div>
                            @endif
                        </button>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium truncate">{{ $file['originalName'] }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 mt-0.5 text-xs text-neutral-500">
                                <span class="font-mono uppercase text-[10px] px-1 py-px rounded bg-neutral-800">{{ $file['originalFormat'] }}</span>
                                <span class="tabular-nums">{{ $file['originalWidth'] }}&times;{{ $file['originalHeight'] }}</span>
                                <span class="tabular-nums">{{ $this->formatBytes($file['originalSize']) }}</span>

                                @if($file['status'] === 'done')
                                    <svg class="w-3 h-3 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                    </svg>
                                    <span class="font-mono uppercase text-[10px] px-1 py-px rounded bg-fuchsia-600/20 text-fuchsia-400">{{ $file['convertedFormat'] }}</span>
                                    <span class="tabular-nums text-fuchsia-400">{{ $this->formatBytes($file['convertedSize']) }}</span>
                                    @php $savings = $this->calculateSavings($file['originalSize'], $file['convertedSize']); @endphp
                                    @if($savings !== '' && $savings !== '0%')
                                        @php $isSmaller = $file['convertedSize'] < $file['originalSize']; @endphp
                                        <span class="text-[10px] font-bold px-1.5 py-px rounded {{ $isSmaller ? 'bg-emerald-500/15 text-emerald-400' : 'bg-amber-500/15 text-amber-400' }}">
                                            {{ $savings }}
                                        </span>
                                    @endif
                                @elseif($file['status'] === 'converting')
                                    <svg class="w-3 h-3 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                    </svg>
                                    <div class="w-3 h-3 border-[1.5px] border-neutral-700 border-t-fuchsia-500 rounded-full animate-spin"></div>
                                    <span class="text-neutral-500">Dönüştürülüyor</span>
                                @elseif($file['status'] === 'error')
                                    <svg class="w-3 h-3 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                    </svg>
                                    <span class="text-red-400">{{ $file['error'] ?? 'Hata' }}</span>
                                @else
                                    <svg class="w-3 h-3 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                    </svg>
                                    <span class="text-neutral-600">Bekleniyor</span>
                                @endif
                            </div>

                            {{-- Progress bar (dönüştürülmüşler için) --}}
                            @if($file['status'] === 'done')
                                @php
                                    $ratio = $file['originalSize'] > 0 ? $file['convertedSize'] / $file['originalSize'] : 1;
                                    $isSmaller = $file['convertedSize'] < $file['originalSize'];
                                @endphp
                                <div class="mt-1.5 h-1 bg-neutral-800 rounded-full overflow-hidden max-w-xs">
                                    <div class="h-full rounded-full transition-all duration-700 ease-out {{ $isSmaller ? 'bg-emerald-500/60' : 'bg-amber-500/60' }}"
                                         style="width: {{ min($ratio * 100, 100) }}%"></div>
                                </div>
                            @endif
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center gap-1.5 shrink-0">
                            @if($file['status'] === 'done')
                                <button
                                    wire:click="downloadSingle('{{ $file['id'] }}')"
                                    type="button"
                                    class="bg-fuchsia-600 hover:bg-fuchsia-500 text-white rounded-lg p-2 transition-colors"
                                    title="İndir"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                </button>
                            @endif
                            <button
                                wire:click="removeFile('{{ $file['id'] }}')"
                                type="button"
                                class="p-2 text-neutral-600 hover:text-red-400 hover:bg-red-600/10 rounded-lg transition-colors opacity-0 group-hover:opacity-100"
                                title="Kaldır"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Toplu İndirme --}}
        @php $doneCount = count(array_filter($convertedFiles, fn($f) => $f['status'] === 'done')); @endphp
        @if($doneCount > 1)
            <div class="flex justify-center">
                <button
                    wire:click="downloadAllAsZip"
                    wire:loading.attr="disabled"
                    wire:target="downloadAllAsZip"
                    type="button"
                    class="bg-white hover:bg-fuchsia-500 text-neutral-900 hover:text-white font-bold rounded-xl px-8 py-3 flex items-center gap-2.5 transition-all shadow-lg hover:shadow-fuchsia-600/20"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span wire:loading.remove wire:target="downloadAllAsZip">
                        Tümünü ZIP Olarak İndir ({{ $doneCount }} dosya)
                    </span>
                    <span wire:loading wire:target="downloadAllAsZip">ZIP Hazırlanıyor...</span>
                </button>
            </div>
        @endif
    @endif

    {{-- Boş Durum --}}
    @if(count($convertedFiles) === 0)
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
            <div class="bg-neutral-900/50 rounded-xl border border-white/5 p-6 text-center">
                <div class="w-10 h-10 mx-auto mb-3 rounded-lg bg-fuchsia-600/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-fuchsia-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h3 class="text-sm font-semibold mb-1">Format Dönüştürme</h3>
                <p class="text-xs text-neutral-500">PNG, WebP, JPG arası geçiş</p>
            </div>
            <div class="bg-neutral-900/50 rounded-xl border border-white/5 p-6 text-center">
                <div class="w-10 h-10 mx-auto mb-3 rounded-lg bg-fuchsia-600/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-fuchsia-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h3 class="text-sm font-semibold mb-1">Boyut Karşılaştırma</h3>
                <p class="text-xs text-neutral-500">Orijinal ve dönüştürülmüş boyutları görün</p>
            </div>
            <div class="bg-neutral-900/50 rounded-xl border border-white/5 p-6 text-center">
                <div class="w-10 h-10 mx-auto mb-3 rounded-lg bg-fuchsia-600/10 flex items-center justify-center">
                    <svg class="w-5 h-5 text-fuchsia-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                    </svg>
                </div>
                <h3 class="text-sm font-semibold mb-1">Toplu İndirme</h3>
                <p class="text-xs text-neutral-500">Birden fazla dosyayı ZIP olarak indirin</p>
            </div>
        </div>
    @endif
</div>
