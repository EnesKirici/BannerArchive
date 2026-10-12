<?php

use App\Mcp\Servers\KapakServer;
use Laravel\Mcp\Facades\Mcp;

/*
| Claude bağlayıcısı (claude.ai → Ayarlar → Connectors → Add custom connector):
| adres https://bannerarchive.elw.com.tr/mcp. Claude OAuth ile panel
| girişine yönlendirir; yalnızca yönetici hesabı izin verebilir.
*/

Mcp::oauthRoutes();

Mcp::web('/mcp', KapakServer::class)
    ->middleware(['auth:api', 'admin', 'throttle:60,1']);
