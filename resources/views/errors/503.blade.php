<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 — Bakım Modu</title>
    <link rel="icon" type="image/jpeg" href="/images/elw.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',system-ui,sans-serif;background:#0a0a0a;color:#fff;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;overflow:hidden;position:relative}
        .glow{position:absolute;border-radius:50%;pointer-events:none}
        .glow-1{width:600px;height:600px;background:radial-gradient(circle,rgba(234,179,8,.08) 0%,transparent 70%);top:25%;left:50%;transform:translate(-50%,-50%)}
        .glow-2{width:400px;height:400px;background:radial-gradient(circle,rgba(168,85,247,.06) 0%,transparent 70%);bottom:25%;left:33%}
        .wrap{position:relative;z-index:10;display:flex;flex-direction:column;align-items:center;text-align:center;padding:1.5rem}
        .code{font-size:clamp(8rem,18vw,14rem);font-weight:900;line-height:1;letter-spacing:-.06em;background:linear-gradient(135deg,#eab308,#ca8a04,#a16207);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;user-select:none}
        .icon{margin-bottom:1.5rem;margin-top:-.25rem}
        .icon svg{width:48px;height:48px;color:rgba(255,255,255,.15);stroke:currentColor}
        h2{font-size:1.5rem;font-weight:700;color:rgba(255,255,255,.9);margin-bottom:.75rem}
        .desc{font-size:.875rem;color:rgba(255,255,255,.35);line-height:1.7;max-width:24rem;margin-bottom:2.5rem}
        .actions{display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center}
        .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;border-radius:.75rem;font-size:.8125rem;font-weight:600;text-decoration:none;transition:all .2s;border:none;cursor:pointer;font-family:inherit}
        .btn svg{width:16px;height:16px}
        .btn-primary{background:#eab308;color:#000;box-shadow:0 4px 20px rgba(234,179,8,.3)}
        .btn-primary:hover{background:#ca8a04;transform:translateY(-1px);box-shadow:0 6px 28px rgba(234,179,8,.4)}
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
        <div class="code">503</div>
        <div class="icon">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085"/>
            </svg>
        </div>
        <h2>Bakım Modu</h2>
        <p class="desc">Site şu anda bakımda. Kısa bir süre içinde tekrar hizmetinizde olacağız.</p>
        <div class="actions">
            <button onclick="location.reload()" class="btn btn-primary">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                Tekrar Dene
            </button>
        </div>
        <div class="logo"><img src="/images/elw.jpg" alt="elw"><span>BannerArchive</span></div>
    </div>
</body>
</html>
