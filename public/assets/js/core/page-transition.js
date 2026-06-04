/**
 * page-transition.js
 * Page Transition Loader — massangos platform
 *
 * Funcionalidades:
 *  - Barra de progresso verde no topo ao navegar entre páginas
 *  - Spinner discreto no canto inferior direito (aparece após 400ms)
 *  - Overlay de saída suave (fade-out) ao clicar em links internos
 *  - Fade-in da nova página ao carregar
 *  - Compatível com: links normais, formulários de navegação,
 *    window.location.href, reels.php, index.php, premium_lightbox (não intercepta o lightbox)
 *
 * NÃO intercepta:
 *  - Links externos (outro domínio)
 *  - Links com target="_blank"
 *  - Links de download (download attribute)
 *  - Links de ancora (#hash apenas)
 *  - Links dentro do lightbox modal (#feedLightbox)
 *  - Links de mailto: / tel: / javascript:
 *  - Formulários com data-no-transition
 */

(function () {
    'use strict';

    /* ── Criar elementos do loader ──────────────────────────────────── */
    function createLoader() {
        // Garantir que o body existe antes de appendar
        const target = document.body || document.documentElement;

        // Barra de progresso
        const bar = document.createElement('div');
        bar.id = 'pt-bar';
        target.appendChild(bar);

        // Spinner SVG
        const spinner = document.createElement('div');
        spinner.id = 'pt-spinner';
        spinner.innerHTML = `
            <svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="18" cy="18" r="15"
                    stroke="rgba(0,245,118,0.18)"
                    stroke-width="3"/>
                <path d="M18 3 A15 15 0 0 1 33 18"
                    stroke="#00f576"
                    stroke-width="3"
                    stroke-linecap="round"/>
            </svg>`;
        target.appendChild(spinner);

        // Overlay de saída
        const overlay = document.createElement('div');
        overlay.id = 'pt-overlay';
        target.appendChild(overlay);
    }

    /* ── Estado interno ─────────────────────────────────────────────── */
    let bar, spinner, overlay;
    let progressTimer = null;
    let spinnerTimer = null;
    let currentProgress = 0;
    let isNavigating = false;

    /* ── Iniciar loader ─────────────────────────────────────────────── */
    function start() {
        if (isNavigating) return;
        isNavigating = true;
        currentProgress = 0;

        bar.style.width = '0%';
        bar.style.transition = 'none'; // reset instantâneo
        bar.classList.remove('finishing', 'done');

        // Forçar reflow antes de iniciar animação
        bar.offsetWidth;

        bar.style.transition = '';
        bar.classList.add('running');
        bar.style.width = '15%';

        // Progresso simulado: avança até ~85% de forma orgânica
        let step = 0;
        const steps = [
            { target: 35, delay: 120 },
            { target: 55, delay: 200 },
            { target: 70, delay: 350 },
            { target: 80, delay: 500 },
            { target: 86, delay: 800 },
        ];

        function advance() {
            if (step >= steps.length) return;
            const { target, delay } = steps[step++];
            progressTimer = setTimeout(function () {
                bar.style.width = target + '%';
                advance();
            }, delay);
        }
        advance();

        // Spinner aparece se a carga demorar mais de 400ms
        spinnerTimer = setTimeout(function () {
            spinner.classList.add('visible');
        }, 400);

        // Overlay de saída
        overlay.classList.add('leaving');
    }

    /* ── Finalizar loader ───────────────────────────────────────────── */
    function finish() {
        clearTimeout(progressTimer);
        clearTimeout(spinnerTimer);
        isNavigating = false;

        bar.classList.remove('running');
        bar.classList.add('finishing');
        bar.style.width = '100%';

        spinner.classList.remove('visible');
        overlay.classList.remove('leaving');

        // Esconder a barra após completar
        setTimeout(function () {
            bar.classList.add('done');
            setTimeout(function () {
                bar.classList.remove('finishing', 'done');
                bar.style.width = '0%';
            }, 350);
        }, 250);
    }

    /* ── Verificar se um link deve ser interceptado ──────────────────── */
    function shouldIntercept(anchor) {
        if (!anchor || !anchor.href) return false;

        const href = anchor.href;
        const origin = window.location.origin;

        // Ignorar protocolos especiais
        if (
            href.startsWith('mailto:') ||
            href.startsWith('tel:') ||
            href.startsWith('javascript:')
        ) return false;

        // Ignorar externo, _blank, download
        if (anchor.target === '_blank') return false;
        if (anchor.hasAttribute('download')) return false;
        if (!href.startsWith(origin)) return false;

        // Ignorar âncoras puras (mesma página, só hash diferente)
        const url = new URL(href);
        if (url.pathname === window.location.pathname && url.hash) return false;

        // Ignorar links dentro do modal do lightbox (aberto)
        if (anchor.closest('#feedLightbox')) return false;

        // Ignorar links dentro de um lightbox-trigger —
        // o clique pertence ao handler do lightbox, não à navegação
        if (anchor.closest('.lightbox-trigger')) return false;

        // Ignorar links dentro de cards de post/reel (podem ter triggers aninhados)
        if (anchor.closest('.reel-card')) return false;

        // Ignorar links marcados explicitamente
        if (anchor.dataset.noTransition !== undefined) return false;

        return true;
    }

    /* ── Interceptar cliques em links ───────────────────────────────── */
    function interceptLinks() {
        document.addEventListener('click', function (e) {
            // Nunca interferir com lightbox-trigger (divs geridos pelo premium_lightbox.js)
            if (e.target.closest('.lightbox-trigger')) return;
            // Nunca interferir se o evento já foi tratado por outro handler
            if (e.defaultPrevented) return;

            const anchor = e.target.closest('a');
            if (!anchor || !shouldIntercept(anchor)) return;

            // Ctrl/Cmd + clique → nova aba, não interceptar
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

            e.preventDefault();
            start();

            // Navegar após iniciar a animação de saída
            setTimeout(function () {
                window.location.href = anchor.href;
            }, 80);
        }, true);
    }

    /* ── Interceptar window.location programático ────────────────────── */
    function interceptProgrammaticNavigation() {
        // NÃO sobrescrever pushState: o premium_lightbox.js usa pushState/replaceState
        // para actualizar a URL ao abrir/fechar o lightbox sem navegar.
        // Sobrescrever causaria que cada abertura do lightbox disparasse o overlay de saída.
        // A transição de página é gerida exclusivamente pelos cliques em <a> e submissões de forms.
    }

    /* ── Interceptar formulários de navegação ────────────────────────── */
    function interceptForms() {
        document.addEventListener('submit', function (e) {
            const form = e.target;
            // Não interceptar: AJAX forms, lightbox forms, forms marcados
            if (form.dataset.noTransition !== undefined) return;
            if (form.closest('#feedLightbox')) return;
            if (form.id === 'lightboxCommentForm') return;
            if (form.id === 'filterForm' && window.location.pathname.includes('reels')) {
                // O filtro de reels submete e recarrega a mesma página — deixar passar
                start();
                return;
            }
            if (form.method && form.method.toLowerCase() === 'get') {
                start();
            }
        }, true);
    }

    /* ── Finalizar quando a página nova carregar ─────────────────────── */
    function setupPageLoad() {
        // window.load: página totalmente carregada
        window.addEventListener('load', finish);

        // pageshow: captura também navegação via Voltar/Avançar do browser
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) {
                // Página veio do cache do browser (bfcache) — não animar entrada
                document.body.classList.add('pt-instant');
                finish();
            }
        });

        // DOMContentLoaded: finaliza a barra antes mesmo do load completo
        // (garante feedback rápido em páginas com muitos recursos)
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                bar.style.width = '95%';
            });
        }

        // SAFETY NET: script carregado com defer ou no fim do body —
        // a página já está pronta quando init() corre, por isso o
        // evento 'load' já disparou e finish() nunca seria chamado.
        // Verificamos o estado e chamamos finish() imediatamente.
        if (document.readyState === 'complete') {
            finish();
        } else if (document.readyState === 'interactive') {
            window.addEventListener('load', finish, { once: true });
        }
    }

    /* ── Expor API pública (para uso em premium_lightbox.js, etc.) ─────── */
    window.PageTransition = {
        start: start,
        finish: finish,
    };

    /* ── Bootstrap ──────────────────────────────────────────────────── */
    // Criar elementos logo que o script é executado (antes do DOMContentLoaded
    // é seguro porque usamos appendChild que aguarda pelo body)
    function init() {
        createLoader();

        bar = document.getElementById('pt-bar');
        spinner = document.getElementById('pt-spinner');
        overlay = document.getElementById('pt-overlay');

        interceptLinks();
        interceptForms();
        interceptProgrammaticNavigation();
        setupPageLoad();

        // Iniciar a barra de entrada apenas se a página ainda está a carregar.
        // Se readyState for 'complete' ou 'interactive' (script com defer),
        // não iniciar a barra — setupPageLoad() já chamou finish().
        if (document.readyState === 'loading') {
            bar.classList.add('running');
            bar.style.width = '40%';
            spinnerTimer = setTimeout(function () {
                spinner.classList.add('visible');
            }, 600);
        }
    }

    if (document.body) {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }

})();