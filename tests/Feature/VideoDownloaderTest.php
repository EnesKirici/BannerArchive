<?php

declare(strict_types=1);

use App\Models\SecurityLog;
use App\Services\VideoDownloaderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;
use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

test('video downloader page renders', function () {
    get('/tools/video-downloader')
        ->assertSuccessful()
        ->assertSee('Video İndirici');
});

test('home page shows both tool buttons', function () {
    Http::fake(['*' => Http::response(['results' => []], 200)]);

    get('/')
        ->assertSuccessful()
        ->assertSee('/tools/image-converter', false)
        ->assertSee('/tools/video-downloader', false)
        ->assertSee('Video İndirici');
});

test('fetches video info for a valid URL', function () {
    mock(VideoDownloaderService::class, function ($mock) {
        $mock->shouldReceive('validateUrl')->andReturn(['valid' => true, 'platform' => 'youtube', 'reason' => null]);
        $mock->shouldReceive('fetchInfo')->andReturn([
            'title' => 'Test Video',
            'thumbnail' => null,
            'duration' => 65,
            'uploader' => 'Test Kanalı',
            'maxHeight' => 1080,
        ]);
    });

    Volt::test('video-downloader')
        ->set('url', 'https://www.youtube.com/watch?v=abc123')
        ->call('fetchInfo')
        ->assertSet('videoInfo.title', 'Test Video')
        ->assertSet('videoInfo.platform', 'youtube')
        ->assertSee('Test Video')
        ->assertSee('1080p');
});

test('rejects unsupported platform URL and records security log', function () {
    Volt::test('video-downloader')
        ->set('url', 'https://www.tiktok.com/@user/video/123')
        ->call('fetchInfo')
        ->assertSet('videoInfo', null)
        ->assertSet('messageType', 'error');

    expect(SecurityLog::query()->where('event_type', 'suspicious_video_url')->count())->toBe(1);
});

test('requires a URL before fetching', function () {
    Volt::test('video-downloader')
        ->set('url', '')
        ->call('fetchInfo')
        ->assertHasErrors(['url']);
});

test('download without fetched info shows error', function () {
    Volt::test('video-downloader')
        ->call('download')
        ->assertSet('messageType', 'error');
});

test('prepares download and exposes a token URL', function () {
    $dir = storage_path('app/private/videos');
    File::ensureDirectoryExists($dir);
    $path = $dir.'/vid_pesttest.mp4';
    file_put_contents($path, 'fake-video-data');

    mock(VideoDownloaderService::class, function ($mock) use ($path) {
        $mock->shouldReceive('validateUrl')->andReturn(['valid' => true, 'platform' => 'youtube', 'reason' => null]);
        $mock->shouldReceive('fetchInfo')->andReturn([
            'title' => 'Test Video',
            'thumbnail' => null,
            'duration' => 65,
            'uploader' => null,
            'maxHeight' => 720,
        ]);
        $mock->shouldReceive('isValidFormat')->with('mp4_best')->andReturnTrue();
        $mock->shouldReceive('download')->andReturn(['path' => $path, 'ext' => 'mp4', 'size' => 15]);
        $mock->shouldReceive('sanitizeFilename')->andReturn('Test Video.mp4');
    });

    $component = Volt::test('video-downloader')
        ->set('url', 'https://www.youtube.com/watch?v=abc123')
        ->call('fetchInfo')
        ->call('download')
        ->assertSet('messageType', 'success')
        ->assertSet('downloadName', 'Test Video.mp4');

    $downloadUrl = $component->get('downloadUrl');
    expect($downloadUrl)->toContain('/tools/video-downloader/file/');

    get($downloadUrl)
        ->assertSuccessful()
        ->assertDownload('Test Video.mp4');

    File::delete($path);
});

test('fetch info is rate limited per IP', function () {
    $max = (int) config('security.video.rate_limit_info', 10);

    foreach (range(1, $max) as $i) {
        \Illuminate\Support\Facades\RateLimiter::hit('video-info:127.0.0.1', 60);
    }

    Volt::test('video-downloader')
        ->set('url', 'https://www.youtube.com/watch?v=abc123')
        ->call('fetchInfo')
        ->assertSet('messageType', 'error')
        ->assertSet('videoInfo', null);
});

test('image converter conversion is rate limited per IP', function () {
    $max = (int) config('security.upload.rate_limit_convert', 10);

    foreach (range(1, $max) as $i) {
        \Illuminate\Support\Facades\RateLimiter::hit('image-convert:127.0.0.1', 60);
    }

    Volt::test('image-converter')
        ->set('convertedFiles', [['id' => 'f_test', 'status' => 'pending', 'tempPath' => null, 'tempUploadPath' => '/tmp/none', 'originalName' => 'x.png', 'originalFormat' => 'png', 'originalSize' => 1, 'originalWidth' => 1, 'originalHeight' => 1, 'previewUrl' => null, 'convertedSize' => null, 'convertedWidth' => null, 'convertedHeight' => null, 'convertedFormat' => null, 'error' => null]])
        ->call('convert')
        ->assertSet('messageType', 'error')
        ->assertSet('message', 'Çok fazla istek gönderdiniz, lütfen bir dakika bekleyin.');
});

test('download route returns 404 for unknown token', function () {
    get('/tools/video-downloader/file/'.str_repeat('a', 40))
        ->assertNotFound();
});

test('download route rejects paths outside the videos directory', function () {
    $token = str_repeat('b', 40);
    Cache::put("video_download_{$token}", ['path' => base_path('composer.json'), 'name' => 'composer.json'], 600);

    get('/tools/video-downloader/file/'.$token)
        ->assertNotFound();
});
