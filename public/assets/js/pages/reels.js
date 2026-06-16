document.addEventListener('DOMContentLoaded', function () {


    // --- Filtros, blur e lazy load (extraido de reels.php) ---
    // â”€â”€ Toggle mobile filtros â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    const MOBILE_BP = 768;
    const btnToggle = document.getElementById('btnFiltersToggle');
    const panel = document.getElementById('filtersPanel');
    const badge = document.getElementById('filtersBadge');

    if (btnToggle && panel) {

        function countActiveFilters() {
            let n = 0;
            // Chips activos (exclui "Todos")
            panel.querySelectorAll('.chip.active:not([data-chip="all"])').forEach(() => n++);
            // Sort diferente do padrÃ£o
            const sort = panel.querySelector('select[name="sort"]');
            if (sort && sort.value && sort.value !== 'recent') n++;
            // Qualidade seleccionada
            const quality = panel.querySelector('select[name="quality"]');
            if (quality && quality.value) n++;
            // PreÃ§o preenchido
            panel.querySelectorAll('input[name="price_min"], input[name="price_max"]').forEach(inp => {
                if (inp.value.trim() !== '') n++;
            });
            return n;
        }

        function updateBadge() {
            const n = countActiveFilters();
            if (n > 0) {
                badge.textContent = n;
                badge.classList.add('has-count');
            } else {
                badge.textContent = '';
                badge.classList.remove('has-count');
            }
        }

        function togglePanel(forceOpen) {
            const isOpen = typeof forceOpen === 'boolean' ?
                forceOpen :
                !panel.classList.contains('is-open');
            panel.classList.toggle('is-open', isOpen);
            btnToggle.classList.toggle('is-open', isOpen);
            btnToggle.setAttribute('aria-expanded', String(isOpen));

            // Desktop: mostra/esconde linha secundÃ¡ria
            const secondaryRow = document.querySelector('.filters-row-secondary');
            if (secondaryRow) secondaryRow.classList.toggle('is-open', isOpen);
        }

        btnToggle.addEventListener('click', () => togglePanel());

        window.addEventListener('resize', () => {
            if (window.innerWidth > MOBILE_BP) {
                panel.classList.remove('is-open');
                btnToggle.classList.remove('is-open');
                btnToggle.setAttribute('aria-expanded', 'false');
            }
        });

        panel.addEventListener('change', updateBadge);
        panel.addEventListener('click', e => {
            if (e.target.closest('.chip')) setTimeout(updateBadge, 10);
        });

        // Badge inicial (filtros vindos via GET)
        updateBadge();
    }

    // â”€â”€ ProtecÃ§Ã£o de clique em conteÃºdo com blur â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('.lightbox-trigger');
        if (trigger) {
            const blurWrapper = trigger.querySelector('.video-blur-wrapper[data-blur-active="true"]');
            if (e.target.tagName === 'BUTTON' && e.target.innerText.includes('Ver mesmo assim')) return;
            if (blurWrapper) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
            }
        }
    }, true);

    // â”€â”€ Desbloqueio do blur â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    window.unblurReel = function (button) {
        const blurWrapper = button.closest('.video-blur-wrapper');
        if (!blurWrapper) return;

        const video = blurWrapper.querySelector('video.media-blur');
        if (video) {
            video.classList.remove('media-blur');
            video.style.filter = 'none';
            video.style.transform = 'none';
            video.style.pointerEvents = 'auto';
        }

        const overlay = blurWrapper.querySelector('.media-overlay-msg');
        if (overlay) overlay.style.display = 'none';

        blurWrapper.dataset.blurActive = 'false';

        const trigger = blurWrapper.closest('.lightbox-trigger');
        if (trigger) trigger.dataset.aiUnlocked = 'true';

        if (video && window.reelsLazyLoad) {
            window.reelsLazyLoad.loadVideo(video);
        }
    };

    // â”€â”€ Lazy load + Hover play â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    (function () {
        const LOAD_MARGIN = '200px';

        function loadVideo(video) {
            return new Promise((resolve) => {
                if (video.dataset.loaded === 'true') {
                    resolve(video);
                    return;
                }
                const src = video.dataset.src;
                if (!src) {
                    resolve(video);
                    return;
                }
                video.src = src;
                video.load();
                video.dataset.loaded = 'true';
                video.addEventListener('loadedmetadata', () => resolve(video), {
                    once: true
                });
                setTimeout(() => resolve(video), 800);
            });
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                loadVideo(entry.target);
                observer.unobserve(entry.target);
            });
        }, {
            rootMargin: LOAD_MARGIN,
            threshold: 0
        });

        document.querySelectorAll('video.lazy-video').forEach(v => observer.observe(v));

        document.querySelectorAll('.reel-card').forEach(card => {
            const video = card.querySelector('video.reel-video');
            if (!video) return;

            card.addEventListener('mouseenter', async () => {
                // Permite reprodução mesmo com blur ativo (o vídeo aparece desfocado)
                // O bloqueio de navegação já é tratado no listener de clique
                await loadVideo(video);
                video.play().catch(() => { });
            });

            card.addEventListener('mouseleave', () => {
                video.pause();
                video.currentTime = 0;
            });
        });

        window.reelsLazyLoad = {
            loadVideo
        };
    })();

    // â”€â”€ Controlo dos chips (sale + sensitive) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    window.setChip = function (type) {
        const saleInput = document.getElementById('input_sale');
        const sensitiveInput = document.getElementById('input_sensitive');

        saleInput.value = '';
        sensitiveInput.value = '';

        if (type === 'free') saleInput.value = '0';
        if (type === 'paid') saleInput.value = '1';
        if (type === 'adult') {
            if (!confirm('Este conteÃºdo Ã© destinado a maiores de 18 anos. Confirma que tem 18 ou mais anos?')) return;
            sensitiveInput.value = '1';
        }

        document.getElementById('filterForm').submit();
    };

    // â”€â”€ Submit ao pressionar Enter nos campos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    document.querySelectorAll('.price-range-inputs input').forEach(inp => {
        inp.addEventListener('keydown', e => {
            if (e.key === 'Enter') document.getElementById('filterForm').submit();
        });
    });

    const searchInput = document.querySelector('.filter-search input');
    if (searchInput) {
        searchInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') document.getElementById('filterForm').submit();
        });
    }

});