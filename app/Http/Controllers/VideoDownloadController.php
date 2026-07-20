<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VideoDownloadController extends Controller
{
    /**
     * Hazırlanan video dosyasını tek kullanımlık token ile indir.
     */
    public function download(string $token): BinaryFileResponse
    {
        if (! preg_match('/^[a-zA-Z0-9]{40}$/', $token)) {
            abort(404);
        }

        $file = Cache::get("video_download_{$token}");

        if (! is_array($file) || empty($file['path']) || ! file_exists($file['path'])) {
            abort(404, 'Dosya bulunamadı veya süresi doldu.');
        }

        $realPath = realpath($file['path']);
        $videosDir = realpath(storage_path('app/private/videos'));

        if ($realPath === false || $videosDir === false || ! str_starts_with($realPath, $videosDir)) {
            abort(404);
        }

        return response()->download($realPath, $file['name']);
    }
}
