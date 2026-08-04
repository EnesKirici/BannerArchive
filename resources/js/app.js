import './bootstrap';
import './livewire-session';
import { initTMDB, initSidebarToggle, initSidebarSlider } from './tmdb';
import { initParticles } from './particles';
import { initBatAnimation } from './bat-animation';

document.addEventListener('DOMContentLoaded', async () => {
    await initParticles();
    await initBatAnimation();
    initTMDB();
    initSidebarToggle();
    initSidebarSlider();
});