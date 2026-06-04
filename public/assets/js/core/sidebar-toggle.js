/**
 * sidebar-toggle.js
 * Controla a sidebar responsiva do Massango.
 * Compatível com: #appSidebar, #mobileMenuToggle, #sidebarOverlay
 */

(function () {
    'use strict';

    const MOBILE_BREAKPOINT = 992; // coincide com o topbar (@media max-width: 992px)

    // ── Elementos ────────────────────────────────────────────────────────
    const sidebar = document.getElementById('appSidebar');
    const toggleBtn = document.getElementById('mobileMenuToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const navLinks = sidebar ? sidebar.querySelectorAll('.nav-link') : [];

    if (!sidebar) return; // página sem sidebar — sair limpo

    // ── Estado ───────────────────────────────────────────────────────────
    function isOpen() {
        return sidebar.classList.contains('active');
    }

    // ── Abrir ────────────────────────────────────────────────────────────
    function openSidebar() {
        sidebar.classList.add('active');
        if (overlay) {
            overlay.style.display = 'block';
            // força reflow para a transição CSS funcionar
            overlay.offsetHeight;
            overlay.style.opacity = '1';
        }
        document.body.style.overflow = 'hidden';

        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', 'true');
            toggleBtn.setAttribute('aria-label', 'Fechar menu');
        }
    }

    // ── Fechar ───────────────────────────────────────────────────────────
    function closeSidebar() {
        sidebar.classList.remove('active');
        if (overlay) {
            overlay.style.opacity = '0';
            // aguarda transição antes de esconder
            setTimeout(() => { overlay.style.display = 'none'; }, 250);
        }
        document.body.style.overflow = '';

        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', 'false');
            toggleBtn.setAttribute('aria-label', 'Abrir menu');
        }
    }

    // ── Toggle ───────────────────────────────────────────────────────────
    function toggleSidebar() {
        isOpen() ? closeSidebar() : openSidebar();
    }

    // ── Event Listeners ──────────────────────────────────────────────────
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    // Fechar ao navegar (mobile)
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= MOBILE_BREAKPOINT) {
                closeSidebar();
            }
        });
    });

    // Fechar ao redimensionar para desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > MOBILE_BREAKPOINT && isOpen()) {
            closeSidebar();
        }
    });

    // ESC fecha
    document.addEventListener('keydown', e => {
        if ((e.key === 'Escape' || e.keyCode === 27) && isOpen()) {
            closeSidebar();
        }
    });

    // ── Init ─────────────────────────────────────────────────────────────
    function init() {
        // Garantir estado limpo no carregamento
        if (window.innerWidth <= MOBILE_BREAKPOINT) {
            closeSidebar();
        }
        // Overlay invisível por padrão
        if (overlay) {
            overlay.style.cssText = 'display:none; opacity:0; transition:opacity 0.25s ease;';
        }
    }

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', init)
        : init();

    // ── API pública ──────────────────────────────────────────────────────
    window.sidebarToggle = { open: openSidebar, close: closeSidebar, toggle: toggleSidebar };

})();