/**
 * PATCH: premium_lightbox.js — lazy-loading real de vídeo (poupança de dados)
 * ============================================================================
 *
 * PROBLEMA IDENTIFICADO:
 *   renderReelItem() imprime <video src="${item.src}" preload="auto"> para
 *   TODOS os itens de uma vez (renderReels faz .map().join('') sobre a
 *   lista toda). O browser começa a pedir o ficheiro de vídeo de cada
 *   <video> inserido no DOM imediatamente, independentemente do que
 *   loadReel() faz depois — preload="auto" é uma forte sugestão ao
 *   browser para começar o download assim que possível.
 *
 *   loadReel() já fazia pause()+removeAttribute('src') nos vídeos não
 *   ativos, mas isso corre DEPOIS do HTML já estar no DOM — nessa altura
 *   o browser já pode ter iniciado vários downloads em paralelo.
 *
 * CORREÇÃO:
 *   1. renderReelItem() deixa de imprimir src= real. Passa a usar
 *      data-src (mesmo padrão já usado no grid de reels, _card.php).
 *      O atributo `poster` mantém-se real (é só uma imagem, leve,
 *      e é isso que dá feedback visual instantâneo ao abrir).
 *   2. loadReel() passa a gerir uma JANELA de 3 índices (anterior,
 *      atual, seguinte): só esses recebem `video.src` real. Todos os
 *      outros são garantidos sem src (e com .load() chamado, que limpa
 *      qualquer buffer pendente do browser).
 *
 * Substituir as duas funções abaixo no ficheiro original. O resto da
 * classe ReelsManager fica inalterado.
 * ============================================================================
 */

// ─────────────────────────────────────────────────────────────────────────
// 1. renderReelItem() — alteração mínima: src real -> data-src
// ─────────────────────────────────────────────────────────────────────────
//
// ANTES:
//   <video class="reels-video ${sensitiveClass}"
//          src="${this.escapeHtml(item.src)}"
//          loop playsinline preload="auto"
//          ...
//
// DEPOIS (única linha alterada — tudo o resto da função fica igual):
//   <video class="reels-video ${sensitiveClass}"
//          data-src="${this.escapeHtml(item.src)}"
//          loop playsinline preload="none"
//          ...
//
// Nota: preload também passa de "auto" para "none" — sem src, "auto"
// não tem efeito prático, mas "none" deixa a intenção explícita e
// remove qualquer ambiguidade entre browsers.


// ─────────────────────────────────────────────────────────────────────────
// 2. loadReel() — gere uma janela de 3 índices em vez de só 1
// ─────────────────────────────────────────────────────────────────────────

loadReel() {
    const videos = this.scrollContainer.querySelectorAll('video.reels-video');
    const currentItem = this.state.currentItems[this.state.currentIndex];
    if (!currentItem) return;

    console.log(`[Reels] loadReel #${this.state.currentIndex}, duration: ${currentItem.duration}s`);

    this.stopAllPreviews();

    // Janela de pré-carga: anterior, atual, seguinte. Os restantes
    // ficam sempre sem src real — é aqui que a poupança de dados
    // acontece de facto (antes, todos os vídeos da lista tinham src).
    const preloadIndexes = new Set([
        this.state.currentIndex - 1,
        this.state.currentIndex,
        this.state.currentIndex + 1,
    ]);

    videos.forEach((v, idx) => {
        const shouldPreload = preloadIndexes.has(idx);

        if (!shouldPreload) {
            // Fora da janela: garantir que não tem src real carregado.
            if (v.hasAttribute('src') || v.src) {
                v.pause();
                v.removeAttribute('src');
                v.load();
            }
            v.currentTime = 0;
            v.style.filter = '';
            v.ontimeupdate = null;
            return;
        }

        if (idx === this.state.currentIndex) {
            // O item ativo é tratado em detalhe mais abaixo (play, eventos, etc.)
            return;
        }

        // Vizinho (anterior ou seguinte): só garante que tem o src real
        // atribuído, SEM dar play. Fica pronto para quando o utilizador
        // avançar/recuar, sem novo round-trip de rede nesse momento.
        const neighborItem = this.state.currentItems[idx];
        if (neighborItem && (!v.src || v.src !== neighborItem.src)) {
            v.pause();
            v.src = neighborItem.src;
            v.load();
            v.muted = true; // vizinhos nunca tocam som
        }
    });

    const currentVideo = videos[this.state.currentIndex];
    if (!currentVideo) return;

    const isLocked = (currentItem.isAdultContent && !this.state.userAgeVerified) ||
        (currentItem.isForSale && !currentItem.hasAccess && !currentItem.isPostOwner);

    if (isLocked) {
        currentVideo.removeAttribute('src');
        currentVideo.load();
        currentVideo.style.filter = 'blur(15px)';

        if (currentItem.isForSale && !currentItem.hasAccess) {
            setTimeout(() => this.initPreview(currentItem), 150);
        }

        this.sendViewRequest(currentItem.itemType, currentItem.itemId);

        if (this.state.sidebarOpen) {
            this.loadComments();
        }
        return;
    }

    if (!currentVideo.src || currentVideo.src !== currentItem.src) {
        currentVideo.src = currentItem.src;
    }

    currentVideo.style.filter = '';
    currentVideo.muted = this.state.isMuted;

    currentVideo.onloadedmetadata = () => {
        // ── (inalterado a partir daqui — mesma lógica de aspect-ratio/wrapper) ──
        const wrapper = currentVideo.closest('.reel-video-wrapper');
        const isMobile = window.innerWidth <= 767;
        const isLandscapeVideo = currentVideo.videoWidth > currentVideo.videoHeight;

        if (wrapper && currentVideo.videoWidth && currentVideo.videoHeight) {
            if (isLandscapeVideo && !isMobile) {
                const gutter = '200px';
                wrapper.classList.add('is-landscape');
                wrapper.style.cssText = `
                    aspect-ratio: 16/9;
                    width: min(calc(100vw - ${gutter}), calc(90vh * 16 / 9));
                    height: min(90vh, calc((100vw - ${gutter}) * 9 / 16));
                    max-width: calc(100vw - ${gutter});
                    max-height: 90vh;
                    flex-shrink: 0;
                    position: relative;
                    overflow: hidden;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                `;
                currentVideo.style.cssText = `
                    width: 100%;
                    height: 100%;
                    max-width: 100%;
                    max-height: 100%;
                    object-fit: cover;
                    object-position: center;
                    display: block;
                    background: #000;
                    border-radius: 0;
                    margin: 0;
                    padding: 0;
                    border: none;
                `;
            } else if (isLandscapeVideo && isMobile) {
                wrapper.classList.remove('is-landscape');
                wrapper.style.cssText = `
                    width: 100%;
                    max-width: 100%;
                    height: 100svh;
                    height: 100dvh;
                    max-height: 100svh;
                    border-radius: 0;
                    overflow: hidden;
                    position: relative;
                `;
                currentVideo.style.cssText = `
                    width: 100%;
                    height: 100%;
                    max-height: none;
                    object-fit: cover;
                    object-position: center;
                    display: block;
                    background: #000;
                    margin: 0;
                    padding: 0;
                    border: none;
                `;
            } else {
                wrapper.classList.remove('is-landscape');
                if (isMobile) {
                    wrapper.style.cssText = `
                        width: 100%;
                        max-width: 100%;
                        height: 100svh;
                        height: 100dvh;
                        max-height: 100svh;
                        border-radius: 0;
                        overflow: hidden;
                        position: relative;
                    `;
                    currentVideo.style.cssText = `
                        width: 100%;
                        height: 100%;
                        max-height: none;
                        object-fit: cover;
                        background: #000;
                        margin: 0;
                        padding: 0;
                        border: none;
                    `;
                } else {
                    wrapper.style.cssText = '';
                    currentVideo.style.cssText = `
                        display: block;
                        background: #000;
                        margin: 0;
                        padding: 0;
                        border: none;
                    `;
                }
            }
        }
        currentVideo.play().catch(() => this.updatePlayIcon(false));
    };

    this.setupProgressBar(currentVideo);

    currentVideo.onplay = () => {
        this.sendViewRequest(currentItem.itemType, currentItem.itemId);
    };

    if (this.state.sidebarOpen) {
        this.loadComments();
    }
}
