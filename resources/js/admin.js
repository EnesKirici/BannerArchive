// Admin panel JavaScript

document.addEventListener('DOMContentLoaded', () => {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('[class*="bg-emerald"], [class*="bg-red"]');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

/**
 * Particle Preview — Canvas animasyonu
 * Her tema için config'den shape, links, direction okuyarak farklı görsel üretir.
 */
window.particlePreview = function (cfg) {
    return {
        raf: null,
        init() {
            const canvas = this.$refs.canvas;
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const dpr = 2;
            const w = canvas.offsetWidth;
            const h = canvas.offsetHeight;
            canvas.width = w * dpr;
            canvas.height = h * dpr;
            ctx.scale(dpr, dpr);

            const colors = Array.isArray(cfg.colors) ? cfg.colors : [cfg.color];
            const count = cfg.count || 30;
            const shape = cfg.shape || 'circle';
            const sides = cfg.sides || 6;
            const points = cfg.points || 5;
            const links = cfg.links || false;
            const direction = cfg.direction || 'none';
            const speed = cfg.speed || 1;
            const wobble = cfg.wobble || false;

            const particles = Array.from({ length: count }, () => ({
                x: Math.random() * w,
                y: direction === 'bottom' ? Math.random() * h : Math.random() * h,
                r: Math.random() * 2.5 + 1,
                dx: direction === 'bottom'
                    ? (Math.random() - 0.5) * 0.4
                    : (Math.random() - 0.5) * speed * 0.5,
                dy: direction === 'bottom'
                    ? Math.random() * speed * 0.5 + 0.3
                    : (Math.random() - 0.5) * speed * 0.5,
                o: Math.random() * 0.5 + 0.3,
                rot: Math.random() * Math.PI * 2,
                rotSpeed: (Math.random() - 0.5) * 0.02,
                ci: Math.floor(Math.random() * colors.length),
                phase: Math.random() * Math.PI * 2,
            }));

            const drawShape = (x, y, size, type) => {
                ctx.beginPath();
                if (type === 'circle') {
                    ctx.arc(x, y, size, 0, Math.PI * 2);
                } else if (type === 'polygon') {
                    for (let i = 0; i < sides; i++) {
                        const a = (Math.PI * 2 / sides) * i - Math.PI / 2;
                        const px = x + size * Math.cos(a);
                        const py = y + size * Math.sin(a);
                        i === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
                    }
                    ctx.closePath();
                } else if (type === 'star') {
                    for (let i = 0; i < points * 2; i++) {
                        const a = (Math.PI * 2 / (points * 2)) * i - Math.PI / 2;
                        const rad = i % 2 === 0 ? size : size * 0.4;
                        const px = x + rad * Math.cos(a);
                        const py = y + rad * Math.sin(a);
                        i === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
                    }
                    ctx.closePath();
                } else if (type === 'triangle') {
                    for (let i = 0; i < 3; i++) {
                        const a = (Math.PI * 2 / 3) * i - Math.PI / 2;
                        const px = x + size * Math.cos(a);
                        const py = y + size * Math.sin(a);
                        i === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
                    }
                    ctx.closePath();
                }
            };

            let t = 0;
            const draw = () => {
                t++;
                ctx.clearRect(0, 0, w, h);

                particles.forEach(p => {
                    if (wobble && direction === 'bottom') {
                        p.x += Math.sin(t * 0.02 + p.phase) * 0.3;
                    }
                    p.x += p.dx;
                    p.y += p.dy;
                    p.rot += p.rotSpeed;

                    if (direction === 'bottom') {
                        if (p.y > h + 10) { p.y = -10; p.x = Math.random() * w; }
                        if (p.x < -10) p.x = w + 10;
                        if (p.x > w + 10) p.x = -10;
                    } else {
                        if (p.x < 0 || p.x > w) p.dx *= -1;
                        if (p.y < 0 || p.y > h) p.dy *= -1;
                    }

                    const pColor = colors[p.ci];

                    ctx.save();
                    ctx.translate(p.x, p.y);
                    if (shape !== 'circle') ctx.rotate(p.rot);
                    ctx.globalAlpha = p.o;

                    drawShape(0, 0, p.r + 1.5, shape);

                    if (shape === 'circle') {
                        ctx.fillStyle = pColor;
                        ctx.fill();
                    } else {
                        ctx.fillStyle = pColor;
                        ctx.globalAlpha = p.o * 0.35;
                        ctx.fill();
                        ctx.globalAlpha = p.o;
                        ctx.strokeStyle = pColor;
                        ctx.lineWidth = 0.6;
                        ctx.stroke();
                    }

                    ctx.restore();
                });

                if (links) {
                    for (let i = 0; i < particles.length; i++) {
                        const a = particles[i];
                        for (let j = i + 1; j < particles.length; j++) {
                            const b = particles[j];
                            const dist = Math.hypot(a.x - b.x, a.y - b.y);
                            if (dist < 65) {
                                ctx.beginPath();
                                ctx.moveTo(a.x, a.y);
                                ctx.lineTo(b.x, b.y);
                                ctx.strokeStyle = cfg.color;
                                ctx.globalAlpha = (1 - dist / 65) * 0.2;
                                ctx.lineWidth = 0.5;
                                ctx.stroke();
                            }
                        }
                    }
                }

                ctx.globalAlpha = 1;
                this.raf = requestAnimationFrame(draw);
            };

            draw();
        },
        destroy() {
            if (this.raf) cancelAnimationFrame(this.raf);
        }
    };
};

/**
 * Spider Preview — Küçük canvas önizlemesi (admin card)
 */
window.spiderPreview = function (color) {
    return {
        raf: null,
        init() {
            const canvas = this.$refs.spiderCanvas;
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            const w = canvas.offsetWidth;
            const h = canvas.offsetHeight;
            canvas.width = w * 2;
            canvas.height = h * 2;
            ctx.scale(2, 2);

            const { sin, cos, PI, hypot, min, max } = Math;
            const rnd = (x = 1, d = 0) => Math.random() * x + d;
            const lerp = (a, b, t) => a + (b - a) * t;
            const noise = (x, y, t = 101) => {
                return sin(0.3*x+1.4*t+2+2.5*sin(0.4*y-1.3*t+1)) + sin(0.2*y+1.5*t+2.8+2.3*sin(0.5*x-1.2*t+0.5));
            };

            // Mini spider
            const pts = Array.from({length: 80}, () => ({ x: rnd(w), y: rnd(h), len: 0, r: 0 }));
            const legs = Array.from({length: 9}, (_, i) => ({ x: cos(i/9*PI*2), y: sin(i/9*PI*2) }));
            let cx = w/2, cy = h/2, tx = w/2, ty = h/2;
            const seed = rnd(100), kx = rnd(0.5,0.5), ky = rnd(0.5,0.5);
            const wr = { x: rnd(15,15), y: rnd(15,15) };
            const r = w / rnd(30, 40);

            const self = this;
            function draw(t) {
                t /= 1000;
                ctx.clearRect(0, 0, w, h);
                ctx.fillStyle = ctx.strokeStyle = color;

                tx = w/2 + cos(t*0.5)*w*0.25;
                ty = h/2 + sin(t*0.7)*h*0.2;
                const fx = tx + cos(t*kx+seed)*wr.x;
                const fy = ty + sin(t*ky+seed)*wr.y;
                cx += min(w/100, (fx-cx)/10);
                cy += min(w/100, (fy-cy)/10);

                let count = 0;
                pts.forEach(pt => {
                    const dx = pt.x-cx, dy = pt.y-cy;
                    const len = hypot(dx, dy);
                    let pr = min(1.5, w/len/5);
                    const inc = len < w/6 && (count++) < 6;
                    pt.r = inc ? pr*1.5 : pr;
                    pt.len = max(0, min(pt.len + (inc ? 0.1 : -0.1), 1));
                    if (!pt.len) return;
                    legs.forEach(lg => {
                        ctx.beginPath();
                        ctx.moveTo(cx+lg.x*r, cy+lg.y*r);
                        ctx.lineTo(lerp(cx+lg.x*r, pt.x, pt.len*pt.len), lerp(cy+lg.y*r, pt.y, pt.len*pt.len));
                        ctx.stroke();
                    });
                    ctx.beginPath();
                    ctx.arc(pt.x, pt.y, pt.r, 0, PI*2);
                    ctx.fill();
                });

                self.raf = requestAnimationFrame(draw);
            }
            this.raf = requestAnimationFrame(draw);
        },
        destroy() {
            if (this.raf) cancelAnimationFrame(this.raf);
        }
    };
};
