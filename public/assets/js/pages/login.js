/**
 * assets/js/pages/login.js
 *
 * Contém o código do carrossel que estava inline no login.php original.
 * O mesmo ficheiro pode ser usado pelo register.php (ou extrair para
 * assets/js/components/auth-carousel.js e importar em ambos).
 *
 * Pré-requisito: window.CAROUSEL_SLIDES definido em _head.php antes deste script.
 */

(function () {
    'use strict';

    /* Paleta de gradientes usada quando não há imagem */
    const GRADIENTS = [
        'linear-gradient(135deg,#07c95b 0%,#00a844 100%)',
        'linear-gradient(135deg,#0ea5e9 0%,#0369a1 100%)',
        'linear-gradient(135deg,#a855f7 0%,#7e22ce 100%)',
        'linear-gradient(135deg,#f97316 0%,#c2410c 100%)',
        'linear-gradient(135deg,#ec4899 0%,#be185d 100%)',
        'linear-gradient(135deg,#14b8a6 0%,#0f766e 100%)',
    ];

    const slides = window.CAROUSEL_SLIDES || [];
    if (!slides.length) return;

    /* ── Helpers ── */
    function buildSlide(data, idx) {
        const grad = GRADIENTS[idx % GRADIENTS.length];
        const div  = document.createElement('div');
        div.className = 'carousel-slide';
        div.setAttribute('role', 'tabpanel');
        div.setAttribute('aria-label', 'Slide ' + (idx + 1));

        if (data.image_url) {
            div.style.backgroundImage =
                'linear-gradient(to bottom,rgba(11,22,16,.35) 0%,rgba(11,22,16,.80) 100%),' +
                'url(' + JSON.stringify(data.image_url) + ')';
            div.style.backgroundSize     = 'cover';
            div.style.backgroundPosition = 'center';
        } else {
            div.style.background = grad;
        }

        div.innerHTML =
            '<div class="carousel-slide-body">' +
            '<p class="carousel-slide-title">' + (data.title    || '') + '</p>' +
            '<p class="carousel-slide-sub">'   + (data.subtitle || '') + '</p>' +
            '</div>';

        return div;
    }

    function buildDot(idx, total, onClick) {
        const btn = document.createElement('button');
        btn.className = 'carousel-dot';
        btn.setAttribute('role', 'tab');
        btn.setAttribute('aria-label', 'Ir para slide ' + (idx + 1) + ' de ' + total);
        btn.addEventListener('click', onClick);
        return btn;
    }

    /* ── Inicializa uma instância de carrossel ── */
    function initCarousel(trackEl, dotsEl, autoDelay) {
        if (!trackEl) return;
        trackEl.innerHTML = '';
        dotsEl.innerHTML  = '';

        const total = slides.length;
        let current = 0;
        let timer   = null;

        slides.forEach(function (s, i) { trackEl.appendChild(buildSlide(s, i)); });

        const slideEls = trackEl.querySelectorAll('.carousel-slide');

        const dotEls = slides.map(function (_, i) {
            const dot = buildDot(i, total, function () { goTo(i, true); });
            dotsEl.appendChild(dot);
            return dot;
        });

        function activate(idx) {
            slideEls.forEach(function (el, i) {
                el.classList.toggle('is-active', i === idx);
                el.classList.toggle('is-prev',   i === (idx - 1 + total) % total);
            });
            dotEls.forEach(function (d, i) {
                d.classList.toggle('is-active', i === idx);
                d.setAttribute('aria-selected', i === idx ? 'true' : 'false');
            });
            current = idx;
        }

        function goTo(idx, resetTimer) {
            activate((idx + total) % total);
            if (resetTimer) {
                clearInterval(timer);
                timer = setInterval(advance, autoDelay);
            }
        }

        function advance() { goTo(current + 1, false); }

        /* Swipe / drag support */
        var startX = 0;
        trackEl.addEventListener('touchstart', function (e) {
            startX = e.touches[0].clientX;
        }, { passive: true });
        trackEl.addEventListener('touchend', function (e) {
            var diff = startX - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 40) goTo(current + (diff > 0 ? 1 : -1), true);
        }, { passive: true });

        activate(0);
        timer = setInterval(advance, autoDelay);
    }

    /* Toggle de password (login + register) */
    document.querySelectorAll('.auth-password-toggle[data-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            var icon  = btn.querySelector('i');
            if (!input) return;
            if (input.type === 'password') {
                input.type     = 'text';
                icon.className = 'ti ti-eye-off';
            } else {
                input.type     = 'password';
                icon.className = 'ti ti-eye';
            }
        });
    });

    initCarousel(
        document.getElementById('desktopCarousel'),
        document.getElementById('desktopDots'),
        5000
    );
    initCarousel(
        document.getElementById('mobileCarousel'),
        document.getElementById('mobileDots'),
        4000
    );

})();
