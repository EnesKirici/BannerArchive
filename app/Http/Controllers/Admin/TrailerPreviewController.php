<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Kapak Stüdyosu'nda üretilen önizlemeleri sunar.
 *
 * Kapaklar herkese açık bir klasöre değil, kullanıcı bazlı ayrılmış özel
 * depoya yazılır; buradan sadece giriş yapmış yöneticiye servis edilir.
 */
class TrailerPreviewController extends Controller
{
    public function show(Request $request, string $file): BinaryFileResponse
    {
        // Str::slug çıktısı: küçük harf, rakam ve tire. Başka hiçbir şeye izin yok.
        $name = basename($file);

        abort_unless(preg_match('/^[a-z0-9\-_]+\.jpg$/', $name) === 1, 404);

        $path = storage_path('app/private/trailer/thumbnails/'.$request->user()->id.'/'.$name);

        abort_unless(is_file($path), 404);

        return $request->boolean('indir')
            ? response()->download($path, $name)
            : response()->file($path, ['Cache-Control' => 'private, no-store']);
    }
}
