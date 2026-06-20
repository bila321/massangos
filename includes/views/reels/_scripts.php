<?php
/**
 * Partial: scripts da página de Reels.
 *
 *   @var array $logged_in_user_data
 *   @var int   $total_pages
 *   @var int   $page
 */
?>
<!-- ── SCRIPTS ──────────────────────────────────────────────────────────────── -->

<!-- 1. Variáveis globais PRIMEIRO -->
<script>
    window.BASE_URL = "<?= BASE_URL ?>";
    window.UPLOAD_URL = "<?= UPLOAD_URL ?>";
    window.CURRENT_USER_ID = <?= is_logged_in() ? (int)get_current_user_id() : 'null' ?>;
    window.POST_OWNER_ID = null;
    window.IS_POST_OWNER = false;
    window.CURRENT_USER_PROFILE_PICTURE = "<?= htmlspecialchars($_SESSION['user_profile_picture'] ?? UPLOAD_URL . 'profiles/default_profile.png') ?>";
    window.IS_VERIFIED_CREATOR = <?= json_encode((bool)($logged_in_user_data['is_verified_creator'] ?? false)) ?>;
    window.CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
</script>

<script src="<?= BASE_URL ?>assets/js/components/premium_lightbox.js"></script>
<script src="<?= BASE_URL ?>assets/js/pages/reels.js?v=202606161014"></script>

<?php if (($total_pages ?? 1) > 1): ?>
<script>
/**
 * Infinite scroll com confirmação por batch — estilo Facebook.
 *
 * Comportamento:
 *  1. O IntersectionObserver monitoriza #reels-sentinel.
 *  2. Quando o sentinel entra no viewport, carrega a próxima página via fetch.
 *  3. Ao fim de cada batch (CARDS_PER_CONFIRM cards inseridos), o observer
 *     para e mostra o banner de confirmação em vez de continuar sozinho.
 *  4. O utilizador escolhe "Continuar" (retoma o observer) ou "Voltar ao topo".
 *  5. Quando não há mais páginas, mostra a mensagem de fim.
 */
(function () {
    'use strict';

    const CARDS_PER_CONFIRM = 12; // cards por batch antes de pedir confirmação

    const wrapper   = document.getElementById('reels-load-more-wrapper');
    if (!wrapper) return;

    const sentinel  = document.getElementById('reels-sentinel');
    const spinner   = document.getElementById('reels-loading-spinner');
    const banner    = document.getElementById('reels-continue-banner');
    const endMsg    = document.getElementById('reels-end-msg');
    const seenCount = document.getElementById('reels-seen-count');
    const btnYes    = document.getElementById('btn-continue-yes');
    const grid      = document.querySelector('.reels-grid');

    let currentPage  = parseInt(wrapper.dataset.currentPage, 10);
    let totalPages   = parseInt(wrapper.dataset.totalPages, 10);
    let urlTemplate  = wrapper.dataset.urlTemplate;
    let seenTotal    = grid ? grid.querySelectorAll('.reel-card').length : 0;
    let loadedInBatch = 0;
    let loading      = false;

    // ── Helpers de estado ──────────────────────────────────────────────
    function showSpinner()  { spinner.hidden = false; banner.hidden = true; endMsg.hidden = true; }
    function hideSpinner()  { spinner.hidden = true; }
    function showBanner()   { banner.hidden = false; spinner.hidden = true; endMsg.hidden = true; }
    function showEnd()      { endMsg.hidden = false; spinner.hidden = true; banner.hidden = true; }
    function updateSeenCount() {
        if (seenCount) seenCount.textContent = seenTotal;
    }

    // ── Parser de HTML: extrai cards da resposta da próxima página ──────
    function extractCards(html) {
        const parser = new DOMParser();
        const doc    = parser.parseFromString(html, 'text/html');
        return Array.from(doc.querySelectorAll('.reel-card'));
    }

    // ── Reinicializa lightbox e lazy-video nos cards novos ──────────────
    function initNewCards(cards) {
        // lazy video: observar os novos vídeos se o LazyLoader global existir
        if (window.lazyVideoObserver) {
            cards.forEach(card => {
                card.querySelectorAll('video[data-src]').forEach(v => {
                    window.lazyVideoObserver.observe(v);
                });
            });
        }
        // lightbox: se a função de init global existir, chamar
        if (typeof window.initLightboxTriggers === 'function') {
            window.initLightboxTriggers();
        }
    }

    // ── Carregar próxima página ─────────────────────────────────────────
    async function loadNextPage() {
        if (loading || currentPage >= totalPages) return;

        loading = true;
        observer.disconnect();
        showSpinner();

        const nextPage = currentPage + 1;
        const url      = urlTemplate.replace('__PAGE__', nextPage);

        try {
            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const html  = await res.text();
            const cards = extractCards(html);

            if (cards.length === 0) {
                currentPage = totalPages; // força fim
            } else {
                // Inserir antes do wrapper (não dentro do grid diretamente,
                // para não perder a referência ao sentinel)
                cards.forEach(card => {
                    card.style.animationDelay = (loadedInBatch * 40) + 'ms';
                    card.classList.add('reel-card--entering');
                    grid.appendChild(card);
                });

                currentPage      = nextPage;
                seenTotal       += cards.length;
                loadedInBatch   += cards.length;
                updateSeenCount();
                initNewCards(cards);
            }

            hideSpinner();

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
            hideSpinner();
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
</script>
<?php endif; ?>
