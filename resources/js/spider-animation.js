/**
 * Spider Cursor Animation — Fare imlecini takip eden örümcek efekti
 * Canvas tabanlı, particles renderer olarak veya bağımsız çalışır.
 */

const { sin, cos, PI, hypot, min, max } = Math;

function rnd(x = 1, dx = 0) {
    return Math.random() * x + dx;
}

function many(n, f) {
    return [...Array(n)].map((_, i) => f(i));
}

function lerp(a, b, t) {
    return a + (b - a) * t;
}

function noise(x, y, t = 101) {
    let w0 = sin(0.3 * x + 1.4 * t + 2.0 + 2.5 * sin(0.4 * y + -1.3 * t + 1.0));
    let w1 = sin(0.2 * y + 1.5 * t + 2.8 + 2.3 * sin(0.5 * x + -1.2 * t + 0.5));
    return w0 + w1;
}

function spawn(ctx, w, h) {
    const pts = many(333, () => ({
        x: rnd(w), y: rnd(h), len: 0, r: 0
    }));

    const pts2 = many(9, (i) => ({
        x: cos((i / 9) * PI * 2),
        y: sin((i / 9) * PI * 2)
    }));

    let seed = rnd(100);
    let tx = rnd(w);
    let ty = rnd(h);
    let x = rnd(w);
    let y = rnd(h);
    let kx = rnd(0.5, 0.5);
    let ky = rnd(0.5, 0.5);
    let walkRadius = { x: rnd(50, 50), y: rnd(50, 50) };
    let r = w / rnd(100, 150);

    function drawCircle(cx, cy, cr) {
        ctx.beginPath();
        ctx.ellipse(cx, cy, cr, cr, 0, 0, PI * 2);
        ctx.fill();
    }

    function drawLine(x0, y0, x1, y1) {
        ctx.beginPath();
        ctx.moveTo(x0, y0);
        many(100, (i) => {
            i = (i + 1) / 100;
            let lx = lerp(x0, x1, i);
            let ly = lerp(y0, y1, i);
            let k = noise(lx / 5 + x0, ly / 5 + y0) * 2;
            ctx.lineTo(lx + k, ly + k);
        });
        ctx.stroke();
    }

    function paintPt(pt) {
        pts2.forEach((pt2) => {
            if (!pt.len) return;
            drawLine(
                lerp(x + pt2.x * r, pt.x, pt.len * pt.len),
                lerp(y + pt2.y * r, pt.y, pt.len * pt.len),
                x + pt2.x * r,
                y + pt2.y * r
            );
        });
        drawCircle(pt.x, pt.y, pt.r);
    }

    return {
        follow(fx, fy) { tx = fx; ty = fy; },
        tick(t) {
            const selfMoveX = cos(t * kx + seed) * walkRadius.x;
            const selfMoveY = sin(t * ky + seed) * walkRadius.y;
            let fx = tx + selfMoveX;
            let fy = ty + selfMoveY;

            x += min(w / 100, (fx - x) / 10);
            y += min(w / 100, (fy - y) / 10);

            let i = 0;
            pts.forEach((pt) => {
                const dx = pt.x - x, dy = pt.y - y;
                const len = hypot(dx, dy);
                let pr = min(2, w / len / 5);
                pt.t = 0;
                const increasing = len < w / 10 && (i++) < 8;
                let dir = increasing ? 0.1 : -0.1;
                if (increasing) pr *= 1.5;
                pt.r = pr;
                pt.len = max(0, min(pt.len + dir, 1));
                paintPt(pt);
            });
        }
    };
}

/**
 * @param {HTMLElement} container - Animasyonun ekleneceği element
 * @param {object} config - Spider config (color, spider_count)
 */
export function initSpiderAnimation(container, config) {
    const canvas = document.createElement('canvas');
    canvas.style.cssText = 'position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none;';
    container.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    let w, h;

    const spiderColor = config.color || '#ffffff';
    const spiderCount = min(config.spider_count || 2, 10);

    const spiders = many(spiderCount, () => spawn(ctx, window.innerWidth, window.innerHeight));

    document.addEventListener('pointermove', (e) => {
        const rect = canvas.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;
        spiders.forEach(spider => spider.follow(mouseX, mouseY));
    });

    function anim(t) {
        if (w !== canvas.clientWidth) w = canvas.width = canvas.clientWidth;
        if (h !== canvas.clientHeight) h = canvas.height = canvas.clientHeight;

        ctx.clearRect(0, 0, w, h);
        ctx.fillStyle = ctx.strokeStyle = spiderColor;
        t /= 1000;
        spiders.forEach(spider => spider.tick(t));
        requestAnimationFrame(anim);
    }

    requestAnimationFrame(anim);
}
