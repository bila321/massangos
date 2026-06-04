/**
 * Adult Content Handler - PRIORITÁRIO
 * Deve ser carregado ANTES de click-handler.js e premium_lightbox.js
 * Usa capturing phase para garantir execução primeiro
 */

(function () {
    'use strict';

    // Handler em CAPTURING phase no document - executa antes de TODOS os outros
    document.addEventListener('click', function (e) {

        // Verifica se clicou no botão de unblur ou em seu container
        const unblurBtn = e.target.closest('.unblur-btn, [data-action="unblur-adult"]');
        const adultOverlay = e.target.closest('[data-adult-overlay="true"]');

        if (!unblurBtn && !adultOverlay) {
            return; // Não é nosso alvo, deixa passar
        }

        // É NOSSO - para tudo imediatamente para evitar que outros scripts (como click-handler.js ou premium_lightbox.js) abram modais
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        // Marca o evento como tratado para que outros scripts saibam que não devem agir
        e._adultContentHandled = true;

        // Encontra o wrapper
        const wrapper = e.target.closest('.adult-content-wrapper');
        if (!wrapper) return;

        const wrapperId = wrapper.id;

        // Remove blur da mídia
        const media = wrapper.querySelector('.adult-blurred');
        if (media) {
            media.style.filter = 'none';
            media.style.webkitFilter = 'none';
            media.classList.remove('adult-blurred');
            media.classList.add('adult-unblurred');
            
            // Se for uma imagem dentro de um container que tem blur via style, remove também
            if (media.parentElement && media.parentElement.classList.contains('post-image-container')) {
                media.parentElement.style.filter = 'none';
            }
        }

        // Remove o overlay com fade
        const overlay = wrapper.querySelector('.adult-overlay');
        if (overlay) {
            overlay.style.transition = 'opacity 0.3s ease';
            overlay.style.opacity = '0';
            setTimeout(() => overlay.remove(), 300);
        }

        // Marca como revelado
        wrapper.dataset.adultRevealed = 'true';

        // Se for vídeo, tenta reproduzir
        if (media && media.tagName === 'VIDEO') {
            media.play().catch(() => { });
        }

        console.log('[AdultContent] Unblurred inline:', wrapperId);

    }, true); // CAPTURING phase = primeiro a executar

    // Expõe função global para uso manual se necessário
    window.unblurAdultContent = function (element) {
        const wrapper = element.closest('.adult-content-wrapper');
        if (wrapper && !wrapper.dataset.adultRevealed) {
            // Simula um clique no overlay para trigger o handler acima
            const overlay = wrapper.querySelector('.adult-overlay');
            if (overlay) {
                const event = new MouseEvent('click', {
                    bubbles: true,
                    cancelable: true,
                    view: window
                });
                overlay.dispatchEvent(event);
            }
        }
    };

})();
