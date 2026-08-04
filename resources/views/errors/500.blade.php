<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Sunucu Hatası</title>
    <link rel="icon" type="image/jpeg" href="/images/elw.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',system-ui,sans-serif;background:#0a0a0a;color:#fff;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;overflow:hidden;position:relative}
        .glow{position:absolute;border-radius:50%;pointer-events:none}
        .glow-1{width:600px;height:600px;background:radial-gradient(circle,rgba(239,68,68,.1) 0%,transparent 70%);top:25%;left:50%;transform:translate(-50%,-50%)}
        .glow-2{width:400px;height:400px;background:radial-gradient(circle,rgba(168,85,247,.08) 0%,transparent 70%);bottom:25%;left:33%}
        .wrap{position:relative;z-index:10;display:flex;flex-direction:column;align-items:center;text-align:center;padding:1.5rem}
        .code{font-size:clamp(8rem,18vw,14rem);font-weight:900;line-height:1;letter-spacing:-.06em;background:linear-gradient(135deg,#ef4444,#dc2626,#991b1b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;user-select:none}
        .icon{margin-bottom:1.5rem;margin-top:-.25rem}
        .icon svg{width:48px;height:48px;color:rgba(255,255,255,.15);stroke:currentColor}
        h2{font-size:1.5rem;font-weight:700;color:rgba(255,255,255,.9);margin-bottom:.75rem}
        .desc{font-size:.875rem;color:rgba(255,255,255,.35);line-height:1.7;max-width:24rem;margin-bottom:2.5rem}
        .actions{display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center}
        .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;border-radius:.75rem;font-size:.8125rem;font-weight:600;text-decoration:none;transition:all .2s;border:none;cursor:pointer;font-family:inherit}
        .btn svg{width:16px;height:16px}
        .btn-primary{background:#ef4444;color:#fff;box-shadow:0 4px 20px rgba(239,68,68,.3)}
        .btn-primary:hover{background:#dc2626;transform:translateY(-1px);box-shadow:0 6px 28px rgba(239,68,68,.4)}
        .btn-ghost{background:rgba(255,255,255,.05);color:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.08)}
        .btn-ghost:hover{background:rgba(255,255,255,.08);color:#fff}
        .logo{margin-top:4rem;display:flex;align-items:center;gap:.5rem;opacity:.15}
        .logo img{width:20px;height:20px;border-radius:4px}
        .logo span{font-size:11px}
    </style>
</head>
<body>
    <div class="glow glow-1"></div>
    <div class="glow glow-2"></div>
    <div class="wrap">
        <div class="code">500</div>
        <div class="icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
            </svg>
        </div>
        <h2>Sunucu Hatası</h2>
        <p class="desc">Sunucu isteğinizi işlerken beklenmeyen bir hatayla karşılaştı. Sorun en kısa sürede çözülecektir.</p>
        <div class="actions">
            <a href="/" class="btn btn-primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Ana Sayfa
            </a>
            <button onclick="location.reload()" class="btn btn-ghost">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                Yenile
            </button>
        </div>
        <div class="logo"><img src="/images/elw.jpg" alt="elw"><span>BannerArchive</span></div>
    </div>
</body>
</html>
