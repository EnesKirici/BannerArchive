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
