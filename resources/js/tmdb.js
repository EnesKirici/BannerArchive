/**
 * TMDB Image Modal & Sidebar
 *
 * Bu dosya artik SADECE su islemleri yapar:
 * - Image Modal (gorsel onizleme, tab'lar, indirme — Canvas API gerektigi icin JS'de)
 * - Sidebar toggle & auto-scroll animasyonlari (requestAnimationFrame)
 * - Livewire <-> JS event koprusu (sidebar click → Livewire, Livewire → modal)
 *
 * KALDIRILAN KODLAR (artik Livewire component'lari hallediyor):
 * - Arama (searchFilm, handleSearch) → <livewire:movie-search />
 * - Filtre (setActiveFilter, renderMovies) → <livewire:movie-search />
 * - Grid rendering → <livewire:movie-search />
 * - Batch download queue → kaldirildi (gerekirse eklenebilir)
 * - Quotes modal (openQuotesModal, renderQuotesModal, copyQuote) → <livewire:quote-generator />
 */

const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p/';

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

const IMAGE_SIZES = {
    backdrop: ['w300', 'w780', 'w1280', 'w1920', 'original'],
    poster: ['w92', 'w154', 'w185', 'w342', 'w500', 'w780', 'original'],
    logo: ['w45', 'w92', 'w154', 'w185', 'w300', 'w500', 'original'],
};

// --- IMAGE MODAL ---
export function initTMDB() {
    const modal = document.getElementById('imageModal');

    if (! modal) return;

    // Livewire'dan gelen arama sonuclarini tut (quotes modal'a gecirmek icin)
    let currentResults = [];

    // Modal state — gorsel onizleme icin gereken tum durum
    let modalState = {
        movie: null,
        images: { backdrops: [], posters: [], logos: [] },
        activeTab: 'backdrops',
        selectedImage: null,
        selectedSize: 'original',
        selectedFormat: localStorage.getItem('downloadFormat') || 'webp',
    };

    window.closeModal = closeModal;

    // ═══ GALLERY VIEW MODE HELPER ═══
    function getViewMode() {
        return window.galleryViewMode || 'gallery';
    }

    function navigateToGallery(movie) {
        window.location.href = `/gallery/${movie.raw_type}/${movie.id}`;
    }

    // ═══ LIVEWIRE EVENT KOPRUSU ═══
    // MovieSearch component'i kart tiklandiginda 'open-image-modal' dispatch eder
    // gallery_view_mode ayarina gore modal veya galeri sayfasina yonlendirir
    window.addEventListener('open-image-modal', (e) => {
        const movie = e.detail?.movie || e.detail?.[0]?.movie;

        if (movie) {
            const mode = getViewMode();
            if (mode === 'gallery') {
                navigateToGallery(movie);
            } else {
                openModal(movie);
            }
        }
    });

    // MovieSearch sonuclarini takip et (quotes modal'a gecirmek icin)
    window.addEventListener('results-updated', (e) => {
        currentResults = e.detail?.results || e.detail?.[0]?.results || [];
    });

    // QuoteGenerator "Gorseli Indir" butonundan tetiklenir
    window.addEventListener('download-from-quotes', (e) => {
        const movie = e.detail?.movie || e.detail?.[0]?.movie;

        if (movie && modalState.selectedImage) {
            downloadImage(modalState.selectedImage, modalState.selectedSize, movie.title);
        }
    });

    // ═══ SIDEBAR → LIVEWIRE KOPRUSU ═══
    // Sidebar item'larina tiklandiginda:
    // 1. Livewire MovieSearch component'ina bildirir (sonuclari gunceller)
    // 2. Ayni zamanda image modal'i da acar
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-sidebar-item]');

        if (! trigger) return;

        event.preventDefault();

        const movie = getSidebarItemPayload(trigger);

        // Livewire'a bildir — MovieSearch component'indeki selectFromSidebar() cagrilir
        if (window.Livewire) {
            window.Livewire.dispatch('select-from-sidebar', { movie });
        }

        // gallery_view_mode ayarina gore modal veya galeri sayfasina yonlendir
        const mode = getViewMode();
        if (mode === 'gallery') {
            navigateToGallery(movie);
        } else {
            openModal(movie);
        }

        // Mobilede sidebar'i kapat
        if (window.innerWidth < 768) {
            document.getElementById('sidebar')?.classList.add('-translate-x-full');
        }
    });

    function getSidebarItemPayload(trigger) {
        return {
            id: Number(trigger.dataset.tmdbId),
            title: trigger.dataset.tmdbTitle || '',
            overview: trigger.dataset.tmdbOverview || '',
            poster_path: trigger.dataset.tmdbPosterPath || '',
            backdrop_path: trigger.dataset.tmdbBackdropPath || '',
            vote_average: Number(trigger.dataset.tmdbVoteAverage || 0),
            release_date: trigger.dataset.tmdbReleaseDate || null,
            type: trigger.dataset.tmdbType || '',
            raw_type: trigger.dataset.tmdbRawType || 'movie',
        };
    }

    // ═══ FORMAT HELPERS ═══

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

    function getImageTypeKey() {
        if (modalState.activeTab === 'backdrops') return 'backdrop';
        if (modalState.activeTab === 'posters') return 'poster';
        return 'logo';
    }

    function getAvailableSizes() {
        return IMAGE_SIZES[getImageTypeKey()] || [];
    }

    function getSizeLabel(size) {
        if (size === 'original') return 'Original';
        return size.replace('w', '') + 'px';
    }

    function getImageUrl(filePath, size) {
        const tmdbSize = size === 'w1920' ? 'original' : size;
        return `${TMDB_IMAGE_BASE}${tmdbSize}${filePath}`;
    }

    // ═══ MODAL OPEN / CLOSE ═══

    async function openModal(movie) {
        modalState.movie = movie;
        modalState.activeTab = 'backdrops';
        modalState.selectedSize = 'original';
        modalState.selectedImage = null;

        const modalContent = document.getElementById('modalContent');
        modalContent.innerHTML = `
            <div class="flex items-center justify-center py-20">
                <div class="w-10 h-10 border-4 border-neutral-800 border-t-fuchsia-500 rounded-full animate-spin"></div>
            </div>
        `;

        showModal();

        try {
            const response = await fetch(`/images/${movie.raw_type}/${movie.id}`);
            if (! response.ok) throw new Error('API Hatasi');

            modalState.images = await response.json();

            if (modalState.images.backdrops.length > 0) {
                modalState.selectedImage = modalState.images.backdrops[0];
            } else if (modalState.images.posters.length > 0) {
                modalState.activeTab = 'posters';
                modalState.selectedImage = modalState.images.posters[0];
            } else if (modalState.images.logos.length > 0) {
                modalState.activeTab = 'logos';
                modalState.selectedImage = modalState.images.logos[0];
            }

            renderModal();
        } catch (error) {
            console.error('Resim yukleme hatasi:', error);
            modalContent.innerHTML = `<div class="text-center py-20 text-neutral-500">Resimler yuklenemedi.</div>`;
        }
    }

    function showModal() {
        modal.classList.remove('hidden');
        requestAnimationFrame(() => {
            modal.querySelector('.modal-backdrop').classList.remove('opacity-0');
            document.getElementById('modalContainer').classList.remove('scale-95', 'opacity-0');
            document.getElementById('modalContainer').classList.add('scale-100', 'opacity-100');
        });
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        const modalContainer = document.getElementById('modalContainer');
        modalContainer.classList.remove('scale-100', 'opacity-100');
        modalContainer.classList.add('scale-95', 'opacity-0');
        modal.querySelector('.modal-backdrop').classList.add('opacity-0');

        setTimeout(() => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }, 300);
    }

    // ═══ MODAL RENDER ═══

    function renderModal() {
        const modalContent = document.getElementById('modalContent');
        const images = modalState.images;
        const counts = {
            backdrops: images.backdrops.length,
            posters: images.posters.length,
            logos: images.logos.length,
        };

        const tabs = [
            { key: 'backdrops', label: 'Banner', count: counts.backdrops },
            { key: 'posters', label: 'Poster', count: counts.posters },
            { key: 'logos', label: 'Logo', count: counts.logos },
        ].filter(t => t.count > 0);

        const currentImages = images[modalState.activeTab] || [];
        const sizes = getAvailableSizes();
        const selectedImg = modalState.selectedImage;
        const previewUrl = selectedImg ? getImageUrl(selectedImg.file_path, modalState.selectedSize) : '';
        const thumbSize = modalState.activeTab === 'posters' ? 'w185' : 'w300';

        const formatBtns = ['webp', 'png', 'jpg'].map(f => `
            <button data-format="${f}" class="format-btn px-2.5 py-0.5 rounded text-[11px] font-mono font-bold transition-all cursor-pointer ${modalState.selectedFormat === f ? 'bg-fuchsia-600 text-white' : 'bg-neutral-800 text-neutral-400 hover:bg-neutral-700 border border-white/5'}">
                ${f.toUpperCase()}
            </button>
        `).join('');

        modalContent.innerHTML = `
            <!-- Header -->
            <div class="p-4 md:p-6 border-b border-white/5 flex items-center justify-between shrink-0">
                <div>
                    <h2 class="text-lg md:text-xl font-bold text-white">${escapeHtml(modalState.movie.title)}</h2>
                    <p class="text-xs text-neutral-500 mt-1">${escapeHtml(modalState.movie.type)} &middot; ${modalState.movie.release_date ? escapeHtml(modalState.movie.release_date.split('-')[0]) : ''}</p>
                </div>
                <button onclick="closeModal()" class="p-2 bg-neutral-800 rounded-full hover:bg-fuchsia-600 text-white transition-colors shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Tabs -->
            <div class="px-4 md:px-6 pt-4 flex items-center gap-2 shrink-0">
                ${tabs.map(t => `
                    <button data-tab="${t.key}" class="modal-tab px-4 py-1.5 rounded-full text-xs font-bold transition-all ${modalState.activeTab === t.key ? 'bg-fuchsia-600 text-white' : 'bg-neutral-800 text-neutral-400 hover:bg-neutral-700'}">
                        ${t.label} <span class="ml-1 opacity-60">${t.count}</span>
                    </button>
                `).join('')}
            </div>

            <!-- Resolution Filter -->
            <div class="px-4 md:px-6 pt-3 flex items-center gap-2 flex-wrap shrink-0">
                <span class="text-[10px] uppercase tracking-widest text-neutral-600 font-bold mr-1">Cozunurluk:</span>
                ${sizes.map(s => `
                    <button data-size="${s}" class="size-btn px-3 py-1 rounded-full text-[11px] font-mono font-bold transition-all ${modalState.selectedSize === s ? 'bg-white text-black shadow-[0_0_10px_rgba(255,255,255,0.2)]' : 'bg-neutral-800/80 text-neutral-400 hover:bg-neutral-700 border border-white/5'}">
                        ${getSizeLabel(s)}
                    </button>
                `).join('')}
            </div>

            <!-- Content: Preview + Thumbnails -->
            <div class="flex-1 flex flex-col md:flex-row overflow-hidden min-h-0 p-4 md:p-6 gap-4">
                <!-- Preview -->
                <div class="flex-1 flex flex-col items-center justify-center bg-black/50 rounded-xl overflow-hidden relative min-h-[200px]">
                    ${selectedImg ? `
                        <img id="previewImage" src="${previewUrl}" alt="Preview"
                            class="max-w-full max-h-[55vh] object-contain">
                        <div id="previewDimensions" class="absolute bottom-3 left-3 bg-black/70 backdrop-blur-sm px-3 py-1.5 rounded-lg text-xs font-mono text-fuchsia-400 border border-white/10">
                            ...
                        </div>
                    ` : '<p class="text-neutral-600">Bir resim secin</p>'}
                </div>

                <!-- Thumbnails -->
                <div class="w-full md:w-56 shrink-0 overflow-y-auto scrollbar-hide">
                    <div class="${modalState.activeTab === 'posters' ? 'grid grid-cols-3 md:grid-cols-2 gap-2' : 'flex flex-row md:flex-col gap-2 overflow-x-auto md:overflow-x-visible'}">
                        ${currentImages.map((img, i) => `
                            <div data-thumb-index="${i}"
                                class="thumb-item cursor-pointer rounded-lg overflow-hidden border-2 transition-all shrink-0 ${selectedImg && selectedImg.file_path === img.file_path ? 'border-fuchsia-500 shadow-[0_0_10px_rgba(217,70,239,0.3)]' : 'border-transparent hover:border-white/20'}">
                                <img src="${getImageUrl(img.file_path, thumbSize)}" alt="Thumb ${i + 1}"
                                    class="w-full h-auto object-cover bg-neutral-900" loading="lazy">
                                <div class="bg-neutral-900 px-1.5 py-1 text-[9px] font-mono text-neutral-500 text-center">
                                    ${img.width}x${img.height}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 md:p-6 border-t border-white/5 flex items-center justify-between shrink-0">
                <div class="text-xs text-neutral-400 space-y-2">
                    <div class="flex gap-2"><span class="font-bold text-white">BOYUT:</span> <span id="modalFooterDim">${selectedImg ? selectedImg.width + ' x ' + selectedImg.height : '...'}</span></div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-white">FORMAT:</span>
                        ${formatBtns}
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button id="openGalleryBtn" class="${getViewMode() === 'both' ? '' : 'hidden'} px-5 py-2.5 bg-neutral-800 text-white font-bold rounded-lg hover:bg-cyan-600 transition-all text-sm flex items-center gap-2 border border-white/10">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        <span>Galeri'de Ac</span>
                    </button>
                    <button id="generateQuotesBtn" class="px-5 py-2.5 bg-linear-to-r from-purple-600 to-fuchsia-600 text-white font-bold rounded-lg hover:from-purple-500 hover:to-fuchsia-500 transition-all text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                        <span>AI Sozleri</span>
                    </button>
                    <button id="downloadBtn" class="px-6 py-2.5 bg-white text-black font-bold rounded-lg hover:bg-fuchsia-500 hover:text-white transition-colors text-sm flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        <span>${modalState.selectedFormat.toUpperCase()} Indir</span>
                    </button>
                </div>
            </div>
        `;

        // Preview image load → show real dimensions
        const previewImg = document.getElementById('previewImage');
        if (previewImg) {
            previewImg.onload = function () {
                const dimEl = document.getElementById('previewDimensions');
                if (dimEl) dimEl.textContent = `${this.naturalWidth} x ${this.naturalHeight}`;
            };
        }

        // Tab events
        modalContent.querySelectorAll('.modal-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                modalState.activeTab = btn.dataset.tab;
                const imgs = modalState.images[modalState.activeTab] || [];
                modalState.selectedImage = imgs.length > 0 ? imgs[0] : null;
                modalState.selectedSize = 'original';
                renderModal();
            });
        });

        // Size events
        modalContent.querySelectorAll('.size-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                modalState.selectedSize = btn.dataset.size;
                renderModal();
            });
        });

        // Thumbnail events
        modalContent.querySelectorAll('.thumb-item').forEach(el => {
            el.addEventListener('click', () => {
                const idx = parseInt(el.dataset.thumbIndex);
                const imgs = modalState.images[modalState.activeTab] || [];
                modalState.selectedImage = imgs[idx];
                renderModal();
            });
        });

        // Format events
        modalContent.querySelectorAll('.format-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                modalState.selectedFormat = btn.dataset.format;
                localStorage.setItem('downloadFormat', modalState.selectedFormat);
                renderModal();
            });
        });

        // Download button
        const downloadBtn = document.getElementById('downloadBtn');
        if (downloadBtn && selectedImg) {
            downloadBtn.addEventListener('click', () => {
                downloadImage(selectedImg, modalState.selectedSize, modalState.movie.title);
            });
        }

        // ═══ AI Sozleri butonu → Livewire QuoteGenerator'a event gonder ═══
        // Eski JS: openQuotesModal(movie, bannerImage, opts)
        // Yeni: Livewire.dispatch('open-quotes', { ... })
        const generateQuotesBtn = document.getElementById('generateQuotesBtn');
        if (generateQuotesBtn) {
            generateQuotesBtn.addEventListener('click', () => {
                const bannerUrl = selectedImg
                    ? getImageUrl(selectedImg.file_path, 'w780')
                    : '';

                if (window.Livewire) {
                    window.Livewire.dispatch('open-quotes', {
                        movie: modalState.movie,
                        bannerUrl: bannerUrl,
                        results: currentResults,
                    });
                }
            });
        }

        // ═══ Galeri'de Ac butonu → Gallery sayfasina yonlendir ═══
        const openGalleryBtn = document.getElementById('openGalleryBtn');
        if (openGalleryBtn) {
            openGalleryBtn.addEventListener('click', () => {
                navigateToGallery(modalState.movie);
            });
        }
    }

    // ═══ DOWNLOAD (Canvas API — browser-only) ═══

    async function downloadImage(imageData, size, title) {
        const downloadBtn = document.getElementById('downloadBtn');
        const originalHTML = downloadBtn.innerHTML;
        downloadBtn.disabled = true;
        downloadBtn.innerHTML = `
            <div class="w-4 h-4 border-2 border-neutral-400 border-t-black rounded-full animate-spin"></div>
            <span>Indiriliyor...</span>
        `;

        try {
            const tmdbSize = size === 'w1920' ? 'original' : size;
            const proxyUrl = `/proxy-image?path=${encodeURIComponent(imageData.file_path)}&size=${tmdbSize}`;
            const response = await fetch(proxyUrl);
            if (! response.ok) throw new Error('Gorsel alinamadi');
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

            const { mimeType, quality, ext } = getFormatInfo(modalState.selectedFormat);
            const outputBlob = await new Promise(resolve => canvas.toBlob(resolve, mimeType, quality));

            const url = URL.createObjectURL(outputBlob);
            const a = document.createElement('a');
            const safeName = title.replace(/[^a-zA-Z0-9\u00C0-\u024F\u0400-\u04FF\u0600-\u06FF\u4E00-\u9FFF ]/g, '').replace(/\s+/g, '_');
            a.href = url;
            a.download = `${safeName}_${size}.${ext}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        } catch (error) {
            console.error('Indirme hatasi:', error);
            alert('Indirme sirasinda bir hata olustu.');
        } finally {
            downloadBtn.disabled = false;
            downloadBtn.innerHTML = originalHTML;
        }
    }

    // ═══ MODAL EVENTS ═══

    modal.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal-backdrop')) closeModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && ! modal.classList.contains('hidden')) {
            closeModal();
        }
    });
}

// --- SIDEBAR TOGGLE ---
export function initSidebarToggle() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');

    if (! sidebar || ! toggleBtn) return;

    const container = sidebar.parentElement;

    toggleBtn.addEventListener('click', () => {
        const isCollapsed = container.dataset.sidebarCollapsed === 'true';
        const newState = ! isCollapsed;

        container.dataset.sidebarCollapsed = newState;
        document.cookie = 'sidebar_collapsed=' + newState + ';path=/;max-age=31536000;SameSite=Lax';
    });
}

// --- SIDEBAR AUTO-SCROLL ---
export function initSidebarSlider() {
    const containers = document.querySelectorAll('.sidebar-slider');

    containers.forEach(container => {
        const list = container.querySelector('.slider-track');
        if (! list || list.children.length === 0) return;

        // Clone items for seamless loop
        const items = Array.from(list.children);
        items.forEach(item => {
            const clone = item.cloneNode(true);
            list.appendChild(clone);
        });

        let speed = 0.5;
        let isPaused = false;
        let animationId;

        // Start from halfway (the cloned set) and scroll upward toward 0
        const halfHeight = list.scrollHeight / 2;
        let scrollPos = halfHeight;
        container.scrollTop = scrollPos;

        function animate() {
            if (! isPaused) {
                scrollPos -= speed;
                // Reset when we reach the top — jump back to the clone boundary
                if (scrollPos <= 0) {
                    scrollPos = halfHeight;
                }
                container.scrollTop = scrollPos;
            }
            animationId = requestAnimationFrame(animate);
        }

        container.addEventListener('mouseenter', () => { isPaused = true; });
        container.addEventListener('mouseleave', () => { isPaused = false; });
        container.addEventListener('touchstart', () => { isPaused = true; }, { passive: true });
        container.addEventListener('touchend', () => { isPaused = false; });

        animate();
    });
}
