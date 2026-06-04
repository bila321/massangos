// Proteção de Conteúdo Avançada - Massango
(function() {
    'use strict';

    // Configurações de proteção
    const CONFIG = {
        disableRightClick: false, // Desativado a pedido do usuário
        disableTextSelection: false, // Desativado a pedido do usuário
        disableImageDrag: false, // Desativado a pedido do usuário
        disableKeyboardShortcuts: false, // Desativado a pedido do usuário
        disablePrintScreen: false, // Desativado a pedido do usuário
        disableDevTools: false, // Desativado a pedido do usuário
        showWarningMessages: false,
        logAttempts: true,
        enableFloatingWatermark: true, // Marca d'água flutuante sobre mídias
        protectVideos: true
    };

    // Log de tentativas de acesso
    function logSecurityAttempt(type, details = '') {
        if (CONFIG.logAttempts) {
            const timestamp = new Date().toISOString();
            console.warn(`[SECURITY] ${timestamp} - ${type}: ${details}`);
        }
    }

    /**
     * Implementa marca d'água flutuante que se move apenas sobre o elemento pai (imagem ou vídeo)
     */
    function addFloatingWatermarkToElement(element) {
        if (!CONFIG.enableFloatingWatermark) return;
        
        // Só adicionar marca d'água se for conteúdo pago (data-is-paid="true")
        const isPaid = element.getAttribute('data-is-paid') === 'true';
        if (!isPaid) return;
        
        const wrapper = element.parentElement;
        if (!wrapper || wrapper.querySelector('.floating-watermark-local')) return;

        // Garantir que o wrapper tenha posição relativa
        const computedStyle = window.getComputedStyle(wrapper);
        if (computedStyle.position === 'static') {
            wrapper.style.position = 'relative';
        }
        wrapper.style.overflow = 'hidden';

        const watermark = document.createElement('div');
        watermark.className = 'floating-watermark-local';
        
        const userInfo = document.body.getAttribute('data-user-info') || 'Massango Protected';
        watermark.textContent = userInfo;
        
        watermark.style.cssText = `
            position: absolute;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.3);
            pointer-events: none;
            z-index: 20;
            white-space: nowrap;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            font-family: sans-serif;
            user-select: none;
            background: rgba(0,0,0,0.1);
            padding: 2px 5px;
            border-radius: 4px;
            transition: opacity 0.3s;
        `;

        wrapper.appendChild(watermark);

        let posX = Math.random() * (wrapper.offsetWidth - 100);
        let posY = Math.random() * (wrapper.offsetHeight - 30);
        let velX = (Math.random() > 0.5 ? 1 : -1) * (0.5 + Math.random());
        let velY = (Math.random() > 0.5 ? 1 : -1) * (0.5 + Math.random());

        function move() {
            if (!watermark.parentElement) return;

            const parentW = wrapper.offsetWidth;
            const parentH = wrapper.offsetHeight;
            const selfW = watermark.offsetWidth;
            const selfH = watermark.offsetHeight;

            posX += velX;
            posY += velY;

            if (posX <= 0 || posX >= parentW - selfW) velX *= -1;
            if (posY <= 0 || posY >= parentH - selfH) velY *= -1;

            // Ajuste de segurança se o container mudar de tamanho
            if (posX < 0) posX = 0;
            if (posY < 0) posY = 0;
            if (posX > parentW - selfW) posX = parentW - selfW;
            if (posY > parentH - selfH) posY = parentH - selfH;

            watermark.style.left = posX + 'px';
            watermark.style.top = posY + 'px';

            requestAnimationFrame(move);
        }

        move();
    }

    /**
     * Adiciona overlay transparente para impedir "Salvar como" e clique direito direto na mídia
     */
    function addProtectionOverlay(element) {
        const wrapper = element.parentElement;
        if (!wrapper || wrapper.querySelector('.media-protection-overlay')) return;

        const computedStyle = window.getComputedStyle(wrapper);
        if (computedStyle.position === 'static') {
            wrapper.style.position = 'relative';
        }

        const overlay = document.createElement('div');
        overlay.className = 'media-protection-overlay';
        overlay.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 15;
            background: rgba(0,0,0,0);
            cursor: default;
        `;

        // Bloquear menu de contexto apenas no overlay para impedir "Salvar como"
        overlay.addEventListener('contextmenu', e => {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }, true);

        wrapper.appendChild(overlay);
    }

    // Proteção de Mídias (Imagens e Vídeos)
    function protectMedia() {
        // Selecionar imagens e vídeos que devem ser protegidos
        const mediaElements = document.querySelectorAll('img.post-image, img.album-image, video');
        
        mediaElements.forEach(el => {
            if (el.getAttribute('data-enhanced-protected')) return;
            
            // Adicionar overlay e marca d'água
            addProtectionOverlay(el);
            addFloatingWatermarkToElement(el);
            
            // Configurações específicas para vídeo
            if (el.tagName === 'VIDEO') {
                el.setAttribute('controlsList', 'nodownload');
                el.setAttribute('disablePictureInPicture', 'true');
                // Desativar menu nativo no próprio elemento também
                el.addEventListener('contextmenu', e => e.preventDefault());
            }

            el.setAttribute('data-enhanced-protected', 'true');
        });
    }

    // Inicialização
    function init() {
        protectMedia();
        // Observar novos elementos (scroll infinito, modais)
        const observer = new MutationObserver(() => {
            protectMedia();
        });
        observer.observe(document.body, { childList: true, subtree: true });
        
        // Intervalo de segurança para redimensionamentos
        setInterval(protectMedia, 3000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
