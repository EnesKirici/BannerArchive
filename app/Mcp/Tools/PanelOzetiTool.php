<?php

namespace App\Mcp\Tools;

use App\Models\ActivityLog;
use App\Models\BlockedIp;
use App\Models\LoginHistory;
use App\Models\ParticleTheme;
use App\Models\SecurityLog;
use App\Models\Setting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('panel_ozeti')]
#[Description('Admin panelinin özeti: giriş sayıları, bugünkü ziyaret/arama/indirme, güvenlik olayları, engelli IP sayısı, site ayarları, aktif particle teması, cache durumu ve son girişler. "Site nasıl", "bugün ne oldu" gibi sorularda önce bunu çağır.')]
#[IsReadOnly]
class PanelOzetiTool extends Tool
{
    public function handle(Request $request): Response
    {
        $today = now()->startOfDay();

        $lines = [
            '## Bugün ('.now()->format('d.m.Y H:i').')',
            sprintf(
                '- Aktivite: %d olay · %d arama · %d banner indirme · %d video indirme · %d tekil IP',
                ActivityLog::where('created_at', '>=', $today)->count(),
                ActivityLog::where('action', 'search')->where('created_at', '>=', $today)->count(),
                ActivityLog::where('action', 'download')->where('created_at', '>=', $today)->count(),
                ActivityLog::where('action', 'video_download')->where('created_at', '>=', $today)->count(),
                ActivityLog::where('created_at', '>=', $today)->distinct('ip_address')->count('ip_address'),
            ),
            sprintf(
                '- Güvenlik: %d olay · %d ban · %d uyarı · %d şüpheli yükleme',
                SecurityLog::where('created_at', '>=', $today)->count(),
                SecurityLog::where('event_type', 'like', 'ban_%')->where('created_at', '>=', $today)->count(),
                SecurityLog::where('event_type', 'rate_warning')->where('created_at', '>=', $today)->count(),
                SecurityLog::where('event_type', 'suspicious_upload')->where('created_at', '>=', $today)->count(),
            ),
            sprintf(
                '- Son 24 saatte başarısız giriş yapan tekil IP: %d',
                LoginHistory::where('success', false)->where('created_at', '>=', now()->subDay())->distinct('ip_address')->count('ip_address'),
            ),
            '',
            '## Genel',
            sprintf(
                '- Girişler: %d başarılı, %d başarısız · Engelli IP: %d (%d kalıcı)',
                LoginHistory::where('success', true)->count(),
                LoginHistory::where('success', false)->count(),
                BlockedIp::count(),
                BlockedIp::whereNull('blocked_until')->count(),
            ),
            sprintf(
                '- Particle: aktif tema "%s" (%d tema) · Yarasa animasyonu: %s',
                ParticleTheme::active()?->name ?? 'yok',
                ParticleTheme::count(),
                Setting::get('bat_animation_enabled', false) ? 'açık' : 'kapalı',
            ),
            sprintf(
                '- Ayarlar: site adı "%s" · ana renk %s · galeri görünümü %s · particle katmanı %s',
                Setting::get('site_name', 'BannerArchive'),
                Setting::get('primary_color', '#d946ef'),
                Setting::get('gallery_view_mode', 'gallery'),
                Setting::get('particles_layer', 'background'),
            ),
            sprintf(
                '- Cache: sürücü %s · config cache %s · route cache %s · %d derlenmiş view',
                config('cache.default'),
                file_exists(base_path('bootstrap/cache/config.php')) ? 'aktif' : 'yok',
                file_exists(base_path('bootstrap/cache/routes-v7.php')) ? 'aktif' : 'yok',
                count(glob(storage_path('framework/views/*.php')) ?: []),
            ),
            '',
            '## Son girişler',
        ];

        foreach (LoginHistory::with('user')->latest()->take(5)->get() as $login) {
            $lines[] = sprintf(
                '- %s · %s · %s · %s',
                $login->created_at?->format('d.m.Y H:i'),
                $login->success ? 'başarılı' : 'BAŞARISIZ',
                $login->user?->email ?? 'bilinmeyen kullanıcı',
                $login->ip_address,
            );
        }

        return Response::text(implode("\n", $lines));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
