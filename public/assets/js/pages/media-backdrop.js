/**
 * media-backdrop.js
 * Injeta um fundo com blur da própria thumbnail/imagem
 * em todos os containers de mídia do feed (.post-image-container,
 * .post-video-container, .album-container, .album-cover-link).
 *
 * Incluir após card-modern.css:
 *   <script src="js/media-backdrop.js" defer></script>
 */

(function () {
    'use strict';

    // ─── Normaliza URLs de thumbnail (remove segmentos duplicados) ────────────
    // Evita paths como videos/thumbnails/thumbnails/ quando o atributo
    // data-thumbnail já contém o sub-caminho completo.
    function normalizeThumbnailUrl(url) {
        if (!url) return url;
        return url
            .replace(/videos\/thumbnails\/thumbnails\//g, 'videos/thumbnails/')
            .replace(/albums\/thumbnails\/thumbnails\//g, 'albums/thumbnails/');
    }

    // ─── Desenha uma imagem num canvas e devolve dataURL ─────────────────────
    function imageToDataUrl(src, callback) {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function () {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth || img.width || 320;
                canvas.height = img.naturalHeight || img.height || 180;
                canvas.getContext('2d').drawImage(img, 0, 0);
                callback(canvas.toDataURL('image/jpeg', 0.7));
            } catch (e) {
                // canvas tainted (CORS) — usa a URL directamente como fallback
                callback(src);
            }
        };
        img.onerror = function () { callback(null); };
        img.src = src;
    }

    // ─── Captura frame do <video> via canvas ──────────────────────────────────
    function captureVideoFrame(vid, callback) {
        const doCapture = function () {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = vid.videoWidth || 320;
                canvas.height = vid.videoHeight || 180;
                canvas.getContext('2d').drawImage(vid, 0, 0, canvas.width, canvas.height);
                const dataUrl = canvas.toDataURL('image/jpeg', 0.7);
                if (dataUrl.length > 200) {
                    callback(dataUrl);
                    return;
                }
            } catch (e) { /* CORS tainted — tenta poster directamente */ }
            callback(null);
        };

        if (vid.readyState >= 2) {
            doCapture();
        } else {
            vid.addEventListener('loadeddata', doCapture, { once: true });
            // Força carregamento mínimo se preload="none"
            if (vid.preload === 'none') {
                vid.preload = 'metadata';
                vid.load();
            }
        }
    }

    // ─── Aplica o backdrop com uma URL/dataURL já resolvida ──────────────────
    function applyBackdrop(container, url) {
        if (!url || container.querySelector('.media-backdrop')) return;
        const backdrop = document.createElement('div');
        backdrop.className = 'media-backdrop';
        backdrop.style.backgroundImage = "url('" + url.replace(/'/g, "\\'") + "')";
        container.insertBefore(backdrop, container.firstChild);
    }

    // ─── Processa containers de VÍDEO ────────────────────────────────────────
    //
    // Estratégia para vídeos com media-proxy (URLs com token HMAC):
    //
    //   1. Tenta capturar frame do <video> directamente via canvas.
    //      → Funciona se o vídeo já carregou (readyState >= 2).
    //      → Se não carregou, aguarda o evento loadeddata.
    //
    //   2. Se o canvas falhar (CORS tainted porque o proxy não envia
    //      Access-Control-Allow-Origin com anonymous), usa o poster/data-thumbnail
    //      directamente como URL CSS. Funciona na maior parte dos browsers
    //      quando o servidor usa cookies de sessão (same-origin).
    //
    //   3. Se mesmo assim não houver URL, desiste silenciosamente.
    //
    function processVideoContainer(container) {
        if (container.querySelector('.media-backdrop')) return;

        const vid = container.querySelector('video');

        // Tenta capturar frame do próprio <video> (evita fazer um 2.º pedido HTTP)
        if (vid && (vid.src || vid.querySelector('source'))) {
            captureVideoFrame(vid, function (dataUrl) {
                if (dataUrl) {
                    applyBackdrop(container, dataUrl);
                    return;
                }
                // Canvas falhou — tenta URL estática do poster/data-thumbnail
                fallbackToStaticUrl(container, vid);
            });
            return;
        }

        // Sem elemento <video> com src — vai directo para URL estática
        fallbackToStaticUrl(container, vid || null);
    }

    function fallbackToStaticUrl(container, vid) {
        // Prioridade: data-thumbnail no container > data-thumbnail no video > poster > data-poster
        let url =
            container.dataset.thumbnail ||
            (vid && vid.dataset.thumbnail) ||
            (vid && vid.poster) ||
            (vid && vid.dataset.poster) ||
            null;

        url = normalizeThumbnailUrl(url);

        if (!url) return;

        // Tenta converter para dataURL para evitar problemas de CORS em background-image.
        // Se falhar, usa a URL directamente (same-origin funciona normalmente).
        imageToDataUrl(url, function (result) {
            applyBackdrop(container, result || url);
        });
    }

    // ─── Processa containers de IMAGEM / ÁLBUM ───────────────────────────────
    function processImageContainer(container) {
        if (container.querySelector('.media-backdrop')) return;

        const img = container.querySelector('img');
        if (!img) return;

        const src = img.src || img.dataset.src;
        if (!src) return;

        // Imagens partilham a mesma origem — usa directamente, sem conversão canvas
        applyBackdrop(container, src);
    }

    // ─── Dispatcher por tipo de container ────────────────────────────────────
    function injectBackdrop(container) {
        const isVideo =
            container.classList.contains('post-video-container') ||
            container.classList.contains('post-video') ||
            container.classList.contains('video-locked') ||
            container.querySelector('video') !== null;

        if (isVideo) {
            processVideoContainer(container);
        } else {
            processImageContainer(container);
        }
    }

    // ─── Processa todos os containers visíveis no DOM ─────────────────────────
    const SELECTORS = [
        '.post-image-container',
        '.post-video-container',
        '.post-video',
        '.video-locked',
        '.album-container',
        '.album-cover-link',
    ];

    function processAll() {
        document.querySelectorAll(SELECTORS.join(', ')).forEach(injectBackdrop);
    }

    // ─── MutationObserver para infinite scroll / AJAX ────────────────────────
    function observeFeed() {
        const feed = document.querySelector('.posts-list, #feed, main, body');
        if (!feed) return;

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    SELECTORS.forEach(function (sel) {
                        if (node.matches && node.matches(sel)) injectBackdrop(node);
                        if (node.querySelectorAll) node.querySelectorAll(sel).forEach(injectBackdrop);
                    });
                });
            });
        });

        observer.observe(feed, { childList: true, subtree: true });
    }

    // ─── Bootstrap ───────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { processAll(); observeFeed(); });
    } else {
        processAll();
        observeFeed();
    }
})();