/**
 * home.js
 * JavaScript extraído de index.php
 * Funções específicas da página home/feed
 */

// ============================================================
// VARIÁVEIS GLOBAIS (definidas via PHP no index.php via <script> inline)
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

        // Se o clique foi no BOTÃO "Ver mesmo assim", deixamos passar para a função unblur
        if (e.target.tagName === 'BUTTON' && e.target.innerText.includes('Ver mesmo assim')) {
            return;
        }

        // Se ainda tem blur e NÃO foi no botão, bloqueamos a abertura do lightbox
        if (hasBlur) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
        }
    }
}, true); // Captura na fase inicial para garantir o bloqueio


// ============================================================
// DESFOCAR MEDIA (unblur)
// ============================================================

/**
 * Remove o blur de uma mídia específica do feed.
 * @param {number|string} feedItemId - ID do feed item
 */
function unblurMedia(feedItemId) {
    const wrapper = document.querySelector('.media-wrapper-' + feedItemId);
    if (wrapper) {
        wrapper.classList.remove('media-blur-container');
        const mediaElements = wrapper.querySelectorAll('.media-blur');
        mediaElements.forEach(function (el) {
            el.classList.remove('media-blur');
            if (el.tagName === 'VIDEO') {
                el.style.pointerEvents = 'auto';
            }
        });
        const overlay = wrapper.querySelector('.media-overlay-msg');
        if (overlay) {
            overlay.style.display = 'none';
        }

        // Sincronização com Lightbox: Marcar como desbloqueado
        const trigger = wrapper.querySelector('.lightbox-trigger');
        if (trigger) {
            trigger.dataset.aiUnlocked = 'true';
        }
    }
}

/**
 * Remove o blur de um álbum específico.
 * @param {HTMLElement} button - Botão clicado dentro do container do álbum
 */
function unblurAlbum(button) {
    const container =
        button.closest('.album-blur-container') ||
        button.closest('.lightbox-trigger');
    if (container) {
        container.classList.remove('album-blur-container');
        const blurredElements = container.querySelectorAll('.album-blur, .album-cover-image');
        blurredElements.forEach(function (el) {
            el.classList.remove('album-blur');
            el.style.filter = 'none';
            el.style.pointerEvents = 'auto';
        });
        const overlay = container.querySelector('.album-overlay-msg');
        if (overlay) {
            overlay.style.display = 'none';
        }
        const link = container.querySelector('.album-cover-link');
        if (link) {
            link.style.pointerEvents = 'auto';
        }
    }
}


// ============================================================
// MODAL DE VERIFICAÇÃO DE CONTA
// ============================================================

/**
 * Abre o modal de convite para verificação
 */
function openVerificationInviteModal() {
    const modal = document.getElementById('verificationInviteModal');
    if (modal) {
        modal.style.display = 'flex';
        // Forçar reflow para animação funcionar
        modal.offsetHeight;
        modal.classList.add('active');
        document.body.style.overflow = 'hidden'; // Prevenir scroll do body
    } else {
        console.error('Modal de verificação não encontrado: verificationInviteModal');
    }
}

/**
 * Fecha o modal de convite para verificação
 */
function closeVerificationInviteModal() {
    const modal = document.getElementById('verificationInviteModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(function () {
            modal.style.display = 'none';
            document.body.style.overflow = ''; // Restaurar scroll
        }, 300); // Aguardar transição CSS
    }
}

/**
 * Redireciona para a página de verificação
 */
function proceedToVerification() {
    // Fechar modal primeiro
    closeVerificationInviteModal();

    // Redirecionar para página de verificação
    window.location.href = window.BASE_URL + 'verification/index.php';
}

// Fechar modal ao clicar fora do conteúdo ou com tecla ESC
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('verificationInviteModal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeVerificationInviteModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeVerificationInviteModal();
            }
        });
    }
});


// ============================================================
// MODAL HOIST — Portal pattern
// Move os modais para document.body como filhos directos.
// Qualquer ancestral com transform / overflow / will-change / isolation
// cria um stacking context que aprisiona position:fixed dentro dele,
// impedindo o modal de cobrir o ecrã inteiro.
// Mover para body elimina todos esses ancestrais problemáticos.
// ============================================================
(function hoistModalsToBody() {
    var ids = ['feedLightbox', 'verificationInviteModal', 'verificationModal'];

    function hoist() {
        ids.forEach(function (id) {
            var el = document.getElementById(id);
            if (el && el.parentElement !== document.body) {
                document.body.appendChild(el);
            }
        });
    }

    // Executar logo no DOMContentLoaded, antes de qualquer interacção
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hoist);
    } else {
        hoist();
    }
})();
