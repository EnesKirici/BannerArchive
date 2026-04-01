/**
 * Gallery Page - Vanilla JS
 *
 * Tam sayfa gorsel galerisi. Alpine.js bagimliligini yok.
 * - Tab'lar (backdrops, posters, logos)
 * - Cozunurluk & format secimi
 * - Lightbox preview (ok tuslari ile gezinme)
 * - Tekli & toplu indirme (Canvas API)
 * - Checkbox ile gorsel secimi
 * - tsParticles arka plan
 */

import { tsParticles } from "@tsparticles/engine";
import { loadSlim } from "@tsparticles/slim";
import JSZip from "jszip";

const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p/';
const LAZY_BATCH_SIZE = 20;

const IMAGE_SIZES = {
    backdrop: ['w300', 'w780', 'w1280', 'w1920', 'original'],
    poster: ['w92', 'w154', 'w185', 'w342', 'w500', 'w780', 'original'],
    logo: ['w45', 'w92', 'w154', 'w185', 'w300', 'w500', 'original'],
};

function getTypeKey(tab) {
    if (tab === 'backdrops') return 'backdrop';
    if (tab === 'posters') return 'poster';
    return 'logo';
}

function getFormatInfo(format) {
    switch (format) {
        case 'png':
            return { mimeType: 'image/png', quality: undefined, ext: 'png' };
        case 'jpg':
            return { mimeType: 'image/jpeg', quality: 0.92, ext: 'jpg' };
        default:
            return { mimeType: 'image/webp', quality: 0.92, ext: 'webp' };
    }
}

function getSizeLabel(size) {
    return size === 'original' ? 'Original' : size.replace('w', '') + 'px';
}

// ═══ STATE ═══
let state = {
    activeTab: 'backdrops',
    selectedSize: 'original',
    selectedFormat: localStorage.getItem('downloadFormat') || 'webp',
    selectedItems: new Set(), // file_path'leri tutar
    lightboxIndex: -1,
};

// ═══ DOM REFERENCES ═══
let els = {};

function initGallery() {
    const images = window.GALLERY_IMAGES;
    if (! images) return;

    // Cache DOM references
    els = {
        tabs: document.querySelectorAll('.gallery-tab'),
        grids: document.querySelectorAll('.gallery-grid'),
        items: document.querySelectorAll('.gallery-item'),
        sizeButtons: document.getElementById('sizeButtons'),
        controlsBar: document.getElementById('controlsBar'),
        formatBtns: document.querySelectorAll('.format-btn'),
        formatLabels: document.querySelectorAll('.format-label'),
        selectAllBtn: document.getElementById('selectAllBtn'),
        downloadSelectedBtn: document.getElementById('downloadSelectedBtn'),
        downloadSelectedCount: document.getElementById('downloadSelectedCount'),
        lightbox: document.getElementById('lightbox'),
        lightboxImage: document.getElementById('lightboxImage'),
        lightboxDims: document.getElementById('lightboxDims'),
        lightboxCounter: document.getElementById('lightboxCounter'),
        lightboxDownload: document.getElementById('lightboxDownload'),
        lightboxDownloadLabel: document.getElementById('lightboxDownloadLabel'),
        lightboxClose: document.getElementById('lightboxClose'),
        lightboxPrev: document.getElementById('lightboxPrev'),
        lightboxNext: document.getElementById('lightboxNext'),
        lightboxBackdrop: document.getElementById('lightboxBackdrop'),
        lightboxSpinner: document.getElementById('lightboxSpinner'),
        downloadProgress: document.getElementById('downloadProgress'),
        downloadProgressText: document.getElementById('downloadProgressText'),
        downloadProgressBar: document.getElementById('downloadProgressBar'),
    };

    // ═══ OVERVIEW TOGGLE ═══
    const overviewToggle = document.getElementById('overviewToggle');
    if (overviewToggle) {
        const overviewText = document.getElementById('overviewText');
        const overviewArrow = document.getElementById('overviewArrow');
        const overviewLabel = document.getElementById('overviewLabel');
        let expanded = false;

        if (overviewText.scrollHeight > overviewText.clientHeight) {
            overviewToggle.classList.remove('hidden');
            overviewToggle.classList.add('flex');
        }

        overviewToggle.addEventListener('click', () => {
            expanded = !expanded;
            overviewText.classList.toggle('line-clamp-2', !expanded);
            overviewArrow.classList.toggle('rotate-180', expanded);
            overviewLabel.textContent = expanded ? 'Daralt' : 'Devamını Oku';
        });
    }

    // Detect first tab
    const firstTab = document.querySelector('.gallery-tab');
    if (firstTab) {
        state.activeTab = firstTab.dataset.tab;
    }

    // Init size buttons for active tab
    renderSizeButtons();

    // Init format from localStorage
    updateFormatUI();

    // ═══ TAB EVENTS ═══
    els.tabs.forEach(btn => {
        btn.addEventListener('click', () => {
            state.activeTab = btn.dataset.tab;
            state.selectedItems.clear();
            updateTabUI();
            renderSizeButtons();
            updateSelectionUI();
        });
    });

    // ═══ FORMAT EVENTS ═══
    els.formatBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            state.selectedFormat = btn.dataset.format;
            localStorage.setItem('downloadFormat', state.selectedFormat);
            updateFormatUI();
        });
    });

    // ═══ GRID ITEM EVENTS ═══
    els.items.forEach(item => bindItemEvents(item));

    // ═══ SELECT ALL ═══
    els.selectAllBtn.addEventListener('click', () => {
        const currentItems = getCurrentTabItems();
        const allSelected = currentItems.length > 0 && currentItems.every(it => state.selectedItems.has(it.dataset.filePath));

        if (allSelected) {
            // Deselect all
            currentItems.forEach(it => {
                state.selectedItems.delete(it.dataset.filePath);
                const cb = it.querySelector('.gallery-check-input');
                if (cb) cb.checked = false;
                updateItemCheckUI(it, false);
            });
        } else {
            // Select all
            currentItems.forEach(it => {
                state.selectedItems.add(it.dataset.filePath);
                const cb = it.querySelector('.gallery-check-input');
                if (cb) cb.checked = true;
                updateItemCheckUI(it, true);
            });
        }
        updateSelectionUI();
    });

    // ═══ DOWNLOAD SELECTED ═══
    els.downloadSelectedBtn.addEventListener('click', () => {
        downloadSelected();
    });

    // ═══ LIGHTBOX EVENTS ═══
    els.lightboxClose.addEventListener('click', closeLightbox);
    els.lightboxPrev.addEventListener('click', () => navigateLightbox(-1));
    els.lightboxNext.addEventListener('click', () => navigateLightbox(1));
    els.lightboxDownload.addEventListener('click', () => {
        const imgData = getCurrentTabImages()[state.lightboxIndex];
        if (imgData) {
            downloadSingle(imgData.file_path, state.selectedSize);
        }
    });

    // Lightbox backdrop click
    els.lightboxBackdrop.addEventListener('click', (e) => {
        if (e.target === els.lightboxBackdrop) closeLightbox();
    });

    // Keyboard
    document.addEventListener('keydown', (e) => {
        if (els.lightbox.classList.contains('hidden')) return;

        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') navigateLightbox(-1);
        if (e.key === 'ArrowRight') navigateLightbox(1);
    });

    // Lightbox image load → show dimensions
    els.lightboxImage.addEventListener('load', () => {
        els.lightboxDims.textContent = `${els.lightboxImage.naturalWidth} x ${els.lightboxImage.naturalHeight}`;
    });

    // ═══ ACTOR MODAL EVENTS ═══
    initActorModal();

    // ═══ VIDEO MODAL EVENTS ═══
    initVideoModal();
}

// ═══ UI UPDATE FUNCTIONS ═══

function updateTabUI() {
    els.tabs.forEach(btn => {
        const isActive = btn.dataset.tab === state.activeTab;
        btn.className = `gallery-tab px-5 py-2 rounded-full text-sm font-bold transition-all ${isActive ? 'bg-fuchsia-600 text-white' : 'bg-neutral-800 text-neutral-400 hover:bg-neutral-700'}`;
    });

    els.grids.forEach(grid => {
        const gridTab = grid.id.replace('grid-', '');
        grid.classList.toggle('hidden', gridTab !== state.activeTab);
    });

    // Cast/Videos tab'ında indirme kontrolleri gizlenir
    if (els.controlsBar) {
        els.controlsBar.classList.toggle('hidden', state.activeTab === 'cast' || state.activeTab === 'videos');
    }
}

function renderSizeButtons() {
    const sizes = IMAGE_SIZES[getTypeKey(state.activeTab)] || [];
    const container = els.sizeButtons;

    // Keep the label span
    container.innerHTML = '<span class="text-[10px] uppercase tracking-widest text-neutral-500 font-bold">Çözünürlük:</span>';

    sizes.forEach(size => {
        const btn = document.createElement('button');
        btn.dataset.size = size;
        btn.className = `size-btn px-3 py-1 rounded-full text-[11px] font-mono font-bold transition-all ${state.selectedSize === size ? 'bg-white text-black shadow-[0_0_10px_rgba(255,255,255,0.2)]' : 'bg-neutral-800/80 text-neutral-400 hover:bg-neutral-700 border border-white/5'}`;
        btn.textContent = getSizeLabel(size);
        btn.addEventListener('click', () => {
            state.selectedSize = size;
            renderSizeButtons();
        });
        container.appendChild(btn);
    });
}

function updateFormatUI() {
    els.formatBtns.forEach(btn => {
        const isActive = btn.dataset.format === state.selectedFormat;
        btn.className = `format-btn px-2.5 py-1 rounded text-[11px] font-mono font-bold transition-all uppercase ${isActive ? 'bg-fuchsia-600 text-white' : 'bg-neutral-800 text-neutral-400 hover:bg-neutral-700 border border-white/5'}`;
    });

    // Update all format labels in grid items (re-query for lazy-loaded items)
    document.querySelectorAll('.format-label').forEach(lbl => {
        lbl.textContent = state.selectedFormat.toUpperCase();
    });

    // Update lightbox download label
    if (els.lightboxDownloadLabel) {
        els.lightboxDownloadLabel.textContent = state.selectedFormat.toUpperCase() + ' İndir';
    }
}

function updateItemCheckUI(item, checked) {
    const label = item.querySelector('.gallery-checkbox');
    const icon = item.querySelector('.check-icon');

    if (checked) {
        item.classList.remove('border-transparent', 'hover:border-white/20');
        item.classList.add('border-fuchsia-500', 'shadow-[0_0_15px_rgba(217,70,239,0.3)]');
        if (label) {
            label.classList.remove('bg-black/50', 'border-white/20');
            label.classList.add('bg-fuchsia-600');
        }
        if (icon) icon.classList.remove('hidden');
    } else {
        item.classList.remove('border-fuchsia-500', 'shadow-[0_0_15px_rgba(217,70,239,0.3)]');
        item.classList.add('border-transparent', 'hover:border-white/20');
        if (label) {
            label.classList.add('bg-black/50', 'border-white/20');
            label.classList.remove('bg-fuchsia-600');
        }
        if (icon) icon.classList.add('hidden');
    }
}

function updateSelectionUI() {
    const count = state.selectedItems.size;
    const currentItems = getCurrentTabItems();
    const allSelected = currentItems.length > 0 && currentItems.every(it => state.selectedItems.has(it.dataset.filePath));

    els.selectAllBtn.textContent = allSelected ? 'Seçimi Kaldır' : 'Tümünü Seç';

    if (count > 0) {
        els.downloadSelectedBtn.classList.remove('hidden');
        els.downloadSelectedBtn.classList.add('flex');
        els.downloadSelectedCount.textContent = count + ' Görsel İndir';
    } else {
        els.downloadSelectedBtn.classList.add('hidden');
        els.downloadSelectedBtn.classList.remove('flex');
    }
}

// ═══ HELPERS ═══

function getCurrentTabItems() {
    return Array.from(els.items).filter(it => it.dataset.tab === state.activeTab);
}

function getCurrentTabImages() {
    return window.GALLERY_IMAGES?.[state.activeTab] || [];
}

// ═══ ITEM EVENT BINDING ═══

function bindItemEvents(item) {
    if (item.dataset.bound) return;
    item.dataset.bound = '1';

    item.addEventListener('click', (e) => {
        if (e.target.closest('.gallery-checkbox') || e.target.closest('.download-single-btn')) return;
        const tab = item.dataset.tab;
        const index = parseInt(item.dataset.index);
        if (tab !== state.activeTab) return;

        if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            const fp = item.dataset.filePath;
            const checkbox = item.querySelector('.gallery-check-input');
            const isSelected = state.selectedItems.has(fp);
            if (isSelected) {
                state.selectedItems.delete(fp);
                if (checkbox) checkbox.checked = false;
                updateItemCheckUI(item, false);
            } else {
                state.selectedItems.add(fp);
                if (checkbox) checkbox.checked = true;
                updateItemCheckUI(item, true);
            }
            updateSelectionUI();
            return;
        }

        openLightbox(index);
    });

    const checkbox = item.querySelector('.gallery-check-input');
    if (checkbox) {
        checkbox.addEventListener('change', () => {
            const fp = item.dataset.filePath;
            if (checkbox.checked) {
                state.selectedItems.add(fp);
            } else {
                state.selectedItems.delete(fp);
            }
            updateItemCheckUI(item, checkbox.checked);
            updateSelectionUI();
        });
    }

    const dlBtn = item.querySelector('.download-single-btn');
    if (dlBtn) {
        dlBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            downloadSingle(item.dataset.filePath, state.selectedSize);
        });
    }
}

// ═══ LAZY LOADING ═══

function createGalleryItem(img, index, tab) {
    const thumbSize = tab === 'posters' ? 'w342' : 'w780';
    const aspectClass = tab === 'posters' ? 'aspect-2/3' : 'aspect-video';

    const div = document.createElement('div');
    div.className = 'gallery-item group relative bg-neutral-900 rounded-xl overflow-hidden border-2 border-transparent hover:border-white/20 transition-all cursor-pointer';
    div.dataset.tab = tab;
    div.dataset.index = String(index);
    div.dataset.filePath = img.file_path;
    div.dataset.width = String(img.width);
    div.dataset.height = String(img.height);

    div.innerHTML = `
        <div class="absolute top-2 left-2 z-10">
            <label class="gallery-checkbox flex items-center justify-center w-6 h-6 rounded-md transition-all cursor-pointer bg-black/50 backdrop-blur-sm border border-white/20 hover:border-fuchsia-500">
                <input type="checkbox" class="hidden gallery-check-input">
                <svg class="w-4 h-4 text-white hidden check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                </svg>
            </label>
        </div>
        <div class="${aspectClass}">
            <img src="${TMDB_IMAGE_BASE}${thumbSize}${img.file_path}"
                 alt="Görsel ${index + 1}"
                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                 loading="lazy">
        </div>
        <div class="flex items-center justify-between px-3 py-2 bg-neutral-900">
            <span class="text-[10px] font-mono text-neutral-500">${img.width}x${img.height}</span>
            <button class="download-single-btn flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold bg-neutral-800 text-neutral-400 hover:bg-fuchsia-600 hover:text-white transition-all">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span class="format-label">${state.selectedFormat.toUpperCase()}</span>
            </button>
        </div>`;

    return div;
}

function initLazyLoading() {
    const sentinels = document.querySelectorAll('.lazy-load-sentinel');
    if (!sentinels.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;

            const sentinel = entry.target;
            const tab = sentinel.dataset.tab;
            const loaded = parseInt(sentinel.dataset.loaded) || 0;
            const allImages = window.GALLERY_IMAGES?.[tab] || [];

            if (loaded >= allImages.length) {
                observer.unobserve(sentinel);
                sentinel.remove();
                return;
            }

            const grid = sentinel.previousElementSibling;
            const nextBatch = allImages.slice(loaded, loaded + LAZY_BATCH_SIZE);

            nextBatch.forEach((img, i) => {
                const item = createGalleryItem(img, loaded + i, tab);
                grid.appendChild(item);
                bindItemEvents(item);
            });

            sentinel.dataset.loaded = String(loaded + nextBatch.length);
            els.items = document.querySelectorAll('.gallery-item');

            if (loaded + nextBatch.length >= allImages.length) {
                observer.unobserve(sentinel);
                sentinel.remove();
            }
        });
    }, {
        rootMargin: '200px'
    });

    sentinels.forEach(sentinel => observer.observe(sentinel));
}

// ═══ LIGHTBOX ═══

function openLightbox(index) {
    const images = getCurrentTabImages();
    if (index < 0 || index >= images.length) return;

    state.lightboxIndex = index;
    const img = images[index];

    const tmdbSize = state.selectedSize === 'w1920' ? 'original' : state.selectedSize;
    const fullUrl = `${TMDB_IMAGE_BASE}${tmdbSize}${img.file_path}`;

    // Görseli gizle, spinner göster
    els.lightboxImage.classList.add('hidden');
    els.lightboxSpinner.classList.remove('hidden');
    els.lightboxImage.src = '';
    els.lightboxDims.textContent = '...';
    els.lightboxCounter.textContent = `${index + 1} / ${images.length}`;

    els.lightbox.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    // Görseli yükle
    const preloader = new Image();
    preloader.onload = () => {
        if (state.lightboxIndex === index) {
            els.lightboxImage.src = fullUrl;
            els.lightboxImage.classList.remove('hidden');
            els.lightboxSpinner.classList.add('hidden');
            // Komşu görselleri preload et
            preloadNeighbors(index);
        }
    };
    preloader.src = fullUrl;
}

function preloadNeighbors(currentIndex) {
    const images = getCurrentTabImages();
    const tmdbSize = state.selectedSize === 'w1920' ? 'original' : state.selectedSize;

    [currentIndex - 1, currentIndex + 1].forEach(i => {
        if (i >= 0 && i < images.length) {
            const pre = new Image();
            pre.src = `${TMDB_IMAGE_BASE}${tmdbSize}${images[i].file_path}`;
        }
    });
}

function closeLightbox() {
    els.lightbox.classList.add('hidden');
    document.body.style.overflow = '';
    state.lightboxIndex = -1;
}

function navigateLightbox(direction) {
    const images = getCurrentTabImages();
    const newIndex = state.lightboxIndex + direction;

    if (newIndex >= 0 && newIndex < images.length) {
        openLightbox(newIndex);
    }
}

// ═══ DOWNLOAD ═══

async function downloadSingle(filePath, size) {
    const title = window.GALLERY_MOVIE?.title || 'image';
    const images = getCurrentTabImages();
    const imgData = images.find(i => i.file_path === filePath);

    if (! imgData) return;

    await _downloadImage(imgData, size, title);
}

async function downloadSelected() {
    if (state.selectedItems.size === 0) return;

    const title = window.GALLERY_MOVIE?.title || 'image';
    const safeName = title.replace(/[^a-zA-Z0-9\u00C0-\u024F\u0400-\u04FF\u0600-\u06FF\u4E00-\u9FFF ]/g, '').replace(/\s+/g, '_');
    const images = getCurrentTabImages();
    const toDownload = images.filter(img => state.selectedItems.has(img.file_path));
    const total = toDownload.length;
    const useZip = total >= 5;

    let progress = 0;

    // Show progress
    els.downloadProgress.classList.remove('hidden');
    els.downloadProgressText.textContent = useZip ? `ZIP hazırlanıyor... 0/${total}` : `İndiriliyor... 0/${total}`;
    els.downloadProgressBar.style.width = '0%';

    if (useZip) {
        const zip = new JSZip();
        const { ext } = getFormatInfo(state.selectedFormat);

        for (const img of toDownload) {
            try {
                const blob = await _convertImage(img, state.selectedSize);
                if (blob) {
                    const fileName = `${safeName}_${img.width}x${img.height}_${state.selectedSize}.${ext}`;
                    zip.file(fileName, blob);
                }
            } catch (error) {
                console.error('Görsel işleme hatası:', error);
            }
            progress++;
            els.downloadProgressText.textContent = `ZIP hazırlanıyor... ${progress}/${total}`;
            els.downloadProgressBar.style.width = `${(progress / total * 100)}%`;
        }

        els.downloadProgressText.textContent = 'ZIP oluşturuluyor...';

        const zipBlob = await zip.generateAsync({ type: 'blob' }, (metadata) => {
            els.downloadProgressBar.style.width = `${metadata.percent.toFixed(0)}%`;
        });

        _triggerDownload(zipBlob, `${safeName}_${total}_gorsel.zip`);
    } else {
        for (const img of toDownload) {
            await _downloadImage(img, state.selectedSize, title);
            progress++;
            els.downloadProgressText.textContent = `İndiriliyor... ${progress}/${total}`;
            els.downloadProgressBar.style.width = `${(progress / total * 100)}%`;
        }
    }

    // Hide after delay
    setTimeout(() => {
        els.downloadProgress.classList.add('hidden');

        // Clear selection
        state.selectedItems.clear();
        getCurrentTabItems().forEach(item => {
            const cb = item.querySelector('.gallery-check-input');
            if (cb) cb.checked = false;
            updateItemCheckUI(item, false);
        });
        updateSelectionUI();
    }, 1000);
}

/**
 * Görseli fetch edip Canvas ile istenen formata çevirir, Blob döner.
 */
async function _convertImage(imageData, size) {
    const tmdbSize = size === 'w1920' ? 'original' : size;
    const proxyUrl = `/proxy-image?path=${encodeURIComponent(imageData.file_path)}&size=${tmdbSize}`;
    const response = await fetch(proxyUrl);

    if (! response.ok) throw new Error('Görsel alınamadı');

    const blob = await response.blob();
    const img = new Image();

    await new Promise((resolve, reject) => {
        img.onload = resolve;
        img.onerror = reject;
        img.src = URL.createObjectURL(blob);
    });

    const canvas = document.createElement('canvas');

    if (size === 'w1920') {
        const ratio = img.naturalHeight / img.naturalWidth;
        canvas.width = 1920;
        canvas.height = Math.round(1920 * ratio);
    } else {
        canvas.width = img.naturalWidth;
        canvas.height = img.naturalHeight;
    }

    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    URL.revokeObjectURL(img.src);

    const { mimeType, quality } = getFormatInfo(state.selectedFormat);
    return new Promise(resolve => canvas.toBlob(resolve, mimeType, quality));
}

async function _downloadImage(imageData, size, title) {
    try {
        const outputBlob = await _convertImage(imageData, size);
        if (! outputBlob) return;

        const { ext } = getFormatInfo(state.selectedFormat);
        const safeName = title.replace(/[^a-zA-Z0-9\u00C0-\u024F\u0400-\u04FF\u0600-\u06FF\u4E00-\u9FFF ]/g, '').replace(/\s+/g, '_');
        _triggerDownload(outputBlob, `${safeName}_${imageData.width}x${imageData.height}_${size}.${ext}`);
    } catch (error) {
        console.error('İndirme hatası:', error);
    }
}

function _triggerDownload(blob, fileName) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// ═══ PARTICLES ═══

const defaultParticlesConfig = {
    fpsLimit: 60,
    particles: {
        number: { value: 40, density: { enable: true, width: 800, height: 800 } },
        color: { value: ["#d946ef", "#a855f7", "#06b6d4", "#8b5cf6"] },
        shape: { type: ["circle"] },
        opacity: { value: { min: 0.05, max: 0.3 } },
        size: { value: { min: 1, max: 3 } },
        links: {
            enable: true,
            distance: 150,
            color: "#a855f7",
            opacity: 0.1,
            width: 1,
        },
        move: { enable: true, speed: 0.5, direction: "none", outModes: "bounce" }
    },
    interactivity: {
        events: {
            onHover: { enable: true, mode: "grab", parallax: { enable: true, force: 40 } },
        },
        modes: {
            grab: { distance: 200, links: { opacity: 0.5, color: "#d946ef" } },
        }
    },
    detectRetina: true
};

async function initGalleryParticles() {
    const container = document.getElementById('galleryParticles');
    if (!container) return;

    await loadSlim(tsParticles);

    let config = defaultParticlesConfig;

    try {
        const response = await fetch('/api/particles/config');
        if (response.ok) {
            const data = await response.json();
            if (data.config) {
                config = data.config;
            }
        }
    } catch (error) {
        console.warn('Particles config yüklenemedi, varsayılan kullanılıyor:', error);
    }

    config.fullScreen = false;
    config.background = { color: { value: "transparent" } };

    // Container resize'da yıldız sayısı değişmesin (tab geçişlerinde duplicate önlenir)
    if (config.particles?.number?.density) {
        config.particles.number.density.enable = false;
    }

    // Tab geçişlerinde container boyutu değişince particles yeniden çizilmesin
    config.interactivity = config.interactivity || {};
    config.interactivity.events = config.interactivity.events || {};
    config.interactivity.events.resize = { enable: false };

    await tsParticles.load({
        id: "galleryParticles",
        options: config
    });

    // tsParticles v3 bug workaround: canvas.init() runs before actualOptions
    // processes our fullScreen:false, so the canvas gets position:fixed incorrectly.
    // Reset it to fill its container properly.
    const particleCanvas = container.querySelector('canvas');
    if (particleCanvas) {
        particleCanvas.style.cssText = 'width: 100%; height: 100%;';
    }
}

// ═══ ACTOR MODAL ═══

function initActorModal() {
    const modal = document.getElementById('actorModal');
    const backdrop = document.getElementById('actorModalBackdrop');
    const closeBtn = document.getElementById('actorModalClose');
    const loading = document.getElementById('actorModalLoading');
    const content = document.getElementById('actorModalContent');

    if (!modal) return;

    // Cast card click
    document.querySelectorAll('.cast-card').forEach(card => {
        card.addEventListener('click', async () => {
            const personId = card.dataset.personId;
            if (!personId) return;

            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            loading.classList.remove('hidden');
            content.classList.add('hidden');
            content.innerHTML = '';

            try {
                const response = await fetch(`/person/${personId}/credits`);
                if (!response.ok) throw new Error('Veri alınamadı');

                const data = await response.json();
                content.innerHTML = renderActorContent(data);
                loading.classList.add('hidden');
                content.classList.remove('hidden');

                // Filmography items → gallery links
                content.querySelectorAll('.filmography-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const type = item.dataset.rawType;
                        const id = item.dataset.id;
                        if (type && id) {
                            window.location.href = `/gallery/${type}/${id}`;
                        }
                    });
                });
            } catch (error) {
                console.error('Actor modal hatası:', error);
                loading.classList.add('hidden');
                content.classList.remove('hidden');
                content.innerHTML = '<div class="p-8 text-center text-neutral-500">Bilgiler yüklenemedi.</div>';
            }
        });
    });

    // Close
    closeBtn.addEventListener('click', closeActorModal);
    backdrop.addEventListener('click', closeActorModal);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeActorModal();
        }
    });

    function closeActorModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

// ═══ VIDEO MODAL ═══

function initVideoModal() {
    const modal = document.getElementById('videoModal');
    const backdrop = document.getElementById('videoModalBackdrop');
    const closeBtn = document.getElementById('videoModalClose');
    const iframe = document.getElementById('videoModalIframe');
    const title = document.getElementById('videoModalTitle');

    if (!modal) return;

    // Video card clicks
    document.querySelectorAll('.video-card').forEach(card => {
        card.addEventListener('click', () => {
            const key = card.dataset.videoKey;
            const name = card.dataset.videoName;
            if (!key) return;

            iframe.src = `https://www.youtube-nocookie.com/embed/${key}?autoplay=1&rel=0`;
            title.textContent = name || '';
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        });
    });

    function closeVideoModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        iframe.src = '';
    }

    closeBtn.addEventListener('click', closeVideoModal);
    backdrop.addEventListener('click', closeVideoModal);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeVideoModal();
        }
    });
}

function renderActorContent(data) {
    const profileImg = data.profile_path
        ? `<img src="https://image.tmdb.org/t/p/w342${data.profile_path}" alt="${data.name}" class="w-full h-full object-cover">`
        : `<div class="w-full h-full bg-neutral-800 flex items-center justify-center"><svg class="w-16 h-16 text-neutral-700" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg></div>`;

    const meta = [];
    if (data.known_for_department) meta.push(data.known_for_department === 'Acting' ? 'Oyuncu' : data.known_for_department);
    if (data.birthday) meta.push(data.birthday);
    if (data.place_of_birth) meta.push(data.place_of_birth);

    const bio = data.biography
        ? `<p class="text-sm text-neutral-400 line-clamp-4 mt-3">${data.biography}</p>`
        : '';

    const credits = data.credits.map(c => {
        const year = c.release_date ? c.release_date.substring(0, 4) : '';
        const rating = c.vote_average ? c.vote_average.toFixed(1) : '';
        const posterImg = c.poster_path
            ? `<img src="https://image.tmdb.org/t/p/w185${c.poster_path}" alt="${c.title}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" loading="lazy">`
            : `<div class="w-full h-full bg-neutral-800 flex items-center justify-center"><svg class="w-8 h-8 text-neutral-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg></div>`;
        const typeBadge = c.media_type === 'movie' ? 'Film' : 'Dizi';

        return `
            <div class="filmography-item group cursor-pointer bg-neutral-800/50 rounded-xl overflow-hidden border border-white/5 hover:border-fuchsia-500/50 transition-all" data-id="${c.id}" data-raw-type="${c.raw_type}">
                <div class="aspect-2/3">${posterImg}</div>
                <div class="p-2.5">
                    <p class="text-xs font-bold text-white truncate group-hover:text-fuchsia-400 transition-colors">${c.title}</p>
                    <p class="text-[10px] text-neutral-500 truncate mt-0.5">${c.character}</p>
                    <div class="flex items-center gap-2 mt-1.5">
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-fuchsia-600/20 text-fuchsia-400 border border-fuchsia-600/20">${typeBadge}</span>
                        ${year ? `<span class="text-[10px] text-neutral-500">${year}</span>` : ''}
                        ${rating ? `<span class="text-[10px] text-yellow-500">${rating}</span>` : ''}
                    </div>
                </div>
            </div>`;
    }).join('');

    return `
        <div class="p-6">
            <div class="flex flex-col sm:flex-row gap-4 sm:gap-5 mb-6">
                <div class="w-20 h-28 sm:w-28 sm:h-40 rounded-xl overflow-hidden shrink-0 border border-white/10 mx-auto sm:mx-0">${profileImg}</div>
                <div class="flex-1 min-w-0">
                    <h2 class="text-2xl font-black text-white">${data.name}</h2>
                    ${meta.length ? `<p class="text-sm text-neutral-400 mt-1">${meta.join(' &middot; ')}</p>` : ''}
                    ${bio}
                </div>
            </div>
            <div class="border-t border-white/5 pt-5">
                <h3 class="text-xs uppercase tracking-widest text-neutral-500 font-bold mb-4">Filmografi <span class="text-neutral-600">(${data.credits.length})</span></h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2 sm:gap-3">
                    ${credits}
                </div>
            </div>
        </div>`;
}

// ═══ INIT ═══
document.addEventListener('DOMContentLoaded', () => {
    initGallery();
    initLazyLoading();
    initGalleryParticles();
});
