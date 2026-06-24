/**
 * Infinite scroll com confirmação por batch — estilo Facebook.
 *
 * Comportamento:
 *  1. O IntersectionObserver monitoriza #reels-sentinel.
 *  2. Quando o sentinel entra no viewport, insere skeletons no grid
 *     (clonados de #reels-skeleton-template) e carrega a próxima
 *     página via fetch.
 *  3. Quando a resposta chega, os skeletons são removidos e os cards
 *     reais inseridos no lugar.
 *  4. Ao fim de cada batch (CARDS_PER_CONFIRM cards inseridos), o
 *     observer para e mostra o banner de confirmação em vez de
 *     continuar sozinho.
 *  5. O utilizador escolhe "Continuar" (retoma o observer) ou
 *     "Voltar ao topo".
 *  6. Quando não há mais páginas, mostra a mensagem de fim.
 */
(function () {
    'use strict';

    const CARDS_PER_CONFIRM = 12; // cards por batch antes de pedir confirmação

    const wrapper  = document.getElementById('reels-load-more-wrapper');
    if (!wrapper) return;

    const sentinel = document.getElementById('reels-sentinel');
    const banner   = document.getElementById('reels-continue-banner');
    const endMsg   = document.getElementById('reels-end-msg');
    const seenCount = document.getElementById('reels-seen-count');
    const btnYes   = document.getElementById('btn-continue-yes');
    const grid     = document.querySelector('.reels-grid');
    const skelTemplate = document.getElementById('reels-skeleton-template');

    let currentPage   = parseInt(wrapper.dataset.currentPage, 10);
    let totalPages    = parseInt(wrapper.dataset.totalPages, 10);
    let urlTemplate    = wrapper.dataset.urlTemplate;
    let seenTotal      = grid ? grid.querySelectorAll('.reel-card').length : 0;
    let loadedInBatch  = 0;
    let loading        = false;
    let activeSkeletons = []; // referências aos nós skeleton atualmente no DOM

    // ── Helpers de estado ──────────────────────────────────────────────
    function showBanner() { banner.hidden = false; endMsg.hidden = true; }
    function showEnd()    { endMsg.hidden = false; banner.hidden = true; }
    function updateSeenCount() {
        if (seenCount) seenCount.textContent = seenTotal;
    }

    // ── Skeletons: inserir/remover a partir do <template> ───────────────
    // Insere N skeletons no grid, antes do sentinel, para dar feedback
    // visual imediato enquanto o fetch da próxima página decorre.
    function insertSkeletons(count) {
        if (!skelTemplate || !grid) return;

        activeSkeletons = [];
        for (let i = 0; i < count; i++) {
            const node = skelTemplate.content.firstElementChild.cloneNode(true);
            grid.appendChild(node);
            activeSkeletons.push(node);
        }
    }

    function removeSkeletons() {
        activeSkeletons.forEach(node => node.remove());
        activeSkeletons = [];
    }

    // ── Parser de HTML: extrai cards da resposta da próxima página ──────
    function extractCards(html) {
        const parser = new DOMParser();
        const doc    = parser.parseFromString(html, 'text/html');
        return Array.from(doc.querySelectorAll('.reel-card'));
    }

    // ── Reinicializa lightbox e lazy-video nos cards novos ──────────────
    function initNewCards(cards) {
        if (window.lazyVideoObserver) {
            cards.forEach(card => {
                card.querySelectorAll('video[data-src]').forEach(v => {
                    window.lazyVideoObserver.observe(v);
                });
            });
        }
        if (typeof window.initLightboxTriggers === 'function') {
            window.initLightboxTriggers();
        }
    }

    // ── Carregar próxima página ─────────────────────────────────────────
    async function loadNextPage() {
        if (loading || currentPage >= totalPages) return;

        loading = true;
        observer.disconnect();

        // Quantos skeletons mostrar: tenta adivinhar o tamanho do próximo
        // batch sem exceder o que falta (evita over-promise visual).
        const remaining = wrapper.dataset.total
            ? parseInt(wrapper.dataset.total, 10) - seenTotal
            : CARDS_PER_CONFIRM;
        const skeletonCount = Math.max(1, Math.min(CARDS_PER_CONFIRM, remaining));
        insertSkeletons(skeletonCount);

        const nextPage = currentPage + 1;
        const url       = urlTemplate.replace('__PAGE__', nextPage);

        try {
            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const html  = await res.text();
            const cards = extractCards(html);

            removeSkeletons();

            if (cards.length === 0) {
                currentPage = totalPages; // força fim
            } else {
                cards.forEach(card => {
                    card.style.animationDelay = (loadedInBatch * 40) + 'ms';
                    card.classList.add('reel-card--entering');
                    grid.appendChild(card);
                });

                currentPage    = nextPage;
                seenTotal     += cards.length;
                loadedInBatch += cards.length;
                updateSeenCount();
                initNewCards(cards);
            }

            if (currentPage >= totalPages) {
                showEnd();
                return;
            }

            // Fim de batch → pedir confirmação
            if (loadedInBatch >= CARDS_PER_CONFIRM) {
                loadedInBatch = 0;
                showBanner();
            } else {
                // Batch ainda não cheio → continuar a observar
                observer.observe(sentinel);
            }

        } catch (err) {
            console.error('[Reels] loadNextPage error:', err);
            removeSkeletons();
            observer.observe(sentinel); // tentar de novo no próximo trigger
        } finally {
            loading = false;
        }
    }

    // ── IntersectionObserver ────────────────────────────────────────────
    const observer = new IntersectionObserver(
        (entries) => {
            if (entries[0].isIntersecting) loadNextPage();
        },
        { rootMargin: '300px' } // pré-carrega 300px antes do fim do ecrã
    );

    observer.observe(sentinel);

    // ── Botão "Continuar a ver" ─────────────────────────────────────────
    if (btnYes) {
        btnYes.addEventListener('click', () => {
            banner.hidden = true;
            observer.observe(sentinel);
        });
    }

})();
