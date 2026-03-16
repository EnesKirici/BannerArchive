import { tsParticles } from "@tsparticles/engine";
import { loadSlim } from "@tsparticles/slim";

// Default fallback config (Links theme)
const defaultConfig = {
    fpsLimit: 60,
    particles: {
        number: { value: 60, density: { enable: true, width: 800, height: 800 } },
        color: { value: ["#d946ef", "#a855f7", "#06b6d4", "#8b5cf6"] },
        shape: { type: ["circle", "triangle"] },
        opacity: { value: { min: 0.1, max: 0.5 } },
        size: { value: { min: 1, max: 4 } },
        links: {
            enable: true,
            distance: 150,
            color: "#a855f7",
            opacity: 0.15,
            width: 1,
            triangles: { enable: true, opacity: 0.03 }
        },
        move: { enable: true, speed: 1, direction: "none", outModes: "bounce" }
    },
    interactivity: {
        events: {
            onHover: { enable: true, mode: ["grab", "bubble"], parallax: { enable: true, force: 60 } },
            onClick: { enable: true, mode: "push" }
        },
        modes: {
            grab: { distance: 200, links: { opacity: 0.8, color: "#d946ef" } },
            bubble: { distance: 250, size: 6, duration: 2, opacity: 0.8 },
            push: { quantity: 4 }
        }
    },
    detectRetina: true
};

export async function initParticles() {
    // Only initialize if the container element exists on this page
    const container = document.getElementById('tsparticles');
    if (!container) return;

    await loadSlim(tsParticles);

    // Try to fetch config from API
    let config = defaultConfig;
    
    try {
        const response = await fetch('/api/particles/config');
        if (response.ok) {
            const data = await response.json();
            if (data.config) {
                config = data.config;
            }
        }
    } catch (error) {
        console.warn('Failed to load particles config, using default:', error);
    }

    // Add common settings
    config.fullScreen = false;
    config.background = { color: { value: "transparent" } };

    await tsParticles.load({
        id: "tsparticles",
        options: config
    });
}

// Hot reload support for development
if (import.meta.hot) {
    import.meta.hot.accept(() => {
        tsParticles.dom().forEach(container => container.destroy());
        initParticles();
    });
}

