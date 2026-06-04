/**
 * lazy-videos.js — Massango Platform
 * ─────────────────────────────────────────────────────────────────────────────
 * Lazy load universal para elementos <video> com data-src.
 *
 * USO NO PHP:
 *   Em vez de:  <video src="<?= $url ?>">
 *   Usar:       <video data-src="<?= $url ?>" class="lazy-video">
 *
 * INCLUSÃO:
 *   <script src="<?= BASE_URL ?>assets/js/lazy-videos.js" defer></script>
 *
 * API PÚBLICA:
 *   window.LazyVideos.load(videoElement)  → Promise<HTMLVideoElement>
 *   window.LazyVideos.observe(videoElement) → regista no observer
 * ─────────────────────────────────────────────────────────────────────────────
 */

(function (global) {
    'use strict';

    /** Distância antes do viewport em que o carregamento começa */
    const ROOT_MARGIN = '200px';

    /**
     * Injeta o src real no vídeo e sinaliza como carregado.
     * @param {HTMLVideoElement} video
     * @returns {Promise<HTMLVideoElement>}
     */
    function loadVideo(video) {
        return new Promise((resolve) => {
            // Já carregado — resolve imediatamente
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

            // Resolve quando os metadados chegam (duração, dimensões disponíveis)
            video.addEventListener('loadedmetadata', () => resolve(video), { once: true });

            // Fallback: resolve mesmo que loadedmetadata demore (ex: resposta em cache)
            setTimeout(() => resolve(video), 800);
        });
    }

    /**
     * IntersectionObserver partilhado — carrega o vídeo quando o elemento
     * entra na área rootMargin e para de observar depois.
     */
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            loadVideo(entry.target);
            observer.unobserve(entry.target);
        });
    }, {
        rootMargin: ROOT_MARGIN,
        threshold: 0,
    });

    /**
     * Regista um elemento <video> no observer lazy.
     * @param {HTMLVideoElement} video
     */
    function observe(video) {
        if (!(video instanceof HTMLVideoElement)) return;
        if (video.dataset.loaded === 'true') return;
        observer.observe(video);
    }

    /**
     * Inicializa todos os vídeos .lazy-video presentes no documento.
     * Chamado automaticamente no DOMContentLoaded.
     */
    function init() {
        document.querySelectorAll('video.lazy-video').forEach(observe);
    }

    // ── Auto-init ──────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM já carregado (script sem defer ou inserido dinamicamente)
        init();
    }

    // ── API pública ────────────────────────────────────────────────────────
    global.LazyVideos = { load: loadVideo, observe };

})(window);
