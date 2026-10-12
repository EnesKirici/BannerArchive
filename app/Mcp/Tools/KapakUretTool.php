<?php

namespace App\Mcp\Tools;

use App\Services\Trailer\CoverGenerator;
use App\Services\Trailer\CoverSettings;
use App\Services\Trailer\ThumbnailComposer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

#[Name('kapak_uret')]
#[Description('Film/dizi için YouTube fragman kapakları üretir: video (16:9, 3 şablon) ve/veya Shorts (9:16, 2 şablon). Her kapağın küçük önizlemesini ve 3 gün geçerli indirme bağlantısını döndürür. Film adı yeterli; arka plan, afiş ve logo otomatik seçilir, istenirse gorsel_secenekleri numarasıyla değiştirilir.')]
class KapakUretTool extends Tool
{
    private const LOGO_MODES = ['otomatik' => 'auto', 'turkce' => 'tr', 'yazi' => 'text', 'yok' => 'yok'];

    public function handle(Request $request, CoverGenerator $covers, ThumbnailComposer $composer): Response|ResponseFactory
    {
        $validated = $request->validate([
            'film' => ['required_without:tmdb_id', 'nullable', 'string', 'min:2', 'max:120'],
            'tmdb_id' => ['nullable', 'integer', 'min:1'],
            'tur' => ['nullable', 'in:movie,tv'],
            'yil' => ['nullable', 'integer', 'between:1900,2100'],
            'bicim' => ['nullable', 'in:video,shorts,ikisi'],
            'serit' => ['nullable', 'string', 'max:60'],
            'film_adi' => ['nullable', 'string', 'max:80'],
            'logo' => ['nullable', 'in:otomatik,turkce,yazi,yok'],
            'arka_plan' => ['nullable', 'integer', 'min:1'],
            'afis' => ['nullable', 'integer', 'min:1'],
            'logo_no' => ['nullable', 'integer', 'min:1'],
            'renk' => ['nullable', 'string', 'regex:/^(marka|oto|#[0-9a-fA-F]{6})$/'],
            'marka_logosu' => ['nullable', 'boolean'],
            'marka_beyaz' => ['nullable', 'boolean'],
            'yil_tur' => ['nullable', 'boolean'],
        ], [
            'film.required_without' => 'Film adı (film) ya da tmdb_id gerekli.',
            'renk.regex' => 'renk: "marka", "oto" ya da #rrggbb olmalı.',
        ]);

        $userId = (int) $request->user()?->getAuthIdentifier();

        try {
            [$type, $id, $others] = $this->resolveTitle($covers, $validated);

            $choices = [];

            foreach (['arka_plan' => 'arka_plan', 'afis' => 'afis', 'logo_no' => 'logo'] as $parameter => $kind) {
                if (isset($validated[$parameter])) {
                    $choices[$kind] = $this->pick($covers, $type, $id, $kind, (int) $validated[$parameter]);
                }
            }

            $result = $covers->generate(
                $userId,
                $type,
                $id,
                $this->settings($validated, isset($choices['logo'])),
                match ($validated['bicim'] ?? 'ikisi') {
                    'video' => ['video'],
                    'shorts' => ['shorts'],
                    default => ['video', 'shorts'],
                },
                $choices,
            );
        } catch (RuntimeException $exception) {
            return Response::error($exception->getMessage());
        }

        $labels = $composer->options();
        $formats = $composer->formats();

        $summary = sprintf('%s · %s · tmdb_id=%d, tur=%s — %d kapak üretildi.', $result['title'], $type === 'tv' ? 'dizi' : 'film', $id, $type, count($result['rendered']));

        if ($result['notice'] !== null) {
            $summary .= "\nNot: ".$result['notice'];
        }

        if ($others !== []) {
            $summary .= "\nDiğer eşleşmeler (yanlış filmse tmdb_id ile tekrar üret): ".collect($others)
                ->map(fn (array $item): string => "{$item['title']} ({$item['year']}, tmdb_id={$item['id']}, tur={$item['type']})")
                ->implode('; ');
        }

        $responses = [Response::text($summary)];
        $number = 0;

        foreach ($result['rendered'] as $key => $path) {
            $format = $formats[$key] ?? 'video';

            $responses[] = Response::text(sprintf(
                '[%d] %s — %s %s · indir: %s',
                ++$number,
                $labels[$key] ?? $key,
                $format === 'shorts' ? 'Shorts' : 'video',
                $format === 'shorts'
                    ? config('trailer.shorts.width').'×'.config('trailer.shorts.height')
                    : config('trailer.thumbnail.width').'×'.config('trailer.thumbnail.height'),
                $covers->downloadUrl($userId, $result['batch'], $path),
            ));
            $responses[] = Response::image($covers->preview($path), 'image/jpeg');
        }

        return Response::make($responses);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'film' => $schema->string()->description('Film ya da dizi adı (örn. "Fall 2"). tmdb_id verilirse gerekmez.'),
            'tmdb_id' => $schema->integer()->description('Kesin eşleşme için TMDB kimliği (önceki yanıttan ya da film_ara\'dan).'),
            'tur' => $schema->string()->enum(['movie', 'tv'])->description('tmdb_id ile birlikte: movie = film, tv = dizi.'),
            'yil' => $schema->integer()->description('Aynı adlı yapımları ayırmak için çıkış yılı.'),
            'bicim' => $schema->string()->enum(['video', 'shorts', 'ikisi'])->description('Varsayılan: ikisi.')->default('ikisi'),
            'serit' => $schema->string()->description('Şerit metni, kullanıcının yazdığı gibi (örn. "17 Ekim\'de Sinemalarda!"). Kısayollar: "altyazi" = Türkçe Altyazılı Fragman (varsayılan), "dublaj" = Türkçe Dublaj Fragman, "yok" = şerit basılmaz.'),
            'film_adi' => $schema->string()->description('Logo yerine basılacak film adı; TMDB\'deki ad yanlışsa ya da Türkçe ad isteniyorsa.'),
            'logo' => $schema->string()->enum(array_keys(self::LOGO_MODES))
                ->description('otomatik = TMDB logosu (Türkçe öncelikli) · turkce = sadece Türkçe logo, yoksa yazı · yazi = her zaman yazı · yok = ad basılmaz.')
                ->default('otomatik'),
            'arka_plan' => $schema->integer()->description('gorsel_secenekleri (cesit=arka_plan) numarası; video kapaklarının zemini.'),
            'afis' => $schema->integer()->description('gorsel_secenekleri (cesit=afis) numarası; Shorts zemini ve afiş kartı.'),
            'logo_no' => $schema->integer()->description('gorsel_secenekleri (cesit=logo) numarası.'),
            'renk' => $schema->string()->description('Şerit/vurgu rengi: "marka" (kurumsal mavi, varsayılan), "oto" (afişten) ya da #rrggbb.')->default('marka'),
            'marka_logosu' => $schema->boolean()->description('Köşeye Avşar Sinema logosu basılsın mı.')->default(true),
            'marka_beyaz' => $schema->boolean()->description('Marka logosu beyaza boyansın mı.')->default(false),
            'yil_tur' => $schema->boolean()->description('"2026 • Aksiyon" satırı basılsın mı.')->default(true),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: int, 2: array<int, array{id: int, type: string, title: string, original: string, year: string}>}
     */
    private function resolveTitle(CoverGenerator $covers, array $validated): array
    {
        if (isset($validated['tmdb_id'])) {
            return [$validated['tur'] ?? 'movie', (int) $validated['tmdb_id'], []];
        }

        $results = $covers->search((string) $validated['film'], isset($validated['yil']) ? (int) $validated['yil'] : null);

        if ($results === []) {
            throw new RuntimeException("TMDB'de \"{$validated['film']}\" bulunamadı. Farklı yazımı ya da orijinal adı deneyin.");
        }

        $first = array_shift($results);

        return [$first['type'], $first['id'], array_slice($results, 0, 4)];
    }

    private function pick(CoverGenerator $covers, string $type, int $id, string $kind, int $number): string
    {
        $options = $covers->options($type, $id, $kind);

        return $options[$number - 1]['path']
            ?? throw new RuntimeException(sprintf('%s #%d yok; %d seçenek var.', $kind, $number, count($options)));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function settings(array $validated, bool $logoChosen): CoverSettings
    {
        $ribbon = trim((string) ($validated['serit'] ?? 'altyazi'));
        $color = (string) ($validated['renk'] ?? 'marka');

        return new CoverSettings(
            ribbon: in_array(mb_strtolower($ribbon), ['altyazi', 'dublaj', 'yok'], true)
                ? CoverSettings::ribbonFor(mb_strtolower($ribbon))
                : $ribbon,
            logoMode: self::LOGO_MODES[$validated['logo'] ?? 'otomatik'],
            customTitle: (string) ($validated['film_adi'] ?? ''),
            showMeta: (bool) ($validated['yil_tur'] ?? true),
            accentMode: str_starts_with($color, '#') ? 'ozel' : $color,
            accent: str_starts_with($color, '#') ? $color : '',
            brand: (bool) ($validated['marka_logosu'] ?? true),
            brandWhite: (bool) ($validated['marka_beyaz'] ?? false),
            logoChosen: $logoChosen,
        );
    }
}
