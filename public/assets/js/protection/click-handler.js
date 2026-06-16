/**
 * Click Handler for Posts - massango
 * VERSÃO CORRIGIDA v3.2: Intercepta vídeos à venda para usuários verificados E não verificados
 * Ordem de prioridade:
 * 1. Vídeos à venda bloqueados + usuário verificado -> checkout modal
 * 2. Vídeos à venda bloqueados + usuário NÃO verificado -> modal de verificação
 * 3. Vídeos (todos os outros) -> premium_lightbox.js (REELS)
 * 4. Posts/Fotos/Álbuns -> post.php modal ou checkout
 */

(function () {
    'use strict';

    function initClickHandler() {
        // Handler em CAPTURING phase (true) para executar ANTES do premium_lightbox.js
        document.body.addEventListener('click', function (e) {

            if (e._adultContentHandled) return;
            if (e.defaultPrevented) return;

            // === PRIORIDADE 1 & 2: Vídeo à venda bloqueado (verificado ou não) ===
            const videoTrigger = e.target.closest('.video-locked.lightbox-trigger[data-type="video"], .video-locked[data-type="video"], .lightbox-trigger.video-locked[data-type="video"]');

            if (videoTrigger) {
                const isForSale = videoTrigger.dataset.isForSale === 'true';
                const hasAccess = videoTrigger.dataset.hasAccess === 'true';
                const isVerified = videoTrigger.dataset.isVerified === 'true';
                const isPostOwner = videoTrigger.dataset.isPostOwner === 'true';
                const checkoutUrl = videoTrigger.dataset.checkoutUrl;

                // Se é à venda, bloqueado, e não é dono
                if (isForSale && !hasAccess && !isPostOwner) {

                    // Usuário VERIFICADO -> vai para checkout
                    if (isVerified && checkoutUrl) {
                        e.preventDefault();
                        e.stopImmediatePropagation();

                        console.log('Click Handler: Abrindo checkout para vídeo à venda (usuário verificado)');

                        if (typeof pageModalLoader !== 'undefined' && pageModalLoader.open) {
                            pageModalLoader.open(checkoutUrl);
                        } else {
                            window.location.href = checkoutUrl;
                        }
                        return;
                    }

                    // Usuário NÃO VERIFICADO -> abre modal de verificação
                    if (!isVerified) {
                        e.preventDefault();
                        e.stopImmediatePropagation();

                        console.log('Click Handler: Abrindo modal de verificação (usuário não verificado)');

                        if (typeof openVerificationInviteModal === 'function') {
                            openVerificationInviteModal();
                        } else {
                            // Fallback: redireciona para página de verificação
                            window.location.href = (window.BASE_URL || '') + './verifications/index.php';
                        }
                        return;
                    }
                }
            }

            // === PRIORIDADE 3: Vídeos normais (não bloqueados) ===
            const normalVideo = e.target.closest('.lightbox-trigger[data-type="video"]:not(.video-locked), .lightbox-trigger[data-item-type="video"]:not(.video-locked)');
            if (normalVideo) {
                return; // Deixa o premium_lightbox.js capturar
            }

            // === PRIORIDADE 4: Posts/Fotos/Álbuns bloqueados (não vídeos) ===
            const lockedContent = e.target.closest('.album-locked, .post-locked');
            if (lockedContent) {
                if (lockedContent.querySelector('video') || lockedContent.dataset.type === 'video') {
                    return;
                }

                const isVerified = window.IS_VERIFIED_CREATOR || false;

                if (isVerified) {
                    e.preventDefault();
                    e.stopPropagation();

                    const itemId = lockedContent.dataset.itemId ||
                        lockedContent.closest('article')?.dataset.feedItemId;
                    const itemType = lockedContent.dataset.itemType || 'post';
                    const checkoutUrl = (window.BASE_URL || '') + `checkout.php?type=${itemType}&id=${itemId}`;

                    if (typeof pageModalLoader !== 'undefined' && pageModalLoader.open) {
                        pageModalLoader.open(checkoutUrl);
                    } else {
                        window.location.href = checkoutUrl;
                    }
                    return;
                } else {
                    // Usuário não verificado clicou em foto/post bloqueado
                    e.preventDefault();
                    e.stopPropagation();

                    if (typeof openVerificationInviteModal === 'function') {
                        openVerificationInviteModal();
                    } else {
                        window.location.href = (window.BASE_URL || '') + 'actions/verification.php';
                    }
                    return;
                }
            }

            // === PRIORIDADE 5: Posts/Fotos/Álbuns com acesso ===
            const trigger = e.target.closest('.post-image-container, .album-placeholder-link, .post-image');
            if (!trigger) return;

            if (trigger.querySelector('video') || trigger.dataset.type === 'video') {
                return;
            }

            const postCard = trigger.closest('.post-card, article[data-feed-item-id]');
            if (!postCard) return;

            const feedItemId = postCard.dataset.feedItemId;
            if (!feedItemId) return;

            e.preventDefault();
            e.stopPropagation();

            openPostModal(feedItemId);

        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initClickHandler);
    } else {
        initClickHandler();
    }

    function openPostModal(feedItemId) {
        if (!feedItemId) return;
        const modalUrl = 'post.php?id=' + encodeURIComponent(feedItemId);

        if (window.postModalLoader && typeof window.postModalLoader.open === 'function') {
            window.postModalLoader.open(modalUrl);
        } else if (typeof postModalLoader !== 'undefined' && postModalLoader.open) {
            postModalLoader.open(modalUrl);
        } else {
            window.location.href = modalUrl;
        }
    }

    window.openPostModal = openPostModal;

})();