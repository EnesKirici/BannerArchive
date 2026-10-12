<?php

namespace App\Mcp\Tools;

use App\Models\ParticleTheme;
use App\Models\Setting;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('particle_temalari')]
#[Description('Sitenin arka plan particle temalarını (id, ad, aktif mi, hazır mı, renk) ve yarasa animasyonu ayarlarını listeler. tema_id verilirse o temanın tam JSON yapılandırmasını da döndürür.')]
#[IsReadOnly]
class ParticleTemalariTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'tema_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (isset($validated['tema_id'])) {
            $theme = ParticleTheme::find($validated['tema_id']);

            return $theme === null
                ? Response::error("#{$validated['tema_id']} numaralı tema yok.")
                : Response::text(sprintf("#%d %s (%s)\n%s", $theme->id, $theme->name, $theme->is_active ? 'aktif' : 'pasif', json_encode($theme->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)));
        }

        $themes = ParticleTheme::orderBy('is_preset', 'desc')->orderBy('name')->get();

        $lines = $themes->map(fn (ParticleTheme $theme): string => sprintf(
            '- #%d %s%s%s · renk %s%s',
            $theme->id,
            $theme->name,
            $theme->is_active ? ' · AKTİF' : '',
            $theme->is_preset ? ' · hazır tema' : ' · özel',
            $theme->preview_color ?? '—',
            ($theme->config['renderer'] ?? null) === 'spider' ? ' · örümcek' : '',
        ));

        $lines->push('', sprintf(
            'Yarasa animasyonu: %s · adet %d · hız %d · boyut %s · kanat hızı %s · renkler %s / %s',
            Setting::get('bat_animation_enabled', false) ? 'AÇIK' : 'kapalı',
            (int) Setting::get('bat_animation_count', 5),
            (int) Setting::get('bat_animation_speed', 20),
            Setting::get('bat_animation_scale', 2.0),
            Setting::get('bat_flap_speed', 0.4) ?? 0.4,
            Setting::get('bat_outer_color', '#54556b') ?? '#54556b',
            Setting::get('bat_inner_color', '#202020') ?? '#202020',
        ));

        return Response::text($themes->isEmpty() ? "Tema yok (particle_tema_yonet islem=hazirlari_yukle ile hazır temalar eklenebilir).\n".$lines->last() : $lines->implode("\n"));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'tema_id' => $schema->integer()->description('Verilirse o temanın tam yapılandırması döner.'),
        ];
    }
}
