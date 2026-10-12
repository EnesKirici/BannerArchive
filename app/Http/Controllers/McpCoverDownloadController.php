<?php

namespace App\Http\Controllers;

use App\Services\Trailer\CoverGenerator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Claude'un (MCP) ürettiği kapakları sunar.
 *
 * Bağlantı claude.ai sohbetinden, çoğu zaman telefondan açılıyor; oturum
 * istemek yerine süreli imza (signed middleware) yetkiyi taşır.
 */
class McpCoverDownloadController extends Controller
{
    public function __invoke(CoverGenerator $covers, int $user, string $batch, string $file): BinaryFileResponse
    {
        $path = $covers->storedFile($user, $batch, $file);

        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="'.$file.'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
