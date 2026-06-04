/**
 * Proteção Avançada de Mídia - Massango
 * Gerencia ofuscação de URLs, tokens dinâmicos e proteção de elementos de mídia.
 */
(function() {
    'use strict';

    const CONFIG = {
        enableUrlObfuscation: true,
        enableWatermarks: true,
        enableTokenAuth: true,
        tokenExpiry: 3600000, // 1 hora
        proxyPath: 'media-proxy.php'
    };

    const tokenCache = new Map();

    /**
     * Gera um token de acesso compatível com o backend.
     */
    function generateAccessToken(mediaId) {
        const timestamp = Date.now();
        const random = Math.random().toString(36).substring(2, 10);
        // Formato: mediaId:timestamp:random
        const token = btoa(`${mediaId}:${timestamp}:${random}`);
        
        tokenCache.set(mediaId, {
            token,
            expires: timestamp + CONFIG.tokenExpiry
        });
        
        return token;
    }

    /**
     * Ofusca a URL original transformando-a em uma chamada ao proxy.
     */
    function obfuscateMediaUrl(originalUrl) {
        if (!originalUrl || originalUrl.includes(CONFIG.proxyPath) || originalUrl.startsWith('data:')) {
            return originalUrl;
        }

        // Extrair o caminho relativo da imagem
        let relativePath = originalUrl;
        if (originalUrl.includes('/uploads/')) {
            relativePath = originalUrl.split('/uploads/')[1];
        }

        const mediaId = btoa(relativePath).replace(/=/g, '');
        const token = generateAccessToken(mediaId);
        
        // Construir URL do proxy
        const baseUrl = window.location.origin + '/massangos/public';
        // Adicionar um parâmetro aleatório para evitar cache agressivo do navegador em mídias protegidas
        return `${baseUrl}/${CONFIG.proxyPath}?id=${mediaId}&t=${token}&v=${Date.now()}`;
    }

    /**
     * Aplica proteção a elementos de imagem e vídeo.
     */
    function protectMediaElements() {
        // Proteger Imagens
        document.querySelectorAll('img:not([data-protected])').forEach(img => {
            if (img.src && (img.src.includes('/uploads/') || img.classList.contains('post-image') || img.classList.contains('album-photo-thumb') || img.classList.contains('album-cover') || img.classList.contains('post-video-thumbnail') || img.classList.contains('album-cover-image'))) {
                const protectedUrl = obfuscateMediaUrl(img.src);
                img.src = protectedUrl;
                img.setAttribute('data-protected', 'true');
                img.style.webkitUserDrag = 'none';
                img.style.userSelect = 'none';
                // img.addEventListener('contextmenu', e => e.preventDefault());
            }
        });

        // Proteger Vídeos
        document.querySelectorAll('video:not([data-protected])').forEach(video => {
            if (video.src || video.querySelector('source')) {
                const source = video.querySelector('source') || video;
                if (source.src && source.src.includes('/uploads/')) {
                    source.src = obfuscateMediaUrl(source.src);
                }
                
                video.setAttribute('data-protected', 'true');
                video.setAttribute('controlsList', 'nodownload nofullscreen noremoteplayback');
                video.setAttribute('disablePictureInPicture', 'true');
                // video.addEventListener('contextmenu', e => e.preventDefault()); // Removido a pedido do usuário
                
	                // Overlay de proteção removido para permitir interações (play/pause/comentários)
	                /*
	                if (video.parentElement && !video.parentElement.querySelector('.video-protection-overlay')) {
	                    const overlay = document.createElement('div');
	                    overlay.className = 'video-protection-overlay';
	                    video.parentElement.style.position = 'relative';
	                    video.parentElement.appendChild(overlay);
	                }
	                */
                
                // Impedir clique direito no vídeo para "Salvar vídeo como"
                video.style.pointerEvents = 'auto';
            }
        });
    }

    // Inicialização
    document.addEventListener('DOMContentLoaded', () => {
        protectMediaElements();
        
        // Observar mudanças no DOM para proteger novos elementos (como em modais ou scroll infinito)
        const observer = new MutationObserver(() => {
            protectMediaElements();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    });

    // Expor função globalmente se necessário
    window.MassangoProtection = {
        obfuscateUrl: obfuscateMediaUrl,
        refresh: protectMediaElements
    };

})();

