<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMcpCoverUploadRequest;
use App\Services\Trailer\CoverGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Claude sohbetinden açılan görsel yükleme sayfası.
 *
 * claude.ai sohbete eklenen resmi MCP aracına iletemiyor; kullanıcı bu süreli
 * bağlantıdan (telefondan da) afiş/arka plan yükler, Claude kapak_uret'te
 * yuklenen_gorsel ile kullanır. Yetkiyi oturum değil rotadaki imza taşır.
 */
class McpCoverUploadController extends Controller
{
    public function show(CoverGenerator $covers, int $user): View
    {
        return view('mcp.cover-upload', [
            'uploads' => array_keys($covers->uploads($user)),
            'saved' => (array) session('saved', []),
        ]);
    }

    public function store(StoreMcpCoverUploadRequest $request, CoverGenerator $covers, int $user): RedirectResponse
    {
        $saved = [];

        foreach (['afis', 'arka_plan'] as $kind) {
            $file = $request->file($kind);

            if (! $file instanceof UploadedFile) {
                continue;
            }

            try {
                $covers->storeUpload($user, $kind, (string) $file->getRealPath());
                $saved[] = $kind;
            } catch (RuntimeException $exception) {
                return back()->withErrors([$kind => $exception->getMessage()]);
            }
        }

        return back()->with('saved', $saved);
    }
}
