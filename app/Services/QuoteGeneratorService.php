<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class QuoteGeneratorService
{
    private string $lastError = '';

    private string $usedModel = '';

    /**
     * @param  array<int, string>  $models
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly array $models,
        private readonly string $baseUrl,
    ) {}

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function getUsedModel(): string
    {
        return $this->usedModel;
    }

    /**
     * @return array<int, string>
     */
    public function generateQuotes(string $title, string $overview, string $type, string $style = ''): array
    {
        if (empty($this->apiKey)) {
            $this->lastError = 'Gemini API anahtarı tanımlı değil';

            return [];
        }

        if (empty($this->models)) {
            $this->lastError = 'Hiçbir Gemini modeli tanımlı değil';

            return [];
        }

        $prompt = $this->buildPrompt($title, $overview, $type, $style);

        foreach ($this->models as $model) {
            $result = $this->tryModel($model, $prompt);

            if ($result !== null) {
                $this->usedModel = $model;

                return $result;
            }
        }

        return [];
    }

    /**
     * @return array<int, string>|null
     */
    private function tryModel(string $model, string $prompt): ?array
    {
        $maxAttempts = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = Http::timeout(30)
                    ->withHeader('x-goog-api-key', $this->apiKey)
                    ->post(
                        "{$this->baseUrl}/{$model}:generateContent",
                        [
                            'contents' => [
                                [
                                    'parts' => [
                                        ['text' => $prompt],
                                    ],
                                ],
                            ],
                            'generationConfig' => [
                                'temperature' => 0.9,
                                'maxOutputTokens' => 8192,
                            ],
                        ]
                    );

                if ($response->status() === 429) {
                    $this->lastError = "Model {$model} rate limit aşıldı, sonraki modele geçiliyor...";

                    return null;
                }

                if (! $response->successful()) {
                    $this->lastError = "Gemini API [{$model}] {$response->status()}: {$response->body()}";

                    if ($attempt < $maxAttempts) {
                        sleep(2);

                        continue;
                    }

                    return null;
                }

                $text = $response->json('candidates.0.content.parts.0.text', '');

                if (empty($text)) {
                    $this->lastError = "Gemini [{$model}] boş yanıt döndü: ".json_encode($response->json());

                    return null;
                }

                $parsed = $this->parseQuotes($text);

                if (empty($parsed)) {
                    $this->lastError = "JSON parse edilemedi [{$model}]: ".substr($text, 0, 500);

                    return null;
                }

                return $parsed;
            } catch (\Exception $e) {
                $this->lastError = "Exception [{$model}]: {$e->getMessage()}";

                if ($attempt < $maxAttempts) {
                    sleep(2);

                    continue;
                }

                return null;
            }
        }

        return null;
    }

    private function buildPrompt(string $title, string $overview, string $type, string $style): string
    {
        $mediaType = $type === 'movie' ? 'film' : 'dizi';

        $styleInstruction = '';
        if ($style !== '') {
            $styleInstruction = "\n        EK STİL TALİMATI: Sözlerin tarzı şu şekilde olsun: {$style}\n";
        }

        return <<<PROMPT
        Sen bir profesyonel banner tasarımcısının yaratıcı yazarısın.

        Görevin: Aşağıdaki {$mediaType} için web banner'larında kullanılacak 10-15 adet Türkçe slogan/söz üretmek.

        --- {$mediaType} BİLGİLERİ ---
        Ad: {$title}
        Özet: {$overview}
        ---

        Önce bu {$mediaType}'in ana temalarını, duygusal tonunu ve atmosferini analiz et. Sonra bu analizi kullanarak sözler üret.

        SLOGAN KURALLARI:
        - Her söz en fazla 1 veya 2-3 kısa cümle olsun (banner'da okunabilir uzunlukta)
        - {$mediaType}'in atmosferini ve ana mesajını yansıtsın
        - Dramatik, özete bağlı ve akılda kalıcı olsun
        - Bazıları gizemli, bazıları duygusal, bazıları güçlü/epik olsun (çeşitlilik)
        - Doğrudan karakter adı veya hikaye içinden anlat.
        - "Bir {$mediaType} ki..." gibi klişe kalıplardan kaçın
        {$styleInstruction}
        YANITINI SADECE JSON DİZİSİ OLARAK DÖN, başka hiçbir şey yazma:
        ["söz1", "söz2", ...]
        PROMPT;
    }

    /**
     * @return array<int, string>
     */
    private function parseQuotes(string $text): array
    {
        $text = trim($text);

        // Markdown code block temizle (tam veya kesik)
        if (preg_match('/```(?:json)?\s*(.*?)(?:\s*```|$)/s', $text, $matches)) {
            $text = $matches[1];
        }

        $decoded = json_decode($text, true);

        if (is_array($decoded) && count($decoded) > 0) {
            return array_values(array_filter($decoded, fn ($item) => is_string($item) && strlen($item) > 0));
        }

        // Kesik JSON kurtarma: son tamamlanmış string'e kadar kes ve ] ekle
        if (str_starts_with(trim($text), '[')) {
            $lastQuoteEnd = strrpos($text, '",');
            if ($lastQuoteEnd === false) {
                $lastQuoteEnd = strrpos($text, '"');
            }

            if ($lastQuoteEnd !== false) {
                $truncated = substr($text, 0, $lastQuoteEnd + 1).']';
                $decoded = json_decode($truncated, true);

                if (is_array($decoded) && count($decoded) > 0) {
                    return array_values(array_filter($decoded, fn ($item) => is_string($item) && strlen($item) > 0));
                }
            }
        }

        return [];
    }
}
