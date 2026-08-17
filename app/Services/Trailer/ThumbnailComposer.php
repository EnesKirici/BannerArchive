<?php

namespace App\Services\Trailer;

use App\Services\Trailer\Templates\CinemaTemplate;
use App\Services\Trailer\Templates\PosterCardTemplate;
use App\Services\Trailer\Templates\PosterFocusTemplate;
use App\Services\Trailer\Templates\ShortsCinemaTemplate;
use App\Services\Trailer\Templates\ShortsPosterTemplate;
use App\Services\Trailer\Templates\ThumbnailTemplate;
use App\Support\Image\Canvas;
use App\Support\Image\Palette;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Şablonları toplayan ve kapağı diske yazan giriş noktası.
 *
 * Yeni bir şablon eklemek için ThumbnailTemplate'i uygulayıp yapıcıya eklemek
 * yeterli; panel listesi ve komut kendiliğinden görür.
 */
final class ThumbnailComposer
{
    /** @var array<string, ThumbnailTemplate> */
    private array $templates = [];

    public function __construct(
        PosterCardTemplate $posterCard,
        CinemaTemplate $cinema,
        PosterFocusTemplate $posterFocus,
        ShortsCinemaTemplate $shortsCinema,
        ShortsPosterTemplate $shortsPoster,
    ) {
        foreach ([$posterCard, $cinema, $posterFocus, $shortsCinema, $shortsPoster] as $template) {
            $this->templates[$template->key()] = $template;
        }
    }

    /** @return array<string, string> anahtar => görünen ad */
    public function options(): array
    {
        return array_map(
            static fn (ThumbnailTemplate $template): string => $template->label(),
            $this->templates,
        );
    }

    /** @return array<string, string> anahtar => biçim ('video' | 'shorts') */
    public function formats(): array
    {
        return array_map(
            static fn (ThumbnailTemplate $template): string => $template->format(),
            $this->templates,
        );
    }

    /**
     * Bu içerik için gereken görsellere sahip şablonlar.
     *
     * @param  array<int, string>|null  $formats  null = tüm biçimler
     * @return array<int, string>
     */
    public function availableFor(ThumbnailPayload $payload, ?array $formats = null): array
    {
        return array_keys(array_filter(
            $this->templates,
            static fn (ThumbnailTemplate $template): bool => $template->supports($payload)
                && ($formats === null || in_array($template->format(), $formats, true)),
        ));
    }

    public function render(ThumbnailPayload $payload, string $key, ?string $directory = null): string
    {
        $template = $this->templates[$key]
            ?? throw new InvalidArgumentException("Bilinmeyen kapak şablonu: {$key}");

        if (! $template->supports($payload)) {
            throw new RuntimeException("'{$template->label()}' şablonu için gereken görseller eksik.");
        }

        $directory ??= (string) config('trailer.storage.thumbnails');
        $name = Str::slug($payload->title) ?: 'kapak';

        return $template->render($payload)->toJpeg(
            rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name.'-'.$key.'.jpg',
            (int) config('trailer.thumbnail.quality', 88),
        );
    }

    /**
     * @param  array<int, string>|null  $formats  null = tüm biçimler
     * @return array<string, string> anahtar => dosya yolu
     */
    public function renderAll(ThumbnailPayload $payload, ?string $directory = null, ?array $formats = null): array
    {
        // Vurgu rengi afişin piksellerinden çıkarılıyor; her şablon için ayrı
        // değil, tur başına bir kez hesaplansın.
        if ($payload->accent === null || trim($payload->accent) === '') {
            $payload = $payload->with(accent: Palette::accentFor($payload->poster ?? $payload->backdrop));
        }

        $rendered = [];

        foreach ($this->availableFor($payload, $formats) as $key) {
            $rendered[$key] = $this->render($payload, $key, $directory);
        }

        // Çözülmüş görseller bellekte tutuluyordu; tur bitti, bırakalım.
        Canvas::flushDecoded();

        return $rendered;
    }
}
