<?php

declare(strict_types=1);

use App\Services\VideoDownloaderService;

it('accepts supported video URLs', function (string $url, string $platform) {
    $result = (new VideoDownloaderService)->validateUrl($url);

    expect($result['valid'])->toBeTrue()
        ->and($result['platform'])->toBe($platform);
})->with([
    'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube'],
    'youtube shorts' => ['https://youtube.com/shorts/abc123xyz', 'youtube'],
    'youtube live' => ['https://www.youtube.com/live/abc123xyz', 'youtube'],
    'youtu.be short link' => ['https://youtu.be/dQw4w9WgXcQ', 'youtube'],
    'youtube mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube'],
    'instagram reel' => ['https://www.instagram.com/reel/Cxyz12345/', 'instagram'],
    'instagram reels' => ['https://instagram.com/reels/Cxyz12345/', 'instagram'],
    'instagram post' => ['https://www.instagram.com/p/Cxyz12345/', 'instagram'],
    'instagram tv' => ['https://www.instagram.com/tv/Cxyz12345/', 'instagram'],
]);

it('rejects unsupported URLs', function (string $url) {
    expect((new VideoDownloaderService)->validateUrl($url)['valid'])->toBeFalse();
})->with([
    'tiktok' => 'https://www.tiktok.com/@user/video/123',
    'random site' => 'https://example.com/video.mp4',
    'javascript scheme' => 'javascript:alert(1)',
    'ftp scheme' => 'ftp://youtube.com/watch?v=1',
    'garbage text' => 'not a url at all',
    'empty string' => '',
    'youtube channel' => 'https://www.youtube.com/@SomeChannel',
    'youtube playlist' => 'https://www.youtube.com/playlist?list=PL123',
    'instagram profile' => 'https://www.instagram.com/someuser/',
    'youtu.be without video id' => 'https://youtu.be/',
    'fake youtube subdomain' => 'https://youtube.com.evil.com/watch?v=1',
    'overly long url' => 'https://www.youtube.com/watch?v='.str_repeat('a', 600),
]);

it('validates only whitelisted format keys', function () {
    $service = new VideoDownloaderService;

    expect($service->isValidFormat('mp4_best'))->toBeTrue()
        ->and($service->isValidFormat('mp4_720'))->toBeTrue()
        ->and($service->isValidFormat('mp4_480'))->toBeTrue()
        ->and($service->isValidFormat('mp3'))->toBeTrue()
        ->and($service->isValidFormat('exec'))->toBeFalse()
        ->and($service->isValidFormat(''))->toBeFalse()
        ->and($service->isValidFormat('-f best; rm -rf /'))->toBeFalse();
});

it('sanitizes download filenames', function () {
    $service = new VideoDownloaderService;

    expect($service->sanitizeFilename('My: Video / "Title"?', 'mp4'))->toBe('My Video Title.mp4')
        ->and($service->sanitizeFilename('', 'mp3'))->toBe('video.mp3')
        ->and($service->sanitizeFilename(str_repeat('a', 200), 'mp4'))->toBe(str_repeat('a', 80).'.mp4');
});
