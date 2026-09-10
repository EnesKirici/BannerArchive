<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * Gerçek bir PNG üretir (UploadedFile::fake()->image() GD ile aynı işi yapar
 * ama uzantı/MIME denetiminden geçmesi için gerçek içerik şart).
 */
function fakePng(string $name, int $w = 64, int $h = 48): UploadedFile
{
    return UploadedFile::fake()->image($name, $w, $h);
}

function seededStatuses(\Livewire\Features\SupportTesting\Testable $component): array
{
    return array_column($component->get('convertedFiles'), 'status');
}

test('image converter page renders', function () {
    get('/tools/image-converter')
        ->assertSuccessful()
        ->assertSee('Resim Dönüştürücü');
});

test('uploads and converts every file to webp in one request when budget allows', function () {
    $component = Volt::test('image-converter')
        ->set('photos', [fakePng('a.png'), fakePng('b.png'), fakePng('c.png')]);

    expect(seededStatuses($component))->toBe(['pending', 'pending', 'pending']);

    $component->call('convertWithOptions', 'webp', 80)
        ->assertSet('message', '3 dosya başarıyla dönüştürüldü.')
        ->assertSet('messageType', 'success');

    expect(seededStatuses($component))->toBe(['done', 'done', 'done']);

    foreach ($component->get('convertedFiles') as $file) {
        expect($file['convertedFormat'])->toBe('webp')
            ->and($file['tempPath'])->toEndWith('.webp')
            ->and(filesize($file['tempPath']))->toBeGreaterThan(0);

        @unlink($file['tempPath']);
    }
});

test('hands remaining files to the next request when the time budget is exhausted', function () {
    // Bütçe 0 sn → her istek en fazla bir dosya işler, kalanı zincire bırakır.
    config()->set('security.upload.convert_budget_seconds', 0);

    $component = Volt::test('image-converter')
        ->set('photos', [fakePng('a.png'), fakePng('b.png'), fakePng('c.png')])
        ->call('convert');

    // İlk istek: 1 dosya bitti, sıradaki "converting" işaretlendi, diğeri sırada.
    expect(seededStatuses($component))->toBe(['done', 'converting', 'queued']);
    $component->assertSet('message', '');

    // Zincir sürerken buton kilitli, tekrar tetiklenen convert() hiçbir şey yapmaz.
    $component->call('convert');
    expect(seededStatuses($component))->toBe(['done', 'converting', 'queued']);

    // Tarayıcının tetiklediği devam istekleri.
    $component->call('convertBatch');
    expect(seededStatuses($component))->toBe(['done', 'done', 'converting']);

    $component->call('convertBatch')
        ->assertSet('message', '3 dosya başarıyla dönüştürüldü.');
    expect(seededStatuses($component))->toBe(['done', 'done', 'done']);

    foreach ($component->get('convertedFiles') as $file) {
        @unlink($file['tempPath']);
    }
});

test('convertBatch ignores files that were not queued by convert()', function () {
    $component = Volt::test('image-converter')
        ->set('photos', [fakePng('a.png')])
        ->call('convertBatch');

    expect(seededStatuses($component))->toBe(['pending'])
        ->and($component->get('convertedFiles')[0]['tempPath'])->toBeNull();
});

test('reports a per-file error without stopping the batch', function () {
    $component = Volt::test('image-converter')
        ->set('photos', [fakePng('a.png'), fakePng('b.png')]);

    $files = $component->get('convertedFiles');
    $files[0]['tempUploadPath'] = '/nonexistent/gone.png';

    $component->set('convertedFiles', $files)
        ->call('convert')
        ->assertSet('message', '1 dosya başarıyla dönüştürüldü.');

    $files = $component->get('convertedFiles');
    expect($files[0]['status'])->toBe('error')
        ->and($files[0]['error'])->toBe('Dosya bulunamadı, lütfen tekrar yükleyin.')
        ->and($files[1]['status'])->toBe('done');

    @unlink($files[1]['tempPath']);
});
