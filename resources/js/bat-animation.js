/**
 * Bat Animation — Pixel yarasaları arka planda uçurur
 * Yarasalar ekran sınırları içinde kalır, kenarlara çarpınca yön değiştirir.
 */

function createBat(container, index, config) {
    // Wrapper: pozisyon kontrolü
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'position: absolute; will-change: left, top;';

    // Bat sprite: box-shadow ~30px merkez etrafında çiziliyor
    // transform-origin'i sprite merkezine ayarlıyoruz ki flip doğal görünsün
    const bat = document.createElement('div');
    bat.className = 'bat';
    const s = config.scale;
    const opacity = 0.4 + Math.random() * 0.4;
    bat.style.cssText = `
        transform: scale(${s});
        transform-origin: 30px 25px;
        animation: ${config.flapSpeed}s bat steps(1) infinite;
        opacity: ${opacity};
    `;
    wrapper.appendChild(bat);

    const baseSpeed = 60 / config.speed;
    let x = Math.random() * 80 + 10;
    let y = Math.random() * 70 + 10;
    let angle = Math.random() * Math.PI * 2;
    let speedMultiplier = 0.7 + Math.random() * 0.6;
    let facingRight = Math.cos(angle) > 0;

    wrapper.style.left = x + '%';
    wrapper.style.top = y + '%';
    bat.style.transform = `scale(${facingRight ? -s : s}, ${s})`;

    function animate() {
        const vx = Math.cos(angle) * baseSpeed * speedMultiplier;
        const vy = Math.sin(angle) * baseSpeed * speedMultiplier;

        x += vx * 0.05;
        y += vy * 0.05;

        // Sınır kontrolü — kenara gelince yön değiştir
        if (x < 2) { angle = Math.PI - angle; x = 2; }
        if (x > 95) { angle = Math.PI - angle; x = 95; }
        if (y < 2) { angle = -angle; y = 2; }
        if (y > 85) { angle = -angle; y = 85; }

        // Küçük rastgele sapma
        angle += (Math.random() - 0.5) * 0.03;

        // Yön sadece değişince güncelle
        const nowRight = Math.cos(angle) > 0;
        if (nowRight !== facingRight) {
            facingRight = nowRight;
            bat.style.transform = `scale(${facingRight ? -s : s}, ${s})`;
        }

        wrapper.style.left = x + '%';
        wrapper.style.top = y + '%';

        requestAnimationFrame(animate);
    }

    requestAnimationFrame(animate);
    return wrapper;
}

export async function initBatAnimation() {
    const layer = document.getElementById('bat-animation-layer');
    if (!layer) return;

    try {
        const response = await fetch('/api/bat-animation/config');
        if (!response.ok) return;

        const data = await response.json();
        if (!data.enabled) return;

        // CSS dosyasını text olarak çek ve renkleri değiştir
        const cssResponse = await fetch('/css/bat-animation.css');
        if (!cssResponse.ok) return;

        let cssText = await cssResponse.text();

        const outerColor = data.outer_color || '#54556b';
        const innerColor = data.inner_color || '#202020';
        cssText = cssText.replaceAll('#54556b', outerColor);
        cssText = cssText.replaceAll('#202020', innerColor);

        const styleEl = document.createElement('style');
        styleEl.textContent = cssText;
        document.head.appendChild(styleEl);

        // Container
        layer.className = 'bat-animation-container';
        layer.style.cssText = 'position: absolute; inset: 0; overflow: hidden; pointer-events: none; z-index: 1;';

        const config = {
            scale: data.bat_scale || 2,
            speed: data.bat_speed || 20,
            flapSpeed: data.flap_speed || 0.4,
        };

        const count = Math.min(data.bat_count || 5, 30);
        for (let i = 0; i < count; i++) {
            layer.appendChild(createBat(layer, i, config));
        }
    } catch (error) {
        console.warn('Bat animation yüklenemedi:', error);
    }
}
