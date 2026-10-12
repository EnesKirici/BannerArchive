<?php

declare(strict_types=1);

use App\Mcp\Servers\KapakServer;
use App\Mcp\Tools\AktiviteLoglariTool;
use App\Mcp\Tools\EngelliIplerTool;
use App\Mcp\Tools\GirisGecmisiTool;
use App\Mcp\Tools\GuvenlikLoglariTool;
use App\Mcp\Tools\PanelOzetiTool;
use App\Mcp\Tools\ParticleTemalariTool;
use App\Models\ActivityLog;
use App\Models\BlockedIp;
use App\Models\LoginHistory;
use App\Models\ParticleTheme;
use App\Models\SecurityLog;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'email' => 'yonetici@example.com']);
});

test('panel özeti bugünün sayılarını ve son girişleri verir', function () {
    LoginHistory::create(['user_id' => $this->admin->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Firefox', 'success' => true]);
    LoginHistory::create(['user_id' => User::factory()->create()->id, 'ip_address' => '10.0.0.9', 'user_agent' => 'curl', 'success' => false]);
    ActivityLog::create(['ip_address' => '10.0.0.2', 'action' => 'search', 'description' => 'Arama: Fall 2']);
    BlockedIp::create(['ip_address' => '10.0.0.66', 'reason' => 'Rate limit', 'ban_type' => 'rate_limit']);

    KapakServer::actingAs($this->admin)
        ->tool(PanelOzetiTool::class)
        ->assertOk()
        ->assertSee(['1 olay · 1 arama', '1 başarılı, 1 başarısız', 'Engelli IP: 1 (1 kalıcı)', 'yonetici@example.com', 'BAŞARISIZ']);
});

test('giriş geçmişi duruma ve aramaya göre süzülür', function () {
    LoginHistory::create(['user_id' => $this->admin->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'Firefox', 'success' => true]);
    LoginHistory::create(['user_id' => User::factory()->create(['email' => 'deneme@example.com'])->id, 'ip_address' => '10.9.9.9', 'user_agent' => 'curl', 'success' => false]);

    KapakServer::actingAs($this->admin)
        ->tool(GirisGecmisiTool::class, ['durum' => 'basarisiz'])
        ->assertOk()
        ->assertSee('10.9.9.9')
        ->assertDontSee('10.0.0.1 ·');

    KapakServer::actingAs($this->admin)
        ->tool(GirisGecmisiTool::class, ['arama' => 'yonetici'])
        ->assertSee('10.0.0.1')
        ->assertDontSee('10.9.9.9');
});

test('aktivite logları işlem türüne göre süzülür ve türleri listeler', function () {
    ActivityLog::create(['ip_address' => '10.0.0.2', 'action' => 'search', 'description' => 'Arama: Fall 2']);
    ActivityLog::create(['ip_address' => '10.0.0.3', 'action' => 'download', 'description' => 'İndirme: Dune']);

    KapakServer::actingAs($this->admin)
        ->tool(AktiviteLoglariTool::class, ['islem' => 'download'])
        ->assertOk()
        ->assertSee(['İşlem türleri: download, search', 'İndirme: Dune'])
        ->assertDontSee('Arama: Fall 2');
});

test('güvenlik logları olay türüne göre süzülür', function () {
    SecurityLog::create(['ip_address' => '10.0.0.4', 'event_type' => 'ban_rate_limit', 'description' => 'Rate limit aşımı']);
    SecurityLog::create(['ip_address' => '10.0.0.5', 'event_type' => 'suspicious_upload', 'description' => 'PHP dosyası']);

    KapakServer::actingAs($this->admin)
        ->tool(GuvenlikLoglariTool::class, ['olay_turu' => 'suspicious_upload'])
        ->assertOk()
        ->assertSee(['Toplam 2 kayıt', 'PHP dosyası'])
        ->assertDontSee('Rate limit aşımı');
});

test('engelli IP listesi süresi dolmuşları göstermez ve hiçbir şeyi silmez', function () {
    BlockedIp::create(['ip_address' => '10.0.0.7', 'reason' => 'Manuel', 'ban_type' => 'manual']);
    BlockedIp::create(['ip_address' => '10.0.0.8', 'reason' => 'Eski', 'ban_type' => 'rate_limit', 'blocked_until' => now()->subHour()]);

    KapakServer::actingAs($this->admin)
        ->tool(EngelliIplerTool::class)
        ->assertOk()
        ->assertSee(['Etkin 1 engel', '10.0.0.7', 'KALICI'])
        ->assertDontSee('10.0.0.8');

    expect(BlockedIp::count())->toBe(2);
});

test('particle temaları listelenir, tema yapılandırması istenebilir', function () {
    $theme = ParticleTheme::create(['name' => 'Altıgenler', 'slug' => 'hexagons', 'config' => ['fpsLimit' => 60], 'preview_color' => '#d946ef', 'is_preset' => true, 'is_active' => true]);

    KapakServer::actingAs($this->admin)
        ->tool(ParticleTemalariTool::class)
        ->assertOk()
        ->assertSee(["#{$theme->id} Altıgenler · AKTİF · hazır tema", 'Yarasa animasyonu: kapalı']);

    KapakServer::actingAs($this->admin)
        ->tool(ParticleTemalariTool::class, ['tema_id' => $theme->id])
        ->assertSee('"fpsLimit": 60');
});

test('MCP sunucusu yalnızca okuma araçlarını ve kapak araçlarını sunar', function () {
    Laravel\Passport\Passport::actingAs($this->admin, ['mcp:use']);

    $tools = collect($this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->json('result.tools'))->pluck('name');

    expect($tools->sort()->values()->all())->toBe([
        'aktivite_loglari', 'engelli_ipler', 'film_ara', 'giris_gecmisi', 'gorsel_secenekleri',
        'gorsel_yukleme_linki', 'guvenlik_loglari', 'kapak_uret', 'panel_ozeti', 'particle_temalari',
    ]);
});
