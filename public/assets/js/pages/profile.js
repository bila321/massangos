/**
 * profile.js
 * JavaScript extraído de profile.php
 * Funções específicas da página de perfil do utilizador
 */

// ============================================================
// VARIÁVEIS GLOBAIS (definidas via PHP no profile.php via <script> inline)
// Estas são injetadas pelo servidor. Aqui apenas documentadas:
//   window.BASE_URL
//   window.UPLOAD_URL
//   window.CURRENT_USER_ID
//   window.POST_OWNER_ID
//   window.IS_POST_OWNER
//   window.CURRENT_USER_PROFILE_PICTURE
//   window.IS_VERIFIED_CREATOR
// ============================================================


// ============================================================
// FILTROS DE CONTEÚDO E LAYOUT (Feed vs Grid)
// ============================================================

document.addEventListener('DOMContentLoaded', function () {

    const filterButtonsContainer = document.querySelector('.filter-buttons');
    const profileContentFiltered = document.getElementById('profileContentFiltered');

    /**
     * Aplica o layout correto (feed ou grid) consoante o filtro activo.
     * @param {string} filterType - 'all' para feed, qualquer outro para grid
     */
    function applyLayout(filterType) {
        if (filterType === 'all') {
            profileContentFiltered.classList.remove('grid-mode');
        } else {
            profileContentFiltered.classList.add('grid-mode');
        }
    }

    if (filterButtonsContainer && profileContentFiltered) {

        // Obter filtro da URL se existir
        const urlParams = new URLSearchParams(window.location.search);
        const initialFilter = urlParams.get('filter') || 'all';

        // Inicializa com o filtro correto
        applyLayout(initialFilter);

        filterButtonsContainer.addEventListener('click', function (event) {
            const targetButton = event.target.closest('button');

            if (targetButton && targetButton.dataset.filter) {
                filterButtonsContainer.querySelectorAll('button').forEach(function (btn) {
                    btn.classList.remove('active');
                });

                targetButton.classList.add('active');

                const filterType = targetButton.dataset.filter;

                // Atualizar a URL sem recarregar a página para manter o estado
                const newUrl = new URL(window.location.href);
                newUrl.searchParams.set('filter', filterType);
                window.history.replaceState({ path: newUrl.href }, '', newUrl.href);

                // Aplicar layout (Feed ou Grid)
                applyLayout(filterType);

                // Filtrar itens
                const gridItems = profileContentFiltered.querySelectorAll('.grid-item-wrapper');
                const feedItems = profileContentFiltered.querySelectorAll('.feed-item-wrapper');

                if (filterType === 'all') {
                    // Mostrar apenas itens do feed
                    gridItems.forEach(function (item) { item.style.display = 'none'; });
                    feedItems.forEach(function (item) { item.style.display = 'block'; });

                    // Ativar gatilhos do feed e desativar da grelha para evitar duplicação no lightbox
                    document.querySelectorAll('.grid-trigger').forEach(function (el) { el.classList.remove('lightbox-trigger'); });
                    document.querySelectorAll('.feed-trigger').forEach(function (el) { el.classList.add('lightbox-trigger'); });
                } else {
                    // Mostrar apenas itens da grelha que correspondem ao tipo
                    feedItems.forEach(function (item) { item.style.display = 'none'; });
                    gridItems.forEach(function (item) {
                        if (item.dataset.type === filterType) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });

                    // Ativar gatilhos da grelha e desativar do feed
                    document.querySelectorAll('.feed-trigger').forEach(function (el) { el.classList.remove('lightbox-trigger'); });
                    document.querySelectorAll('.grid-trigger').forEach(function (el) { el.classList.add('lightbox-trigger'); });
                }
            }
        });

        // Forçar o estado inicial correto baseado na URL
        const targetInitialBtn = filterButtonsContainer.querySelector('button[data-filter="' + initialFilter + '"]');
        if (targetInitialBtn) {
            targetInitialBtn.click();
        } else {
            const activeBtn = filterButtonsContainer.querySelector('button.active');
            if (activeBtn) activeBtn.click();
        }
    }

    // ============================================================
    // PREVIEW DE VÍDEO NO HOVER (grelha)
    // ============================================================
    const videoItems = document.querySelectorAll('.grid-item-wrapper[data-type="video"]');

    videoItems.forEach(function (item) {
        const video = item.querySelector('video');
        const link = item.querySelector('.profile-grid-link');

        if (video && link) {
            video.muted = true;
            video.playsInline = true;
            video.loop = true;

            link.addEventListener('mouseenter', function () {
                const playPromise = video.play();
                if (playPromise !== undefined) {
                    playPromise.catch(function (error) {
                        console.log('Autoplay prevented:', error);
                    });
                }
            });

            link.addEventListener('mouseleave', function () {
                video.pause();
                video.currentTime = 0;
            });
        }
    });
});


// ============================================================
// LIGHTBOX — Abrir ao clicar num item de vídeo
// ============================================================
document.addEventListener('click', function (e) {
    const item = e.target.closest('.video-item');
    if (!item) return;

    e.preventDefault();

    const postId = item.dataset.postModal;

    if (typeof openPremiumLightbox === 'function') {
        openPremiumLightbox(postId);
    }
});


// ============================================================
// PROTEÇÃO DE LIGHTBOX COM BLUR (v3)
// Impede que o lightbox-trigger dispare quando o conteúdo está com blur.
// ============================================================
document.addEventListener('click', function (e) {
    const trigger = e.target.closest('.lightbox-trigger');
    if (trigger) {
        const hasBlur =
            trigger.querySelector('.media-blur-container, .album-blur-container') ||
            trigger.classList.contains('media-blur-container') ||
            trigger.classList.contains('album-blur-container');

        if (e.target.tagName === 'BUTTON' && e.target.innerText.includes('Ver mesmo assim')) return;

        if (hasBlur) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        }
    }
}, true);


// ============================================================
// DESFOCAR MEDIA (unblur)
// ============================================================

/**
 * Remove o blur de uma mídia específica do feed.
 * @param {number|string} feedItemId
 */
function unblurMedia(feedItemId) {
    const wrapper = document.querySelector('.media-wrapper-' + feedItemId);
    if (wrapper) {
        wrapper.classList.remove('media-blur-container');
        wrapper.querySelectorAll('.media-blur').forEach(function (el) {
            el.classList.remove('media-blur');
            if (el.tagName === 'VIDEO') el.style.pointerEvents = 'auto';
        });
        const overlay = wrapper.querySelector('.media-overlay-msg');
        if (overlay) overlay.style.display = 'none';
        const trigger = wrapper.querySelector('.lightbox-trigger');
        if (trigger) trigger.dataset.aiUnlocked = 'true';
    }
}

/**
 * Remove o blur de um álbum específico.
 * @param {HTMLElement} button
 */
function unblurAlbum(button) {
    const container = button.closest('.album-blur-container') || button.closest('.lightbox-trigger');
    if (container) {
        container.classList.remove('album-blur-container');
        container.querySelectorAll('.album-blur, .album-cover-image').forEach(function (el) {
            el.classList.remove('album-blur');
            el.style.filter = 'none';
            el.style.pointerEvents = 'auto';
        });
        const overlay = container.querySelector('.album-overlay-msg');
        if (overlay) overlay.style.display = 'none';
        const link = container.querySelector('.album-cover-link');
        if (link) link.style.pointerEvents = 'auto';
    }
}

/**
 * Remove o blur de um item da grelha de perfil.
 * @param {HTMLElement} overlayEl - O overlay clicado
 */
function unblurGridItem(overlayEl) {
    const card = overlayEl.closest('.profile-grid-item');
    if (!card) return;
    card.querySelectorAll('.media-blur').forEach(function (el) {
        el.classList.remove('media-blur');
        el.style.filter = 'none';
    });
    overlayEl.remove();
}


// ============================================================
// MODAL DE VERIFICAÇÃO DE CONTA
// ============================================================

/**
 * Abre o modal de convite para verificação.
 */
function openVerificationInviteModal() {
    const modal = document.getElementById('verificationInviteModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.offsetHeight; // forçar reflow para animação
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Fecha o modal de convite para verificação.
 */
function closeVerificationInviteModal() {
    const modal = document.getElementById('verificationInviteModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(function () {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }, 300);
    }
}

/**
 * Redireciona para a página de verificação.
 */
function proceedToVerification() {
    closeVerificationInviteModal();
    window.location.href = window.BASE_URL + 'verification/index.php';
}

// Fechar modal ao clicar fora ou com ESC
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('verificationInviteModal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeVerificationInviteModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) closeVerificationInviteModal();
        });
    }
});


// ============================================================
// MODAL HOIST — Portal pattern
// Move os modais para document.body para evitar problemas de stacking context.
// ============================================================
(function hoistModalsToBody() {
    var ids = ['feedLightbox', 'verificationInviteModal', 'verificationModal'];

    function hoist() {
        ids.forEach(function (id) {
            var el = document.getElementById(id);
            if (el && el.parentElement !== document.body) document.body.appendChild(el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hoist);
    } else {
        hoist();
    }
})();
// --- Layout filter ---
(function() {
    var layout = document.querySelector('.profile-two-col-layout');
    if (!layout) return;

    function updateLayout(filterBtn) {
        var filter = filterBtn ? filterBtn.getAttribute('data-filter') : 'all';
        if (filter === 'all') {
            layout.classList.remove('filter-grid');
        } else {
            layout.classList.add('filter-grid');
        }
    }

    var activeBtn = document.querySelector('.filter-buttons button.active');
    updateLayout(activeBtn);

    document.querySelectorAll('.filter-buttons button').forEach(function(btn) {
        btn.addEventListener('click', function() { updateLayout(btn); });
    });
})();