/**
 * Ultra Premium Reels Lightbox System - Facebook Reels Style
 * VERSAO CORRIGIDA v4.7: Mobile landscape fullscreen fix
 *
 * CORRECÇÕES v4.7 (adicionadas a v4.6):
 *  - FIX 21: Vídeos landscape em mobile (≤767px) usam fullscreen cover (igual ao portrait).
 *            A caixa 16:9 em 375px dava ~211px de altura — inaceitável.
 *            renderReelItem: wrapperInlineStyle não aplica 16:9 em mobile.
 *            loadReel: novo branch isLandscapeVideo && isMobile → fullscreen cover.
 *            CSS: garantia extra via @media (max-width:767px) em .is-landscape.
 *
 * CORRECÇÕES v4.6:
 *  - FIX 10: Propriedades duplicadas em buildItemData (id, aiStatus, aiRisk, aiScore)
 *  - FIX 11: Race condition setupProgressBar vs ontimeupdate sensível — unificados
 *  - FIX 12: <style>@keyframes inline por cada reel → movido para CSS (memory leak)
 *  - FIX 13: handleLockedReelClick + handleLockedVideoClickFromFeed → _resolveLockedContent
 *  - FIX 14: copyToClipboard como método da classe (antes: TypeError em runtime)
 *  - FIX 15: filterLightboxContent implementada (antes: corpo vazio)
 *  - FIX 16: openSidebar() removida — duplicação de toggleSidebar()
 *  - FIX 17: AbortController em loadComments — cancela requests obsoletos
 *  - FIX 18: scrollToReelByOffset — loadReel adiado 650ms (evita conflito scroll)
 *  - FIX 19: Funções globais sem #feedLightbox → stubs com aviso (evita TypeError)
 *  - FIX 20: deletePost → remove do DOM/estado sem window.location.reload()
 *
 * @version 4.7 - MOBILE LANDSCAPE FULLSCREEN + BUG FIXES
 */

// ============================================
// GUARDA CONTRA CARGA DUPLA
// ============================================
if (window.reelsManagerInstance) {
    console.log('[ReelsManager] Instância já existe. A abortar carga duplicada.');
} else {

    class ReelsManager {
        constructor() {
            this.lightbox = document.getElementById('feedLightbox');
            this.scrollContainer = document.getElementById('lightboxScrollContainer');
            this.sidebar = document.querySelector('.photo-sidebar');
            this.commentForm = document.getElementById('lightboxCommentForm');
            this.commentInput = document.getElementById('lightboxCommentInput');
            this.commentsArea = document.getElementById('lightboxCommentsArea');

            // FIX 17: AbortController para cancelar fetch de comentários ao navegar
            this._commentsAbortController = null;

            this.state = {
                currentItems: [],
                currentIndex: 0,
                isScrolling: false,
                isMuted: true,
                volume: 0.5,
                sidebarOpen: false,
                currentFilter: 'all',
                isLoading: false,
                userVerified: window.IS_VERIFIED_CREATOR || false,
                userAgeVerified: false,
                activePreviews: new Map(),
                viewedItems: new Set(),
                unlocked_content: {}
            };

            if (this.lightbox && this.scrollContainer) {
                this.init();  // ← só inicia se o #feedLightbox existir na página
            }
        }

        init() {
            this.setupVideoCapture();
            this.setupEventDelegation();
            this.setupKeyboardShortcuts();
            this.setupScrollListener();
            this.setupResizeListener();
            this.setupCommentForm();
            this.setupSidebarToggle();
            this.exposeGlobalFunctions();
        }

        setupVideoCapture() {
            document.addEventListener('click', (e) => {
                if (e._adultContentHandled || e.defaultPrevented) return;

                const trigger = e.target.closest('.lightbox-trigger[data-type="video"], .lightbox-trigger[data-item-type="video"], .video-locked[data-type="video"]');
                if (!trigger) return;

                const isForSale = trigger.dataset.isForSale === 'true' || trigger.classList.contains('video-locked');
                const hasAccess = trigger.dataset.hasAccess === 'true';
                const isPostOwner = trigger.dataset.isPostOwner === 'true';

                if (isForSale && !hasAccess && !isPostOwner) return;

                const isVideo = trigger.dataset.type === 'video' || trigger.dataset.itemType === 'video';
                if (!isVideo) return;

                // [MASSANGOS FIX] 2-Step Unlock: Se for vídeo sensível e NÃO estiver desbloqueado no feed, não abre lightbox
                const isSensitive = trigger.dataset.aiStatus === 'done' && trigger.dataset.aiRisk !== 'low';
                const isUnlocked = trigger.dataset.aiUnlocked === 'true';

                if (isSensitive && !isUnlocked && !isPostOwner) {
                    console.log('[Reels] Vídeo sensível bloqueado no feed. Aguardando unblur.');
                    return; // Deixa o clique passar para o unblurMedia do index.php
                }

                e.stopImmediatePropagation();
                e.preventDefault();
                this.openReels(trigger);
            }, true);
        }

        exposeGlobalFunctions() {
            window.closeLightbox = () => this.closeLightbox();
            window.scrollToReelByOffset = (offset) => this.scrollToReelByOffset(offset);
            window.toggleGlobalMute = () => this.toggleGlobalMute();
            window.toggleSidebar = () => this.toggleSidebar();
            window.filterLightboxContent = (category) => this.filterLightboxContent(category);
            // window.toggleFollow removido para evitar conflito com App.toggleFollow
            window.toggleReelsComments = (event) => this.toggleReelsComments(event);
            window.handleRepost = (feedItemId) => this.handleRepost(feedItemId);
            window.togglePremiumShareMenu = (feedItemId) => this.toggleShareMenu(feedItemId);
            window.deletePost = (postId) => this.deletePost(postId);
            // FIX 14: copyToClipboard agora é método da classe; antes era exposta mas não existia
            window.copyToClipboard = (text, id) => this.copyToClipboard(text, id);
            window.handleBuyClick = (itemId, itemType, price) => this.handleBuyClick(itemId, itemType, price);
            window.verifyAge = (btn) => this.verifyAge(btn);
            window.unblurContent = (btn) => this.unblurContent(btn);
            window.unlockSensitiveContent = (postId) => this.unlockSensitiveContent(postId);
            // NOVO: Handler para clique em vídeo bloqueado no reels
            window.handleLockedReelClick = (element) => this.handleLockedReelClick(element);
            // NOVO: Handler para clique em vídeo bloqueado no feed (index.php)
            window.handleLockedVideoClickFromFeed = (element) => this.handleLockedVideoClickFromFeed(element);
            // Compatibilidade com nomes antigos usados no PHP
            window.handleLockedVideoClick = (element) => this.handleLockedVideoClickFromFeed(element);
        }

        /**
         * FIX 14: Método de cópia para clipboard — antes exposto globalmente mas não definido
         * como método da classe, causando TypeError em runtime.
         */
        copyToClipboard(text, feedItemId) {
            if (!navigator.clipboard) {
                // Fallback para browsers sem suporte à Clipboard API
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none;';
                document.body.appendChild(ta);
                ta.select();
                try {
                    document.execCommand('copy');
                    this.showToast('Link copiado!');
                } catch {
                    this.showToast('Não foi possível copiar o link', 'error');
                }
                ta.remove();
                return;
            }
            navigator.clipboard.writeText(text).then(() => {
                this.showToast('Link copiado!');
                // Fecha o dropdown de partilha após copiar
                if (feedItemId) this.toggleShareMenu(feedItemId);
            }).catch(() => {
                this.showToast('Não foi possível copiar o link', 'error');
            });
        }

        /**
         * FIX 15: filterLightboxContent — antes exposta mas não implementada.
         * Filtra os itens visíveis no lightbox pela categoria indicada.
         */
        filterLightboxContent(category) {
            this.state.currentFilter = category || 'all';
            const filtered = category === 'all'
                ? this.state.currentItems
                : this.state.currentItems.filter(item => item.category === category);
            if (filtered.length === 0) {
                this.showToast('Sem conteúdo para esta categoria', 'error');
                return;
            }
            this.renderReels(filtered);
            this.state.currentIndex = 0;
            this.scrollToReel(0, false);
            this.loadReel();
        }

        handleLockedReelClick(element) {
            // FIX 13: delega para método privado partilhado (elimina 90% de código duplicado)
            this._resolveLockedContent(element, false);
        }

        /**
         * NOVO: Handler para clique em vídeo bloqueado vindo do Feed (index.php)
         */
        handleLockedVideoClickFromFeed(element) {
            sessionStorage.setItem('from_feed_open', 'true');
            // FIX 13: delega para método privado partilhado
            this._resolveLockedContent(element, true);
        }

        /**
         * FIX 13: Método privado partilhado — elimina duplicação entre
         * handleLockedReelClick e handleLockedVideoClickFromFeed.
         * @param {Element} element — elemento com data-* de checkout
         * @param {boolean} fromFeed — se true, redireciona para process_verification em vez de verification/index.php
         */
        _resolveLockedContent(element, fromFeed) {
            const isVerified = element.dataset.isVerified === 'true' || window.IS_VERIFIED_CREATOR === true;
            let checkoutUrl = element.dataset.checkoutUrl;

            if (!checkoutUrl) {
                const itemId = element.dataset.itemId || element.dataset.id;
                const itemType = element.dataset.itemType || 'video';
                if (itemId) {
                    checkoutUrl = (window.BASE_URL || '') + `checkout.php?type=${itemType}&id=${itemId}`;
                }
            }

            if (isVerified && checkoutUrl) {
                if (typeof pageModalLoader !== 'undefined' && pageModalLoader.open) {
                    pageModalLoader.open(checkoutUrl);
                } else {
                    window.location.href = checkoutUrl;
                }
            } else {
                if (typeof openVerificationInviteModal === 'function') {
                    openVerificationInviteModal();
                } else {
                    const verifyPath = fromFeed ? 'actions/verification.php' : 'verification/index.php';
                    window.location.href = (window.BASE_URL || '') + verifyPath;
                }
            }
        }

        setupEventDelegation() {
            document.addEventListener('click', (e) => {
                const actionBtn = e.target.closest('[data-action]');
                if (actionBtn) {
                    this.handleInteractions(actionBtn.dataset.action, actionBtn);
                    return;
                }

                const trigger = e.target.closest('.lightbox-trigger');
                if (trigger && (trigger.dataset.type === 'video' || trigger.querySelector('video'))) {

                    // ✅ NOVO: Na página reels.php, o lightbox funciona normalmente
                    // (os cards já têm a lógica correcta de acesso no PHP)
                    // Não bloquear — apenas garantir que não abre modal se não houver lightbox
                    if (!this.lightbox) return; // sem modal na página = não abre nada

                    e.preventDefault();
                    e.stopPropagation();
                    this.openReels(trigger);
                    return;
                }

                // Fechar menus de share ao clicar fora
                const clickedShareItem = e.target.closest('.reels-share-item');

                if (!clickedShareItem) {
                    document.querySelectorAll('.reels-share-dropdown.is-open').forEach(menu => {
                        menu.classList.remove('is-open');
                        menu.setAttribute('aria-hidden', 'true');
                    });
                }

                if (this.lightbox?.classList.contains('active')) {
                    if (e.target === this.lightbox || e.target.classList.contains('photo-display-area')) {
                        this.closeLightbox();
                    }
                }
            });
        }

        /**
     * Setup keyboard shortcuts
     */
        setupKeyboardShortcuts() {
            document.addEventListener('keydown', (e) => {
                if (!this.lightbox?.classList.contains('active')) return;

                switch (e.key) {
                    case 'Escape':
                        if (this.state.sidebarOpen) {
                            this.closeSidebar();
                        } else {
                            this.closeLightbox();
                        }
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        this.scrollToReelByOffset(-1);
                        break;
                    case 'ArrowDown':
                        e.preventDefault();
                        this.scrollToReelByOffset(1);
                        break;
                    case ' ':
                        e.preventDefault();
                        this.toggleVideoPlayback();
                        break;
                    case 'm':
                    case 'M':
                        this.toggleGlobalMute();
                        break;
                    case 'c':
                    case 'C':
                        this.toggleSidebar();
                        break;
                }
            });
        }
        setupScrollListener() {
            let scrollTimeout;
            this.scrollContainer?.addEventListener('scroll', () => {
                if (this.state.isScrolling) return;
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => this.handleScroll(), 150);
            }, { passive: true });
        }

        handleScroll() {
            if (this.state.isScrolling) return;
            const itemHeight = this.scrollContainer.clientHeight;
            if (!itemHeight) return;

            const newIndex = Math.round(this.scrollContainer.scrollTop / itemHeight);
            if (newIndex !== this.state.currentIndex && newIndex >= 0 && newIndex < this.state.currentItems.length) {
                console.log(`[Reels] Scroll: ${this.state.currentIndex} -> ${newIndex}`);
                this.state.currentIndex = newIndex;
                this.loadReel();
            }
        }

        setupResizeListener() {
            let resizeTimeout;
            window.addEventListener('resize', () => {
                if (!this.lightbox?.classList.contains('active')) return;
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => this.scrollToReel(this.state.currentIndex, false), 200);
            });
        }

        setupCommentForm() {
            if (!this.commentForm) return;

            this.commentForm.onsubmit = async (e) => {
                e.preventDefault();
                const text = this.commentInput?.value.trim();
                if (!text || this.state.isLoading) return;

                this.state.isLoading = true;
                const item = this.state.currentItems[this.state.currentIndex];

                const formData = new FormData();
                formData.append('action', 'add_comment');
                formData.append('feed_item_id', item.id);
                formData.append('comment_content', text);

                try {
                    const response = await fetch('api/comments.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data.success) {
                        this.commentInput.value = '';
                        await this.loadComments();
                        this.updateCommentCount(item.id, data.comment_count || 0);
                        this.showToast('Comentário adicionado!');
                    }
                } catch (err) {
                    console.error('Error posting comment:', err);
                    this.showToast('Erro ao adicionar comentário', 'error');
                } finally {
                    this.state.isLoading = false;
                }
            };
        }

        setupSidebarToggle() {
            const sidebarCloseBtn = this.sidebar?.querySelector('.sidebar-close-btn');
            if (sidebarCloseBtn) {
                sidebarCloseBtn.addEventListener('click', () => this.closeSidebar());
            }
        }

        toggleReelsComments(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            this.toggleSidebar();
        }

        toggleSidebar() {
            if (!this.sidebar) return;
            this.state.sidebarOpen = !this.state.sidebarOpen;
            if (this.state.sidebarOpen) {
                this.sidebar.classList.add('sidebar-open');
                document.body.classList.add('sidebar-open');
                this.loadComments();
                setTimeout(() => this.commentInput?.focus(), 300);
            } else {
                this.closeSidebar();
            }
        }

        closeSidebar() {
            this.sidebar?.classList.remove('sidebar-open');
            document.body.classList.remove('sidebar-open');
            this.state.sidebarOpen = false;
        }

        // ============================================================
        // SISTEMA DE VIEWS
        // ============================================================

        sendViewRequest(itemType, itemId) {
            if (!itemId || itemId <= 0) return;

            const viewKey = `${itemType}-${itemId}`;
            if (this.state.viewedItems.has(viewKey)) return;

            const formData = new FormData();
            formData.append('item_type', itemType);
            formData.append('item_id', itemId);

            fetch(`${window.BASE_URL || ''}actions/view.php`, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && !data.already_counted) {
                        this.state.viewedItems.add(viewKey);
                        const item = this.state.currentItems[this.state.currentIndex];
                        if (item && item.itemId == itemId) {
                            item.viewsCount = data.new_count || (parseInt(item.viewsCount) + 1);
                            this.updateViewsDisplay(item);
                        }
                        this.updateFeedViewsCount(itemType, itemId, data.new_count);
                    }
                })
                .catch(error => {
                    console.debug('View tracking error:', error);
                });
        }

        updateViewsDisplay(item) {
            const reelItem = this.scrollContainer?.querySelector(`[data-feed-item-id="${item.id}"]`);
            if (!reelItem) return;

            const viewsEl = reelItem.querySelector('.video-locked-overlay .fa-eye + span');
            if (viewsEl) viewsEl.textContent = `${item.viewsCount} visualizações`;

            const infoViewsEl = reelItem.querySelector('.reels-views-count span');
            if (infoViewsEl) infoViewsEl.textContent = `${item.viewsCount} visualizações`;
        }

        updateFeedViewsCount(itemType, itemId, newCount) {
            const feedViewsEl = document.querySelector(`[data-views-id="${itemType}-${itemId}"]`);
            if (feedViewsEl) {
                feedViewsEl.textContent = `${newCount} visualizações`;
                return;
            }

            const card = document.querySelector(`.post-card video[data-item-id="${itemId}"]`)?.closest('.post-card');
            if (card) {
                const statsEl = card.querySelector('.video-stats');
                if (statsEl) {
                    const span = statsEl.querySelector('span');
                    if (span) span.textContent = `${newCount} visualizações`;
                }
            }
        }

        // ============================================================
        // OPEN REELS
        // ============================================================

        openReels(trigger) {
            const initialId = trigger.dataset.id;
            const allTriggers = Array.from(document.querySelectorAll('.lightbox-trigger')).filter(t =>
                t.dataset.type === 'video' || t.querySelector('video')
            );

            if (allTriggers.length === 0) return;

            this.state.currentItems = allTriggers.map(t => this.buildItemData(t)).filter(item => item !== null);
            this.state.currentIndex = this.state.currentItems.findIndex(item => item.id === initialId);
            if (this.state.currentIndex === -1) this.state.currentIndex = 0;

            console.log('[Reels] Aberto com', this.state.currentItems.length, 'itens. Índice atual:', this.state.currentIndex);

            this.renderReels();
            this.lightbox.style.display = 'flex';

            requestAnimationFrame(() => {
                this.lightbox.classList.add('active');
                document.body.classList.add('lightbox-open');
                setTimeout(() => {
                    this.scrollToReel(this.state.currentIndex, false);
                    this.loadReel();
                    this.state.sidebarOpen = false;
                    this.sidebar?.classList.remove('sidebar-open');
                }, 100);
            });
        }

        unlockSensitiveContent(postId) {
            this.state.unlocked_content[postId] = true;

            const reel = document.querySelector(`.reel-item[data-feed-item-id="${postId}"]`);
            if (!reel) return;

            const video = reel.querySelector('video.reels-video');
            if (video) {
                video.style.filter = '';
                video.classList.remove('blurred');
                // Se for o vídeo atual, recarregar para tocar normal
                const currentItem = this.state.currentItems[this.state.currentIndex];
                if (currentItem && currentItem.id == postId) {
                    this.loadReel();
                }
            }

            const overlay = reel.querySelector('.sensitive-overlay');
            if (overlay) overlay.remove();
        }

        buildItemData(trigger) {
            const card = trigger.closest('article, .post-card, .feed-item-wrapper');

            let src = '';
            let duration = 0;

            if (trigger.dataset.src && this.isVideoFile(trigger.dataset.src)) {
                src = trigger.dataset.src;
            } else if (trigger.dataset.videoUrl && this.isVideoFile(trigger.dataset.videoUrl)) {
                src = trigger.dataset.videoUrl;
            } else {
                const videoEl = trigger.querySelector('video');
                if (videoEl) {
                    src = videoEl.src || videoEl.querySelector('source')?.src || '';
                }
            }

            if (trigger.dataset.duration) {
                duration = parseInt(trigger.dataset.duration) || 0;
            } else {
                const videoEl = trigger.querySelector('video');
                if (videoEl?.dataset?.duration) {
                    duration = parseInt(videoEl.dataset.duration) || 0;
                }
            }
            if (!duration && card?.dataset?.duration) {
                duration = parseInt(card.dataset.duration) || 0;
            }

            const thumbnail = trigger.dataset.thumbnail || trigger.querySelector('img')?.src || '';

            // FIX: Capturar dimensões do vídeo do feed para detetar orientação
            // Isto permite reservar o espaço correto no lightbox antes do vídeo carregar
            let videoWidth = parseInt(trigger.dataset.videoWidth || trigger.dataset.width || '0') || 0;
            let videoHeight = parseInt(trigger.dataset.videoHeight || trigger.dataset.height || '0') || 0;
            if (!videoWidth || !videoHeight) {
                const feedVideo = trigger.querySelector('video');
                if (feedVideo) {
                    videoWidth = feedVideo.videoWidth || parseInt(feedVideo.dataset.width || '0') || 0;
                    videoHeight = feedVideo.videoHeight || parseInt(feedVideo.dataset.height || '0') || 0;
                }
            }

            if (!src || !this.isVideoFile(src)) return null;

            const authorLink = card?.querySelector('.post-author');
            const authorName = authorLink?.textContent.trim() || 'Usuário';
            const authorUrl = authorLink?.href || '#';
            const authorThumb = card?.querySelector('.profile-thumb')?.src || '';
            let authorId = trigger.dataset.authorId || '';
            if (!authorId && authorUrl) {
                try {
                    authorId = new URL(authorUrl, window.location.origin).searchParams.get('id') || '';
                } catch (e) { }
            }

            const sharedAuthorLink = card?.querySelector('.shared-username-link');
            const isRepost = !!sharedAuthorLink;

            const isForSale = trigger.dataset.isForSale === 'true' || trigger.classList.contains('video-locked');
            const hasAccess = trigger.dataset.hasAccess === 'true';
            const isPostOwner = trigger.dataset.isPostOwner === 'true';
            const isAdultContent = trigger.dataset.adult === 'true' || card?.dataset?.adult === 'true';

            // Dados da IA (NudeNet)
            const aiStatus = trigger.dataset.aiStatus || '';
            const aiRisk = trigger.dataset.aiRisk || 'low';
            const aiScore = parseFloat(trigger.dataset.aiScore) || 0;

            let viewsCount = trigger.dataset.viewsCount || '';
            if (!viewsCount && card) {
                const viewsEl = card.querySelector('.video-stats span, [data-views-id^="video-"]');
                if (viewsEl) viewsCount = viewsEl.textContent.replace(/\D/g, '');
            }

            let sharesCount = trigger.dataset.sharesCount || '';
            if (!sharesCount && card) {
                // Busca pelo ID específico primeiro
                const specificEl = card.querySelector(`#share-count-${trigger.dataset.id || trigger.dataset.feedItemId || ''}`);
                if (specificEl) {
                    sharesCount = specificEl.textContent.trim();
                } else {
                    // Fallback
                    const fallbackEl = card.querySelector('[id^="share-count-"]');
                    if (fallbackEl) sharesCount = fallbackEl.textContent.trim();
                }
            }

            return {
                // FIX 10: propriedades duplicadas removidas — 'id', 'aiStatus', 'aiRisk', 'aiScore'
                // estavam definidas duas vezes no objecto; JS usa o último valor,
                // mas provoca confusão e linting errors.
                id: trigger.dataset.id || trigger.dataset.feedItemId || '',
                itemId: trigger.dataset.id || trigger.dataset.feedItemId || '',
                itemType: trigger.dataset.itemType || trigger.dataset.type || 'video',
                src: src,
                thumbnail: thumbnail,
                authorThumb: authorThumb,
                authorName: authorName,
                authorUrl: authorUrl,
                authorId: authorId,
                caption: card?.querySelector('.post-text')?.textContent.trim() || '',
                date: card?.querySelector('.post-date')?.textContent.trim() || '',
                isRepost: isRepost,
                sharedAuthorName: sharedAuthorLink?.textContent.trim() || '',
                sharedAuthorUrl: sharedAuthorLink?.href || '',
                isForSale: isForSale,
                price: parseFloat(trigger.dataset.price) || 0,
                hasAccess: hasAccess,
                isAdultContent: isAdultContent,
                isPostOwner: isPostOwner,
                likesCount: this.safeGetText(card, '.likes-count', '0'),
                commentsCount: this.safeGetText(card, '.comment-count-display', '0'),
                viewsCount: viewsCount || '0',
                sharesCount: sharesCount || '0',
                // IA (NudeNet) — definidas uma única vez
                aiStatus: aiStatus,
                aiRisk: aiRisk,
                aiScore: aiScore,
                aiUnlocked: trigger.dataset.aiUnlocked || 'false',
                checkoutUrl: trigger.dataset.checkoutUrl || '',
                isVerified: trigger.dataset.isVerified === 'true',
                duration: duration,
                videoWidth: videoWidth,
                videoHeight: videoHeight,
                isSaved: (() => {
                    const card = trigger.closest('article, .post-card, .feed-item-wrapper');
                    const saveBtn = card?.querySelector('.btn-save');
                    return saveBtn ? saveBtn.classList.contains('active') : false;
                })()
            };
        }
        /**
         * Toggle guardar a partir do lightbox (reels sidebar).
         */
        async toggleSaveLightbox(btn) {
            if (btn.disabled) return;
            btn.disabled = true;

            const itemType = btn.dataset.itemType;
            const itemId = btn.dataset.itemId;
            const csrfToken = btn.dataset.csrf;
            const key = itemType + '_' + itemId;
            const isSaved = btn.classList.contains('active');
            const icon = btn.querySelector('i');
            const countEl = btn.closest('.reels-action-item')?.querySelector('.reels-action-count');

            // Optimistic UI
            if (isSaved) {
                btn.classList.remove('active');
                icon?.classList.replace('fa-solid', 'fa-regular');
                if (countEl) countEl.textContent = 'Guardar';
            } else {
                btn.classList.add('active');
                icon?.classList.replace('fa-regular', 'fa-solid');
                if (countEl) countEl.textContent = 'Guardado';
                icon?.animate([
                    { transform: 'scale(1)' },
                    { transform: 'scale(1.4)' },
                    { transform: 'scale(1)' }
                ], { duration: 300, easing: 'ease-out' });
            }

            // Sincronizar com botão no feed
            const feedBtn = document.querySelector(
                `.btn-save[data-item-type="${itemType}"][data-item-id="${itemId}"]`
            );
            if (feedBtn && feedBtn !== btn) {
                feedBtn.classList.toggle('active', !isSaved);
                const fi = feedBtn.querySelector('i');
                if (fi) fi.className = isSaved ? 'fa-regular fa-bookmark' : 'fa-solid fa-bookmark';
                const fl = feedBtn.querySelector('span');
                if (fl) fl.textContent = isSaved ? 'Guardar' : 'Guardado';
            }

            try {
                const res = await fetch((window.BASE_URL || '') + 'ajax/toggle_save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        csrf_token: csrfToken,
                        item_type: itemType,
                        item_id: itemId,
                    })
                });
                const data = await res.json();

                if (!data.success) {
                    // Reverter
                    if (isSaved) {
                        btn.classList.add('active');
                        icon?.classList.replace('fa-regular', 'fa-solid');
                        if (countEl) countEl.textContent = 'Guardado';
                    } else {
                        btn.classList.remove('active');
                        icon?.classList.replace('fa-solid', 'fa-regular');
                        if (countEl) countEl.textContent = 'Guardar';
                    }
                }
            } catch (err) {
                console.error('[Lightbox] Erro ao guardar:', err);
            } finally {
                btn.disabled = false;
            }
        }

        updateShareCount(id, count) {
            // Atualiza no lightbox (reels)
            const reelEl = document.getElementById(`share-count-${id}`);
            if (reelEl) reelEl.textContent = count;

            // Atualiza no feed principal (index.php)
            const feedCard = document.querySelector(`.post-card[data-feed-item-id="${id}"]`);
            if (feedCard) {
                const feedEl = feedCard.querySelector(`[id^="share-count-"]`);
                if (feedEl) feedEl.textContent = count;
            }
        }
        safeGetText(parent, selector, fallback = '') {
            try {
                return parent?.querySelector(selector)?.textContent?.trim() || fallback;
            } catch (e) {
                return fallback;
            }
        }

        isVideoFile(url) {
            if (!url) return false;
            const exts = ['.mp4', '.webm', '.ogg', '.mov', '.m4v', '.mkv', '.avi'];
            const lower = url.toLowerCase();
            return lower.includes('/videos/') || exts.some(e => lower.includes(e));
        }

        // FIX 16: openSidebar() removido — era duplicação exacta de toggleSidebar() quando
        // state.sidebarOpen era false. Todos os callers usam toggleSidebar() ou toggleReelsComments().

        // ===============================
        // COMMENTS SYSTEM (COMPLETO)
        // ===============================

        async loadComments() {
            const item = this.state.currentItems[this.state.currentIndex];
            if (!item || !this.commentsArea) return;

            // FIX 17: Cancelar request anterior se o utilizador mudou de reel rapidamente
            if (this._commentsAbortController) {
                this._commentsAbortController.abort();
            }
            this._commentsAbortController = new AbortController();

            try {
                const res = await fetch(
                    `${window.BASE_URL || ''}ajax/get_comments.php?feed_item_id=${encodeURIComponent(item.id)}`,
                    { signal: this._commentsAbortController.signal }
                );
                const data = await res.json();

                if (data.success) {
                    this.renderCommentsFromJSON(data);
                } else {
                    this.commentsArea.innerHTML = '<p>Erro ao carregar comentários.</p>';
                }
            } catch (e) {
                // FIX: ignorar erros de abort — são esperados ao navegar rapidamente
                if (e.name !== 'AbortError') {
                    this.commentsArea.innerHTML = '<p>Erro ao carregar comentários.</p>';
                }
            }
        }

        renderCommentsFromJSON(data) {
            let html = '';

            if (data.header) {
                const authorInfo = data.header.post_author;
                html += `
                <div class="comments-header" style="padding-bottom: 16px; border-bottom: 1px solid #333; margin-bottom: 16px;">
                    <div class="post-author-info" style="display: flex; align-items: center; gap: 12px;">
                        <img src="${this.escapeHtml(authorInfo.profile_picture)}" alt="${this.escapeHtml(authorInfo.username)}" style="width: 40px; height: 40px; border-radius: 50%;">
                        <div>
                            <strong style="color: #fff;">${this.escapeHtml(authorInfo.username)}</strong>
                            ${data.header.is_repost && data.header.original_author ? `
                                <small style="color: #888; display: block;">Repostado de ${this.escapeHtml(data.header.original_author.username)}</small>
                            ` : ''}
                            <p style="color: #ccc; margin: 4px 0 0 0; font-size: 14px;">${this.escapeHtml(data.header.post_content)}</p>
                        </div>
                    </div>
                </div>
            `;
            }

            if (data.comments && data.comments.length > 0) {
                html += '<div class="comments-list" style="display: flex; flex-direction: column; gap: 16px;">';
                data.comments.forEach(comment => {
                    html += this.renderCommentHTML(comment, data);
                });
                html += '</div>';
            } else {
                html += '<p class="no-comments" style="text-align: center; color: #888; padding: 40px 20px;">Nenhum comentário ainda. Seja o primeiro!</p>';
            }

            this.commentsArea.innerHTML = html;
            this.initCommentEventListeners();
        }

        renderCommentHTML(comment, data) {
            const canDelete = comment.can_delete || false;

            let html = `
            <div class="comment-item" data-comment-id="${comment.id}" style="display: flex; gap: 12px; position: relative;">
                <img src="${this.escapeHtml(comment.profile_picture)}" alt="${this.escapeHtml(comment.username)}" style="width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;">
                
                <div style="flex: 1; min-width: 0;">
                    <div style="background: #3a3b3c; padding: 10px 14px; border-radius: 18px; display: inline-block; max-width: 100%;">
                        <strong style="font-size: 13px; color: #e4e6eb; display: block; margin-bottom: 2px;">${this.escapeHtml(comment.username)}</strong>
                        <p style="margin: 0; font-size: 14px; color: #e4e6eb; line-height: 1.4; word-wrap: break-word;">${this.escapeHtml(comment.content)}</p>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 4px; padding-left: 4px; align-items: center;">
                        <span style="font-size: 12px; color: #b0b3b8;">${this.escapeHtml(comment.formatted_created_at)}</span>
                        <button class="btn-comment-like" data-comment-id="${comment.id}" data-vote-type="like" style="background: none; border: none; color: #b0b3b8; font-size: 12px; cursor: pointer; font-weight: 600;">
                            Gosto <span class="comment-likes-count">${comment.likes}</span>
                        </button>
                        <button class="btn-reply-comment" data-comment-id="${comment.id}" style="background: none; border: none; color: #b0b3b8; font-size: 12px; cursor: pointer; font-weight: 600;">
                            Responder
                        </button>
                    </div>
                    
                    <div class="reply-form-container" id="replyFormContainer-${comment.id}" style="display: none; margin-top: 10px;"></div>
                    
                    ${comment.replies && comment.replies.length > 0 ? `
                        <div class="comment-replies" style="margin-top: 12px; padding-left: 12px; border-left: 2px solid #333; display: flex; flex-direction: column; gap: 12px;">
                            ${comment.replies.map(reply => this.renderCommentHTML(reply, data)).join('')}
                        </div>
                    ` : ''}
                </div>
                
                ${canDelete ? `
                    <div class="comment-actions-dropdown" style="position: absolute; right: 0; top: 0;">
                        <button class="dropdown-toggle" style="background: none; border: none; color: #b0b3b8; cursor: pointer; padding: 5px; border-radius: 50%;">
                            <i class="fa-solid fa-ellipsis-h"></i>
                        </button>
                        <div class="dropdown-menu" style="display: none; position: absolute; right: 0; top: 100%; background: #242526; border: 1px solid #333; border-radius: 8px; box-shadow: 0 8px 16px rgba(0,0,0,0.5); z-index: 100; min-width: 120px;">
                            <button class="edit-comment-btn" data-comment-id="${comment.id}" data-content="${this.escapeHtml(comment.content)}" style="display: block; width: 100%; padding: 10px 16px; text-align: left; background: none; border: none; color: #e4e6eb; font-size: 13px; cursor: pointer;">
                                <i class="fa-solid fa-edit"></i> Editar
                            </button>
                            <button class="delete-comment-btn" data-comment-id="${comment.id}" style="display: block; width: 100%; padding: 10px 16px; text-align: left; background: none; border: none; color: #e4e6eb; font-size: 13px; cursor: pointer;">
                                <i class="fa-solid fa-trash"></i> Deletar
                            </button>
                        </div>
                    </div>
                ` : ''}
            </div>
        `;

            return html;
        }

        initCommentEventListeners() {
            if (!this.commentsArea) return;

            this.commentsArea.querySelectorAll('.btn-comment-like, .btn-comment-dislike').forEach(button => {
                button.addEventListener('click', (e) => this.handleCommentVote(e));
            });

            this.commentsArea.querySelectorAll('.btn-reply-comment').forEach(button => {
                button.addEventListener('click', (e) => this.handleReplyClick(e));
            });

            this.commentsArea.querySelectorAll('.edit-comment-btn').forEach(button => {
                button.addEventListener('click', (e) => this.handleEditClick(e));
            });

            this.commentsArea.querySelectorAll('.delete-comment-btn').forEach(button => {
                button.addEventListener('click', (e) => this.handleDeleteClick(e));
            });

            this.commentsArea.querySelectorAll('.dropdown-toggle').forEach(button => {
                button.addEventListener('click', (e) => this.handleDropdownToggle(e));
            });
        }

        handleDropdownToggle(event) {
            event.stopPropagation();
            const button = event.currentTarget;
            const dropdown = button.closest('.comment-actions-dropdown');
            const menu = dropdown?.querySelector('.dropdown-menu');

            if (!menu) return;

            document.querySelectorAll('.dropdown-menu').forEach(m => {
                if (m !== menu) m.style.display = 'none';
            });

            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }

        async handleCommentVote(event) {
            const button = event.currentTarget;
            const commentId = button.dataset.commentId;
            const voteType = button.dataset.voteType;

            if (!window.CURRENT_USER_ID) {
                this.showToast('Você precisa estar logado para votar.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'vote_comment');
            formData.append('comment_id', commentId);
            formData.append('vote_type', voteType);

            const item = this.state.currentItems[this.state.currentIndex];
            if (item) formData.append('feed_item_id', item.id);

            try {
                const response = await fetch((window.BASE_URL || '') + 'api/comments.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    const commentItem = button.closest('.comment-item');
                    if (!commentItem) return;

                    const likeButton = commentItem.querySelector('.btn-comment-like');
                    const dislikeButton = commentItem.querySelector('.btn-comment-dislike');

                    if (likeButton) {
                        likeButton.querySelector('.comment-likes-count').textContent = data.likes;
                        likeButton.classList.toggle('active', data.user_vote === 'like');
                    }
                    if (dislikeButton) {
                        dislikeButton.classList.toggle('active', data.user_vote === 'dislike');
                    }
                } else {
                    this.showToast(data.message || 'Erro ao processar o voto', 'error');
                }
            } catch (error) {
                console.error('Erro na requisição AJAX de voto:', error);
                this.showToast('Erro ao conectar com o servidor.', 'error');
            }
        }

        handleReplyClick(event) {
            const btn = event.target.closest('.btn-reply-comment');
            if (!btn) return;

            const commentId = btn.dataset.commentId;
            const container = document.getElementById(`replyFormContainer-${commentId}`);
            if (!container) return;

            if (container.style.display === 'block') {
                container.style.display = 'none';
                container.innerHTML = '';
                return;
            }

            const item = this.state.currentItems[this.state.currentIndex];
            if (!item) return;

            const profilePicture = window.CURRENT_USER_PROFILE_PICTURE || (window.UPLOAD_URL || '') + 'profiles/default_profile.png';

            container.innerHTML = `
            <form class="comment-form reply-form" style="display: flex; gap: 8px; align-items: flex-start;">
                <input type="hidden" name="action" value="add_reply">
                <input type="hidden" name="feed_item_id" value="${item.id}">
                <input type="hidden" name="parent_comment_id" value="${commentId}">
                <img src="${profilePicture}" alt="Sua foto" style="width: 32px; height: 32px; border-radius: 50%;">
                <div style="flex: 1;">
                    <textarea name="comment_content" placeholder="Escreva sua resposta..." required style="width: 100%; background: #3a3b3c; border: none; border-radius: 12px; padding: 10px; color: #fff; resize: none; min-height: 60px;"></textarea>
                    <div style="display: flex; gap: 8px; margin-top: 8px; justify-content: flex-end;">
                        <button type="button" class="cancel-reply-btn" data-comment-id="${commentId}" style="background: transparent; border: 1px solid #555; color: #aaa; padding: 6px 16px; border-radius: 16px; cursor: pointer;">Cancelar</button>
                        <button type="submit" style="background: #00f576; color: #000; border: none; padding: 6px 20px; border-radius: 16px; font-weight: bold; cursor: pointer;">Responder</button>
                    </div>
                </div>
            </form>
        `;
            container.style.display = 'block';
            container.querySelector('textarea').focus();

            const form = container.querySelector('form');
            form.addEventListener('submit', (e) => this.handleCommentFormSubmit(e));

            container.querySelector('.cancel-reply-btn').addEventListener('click', () => {
                container.style.display = 'none';
                container.innerHTML = '';
            });
        }

        handleEditClick(event) {
            const button = event.target.closest('.edit-comment-btn');
            if (!button) return;

            const commentId = button.dataset.commentId;
            const originalContent = button.dataset.content || '';
            const replyFormContainer = document.getElementById(`replyFormContainer-${commentId}`);

            if (!replyFormContainer) return;

            this.commentsArea.querySelectorAll('.reply-form-container').forEach(container => {
                if (container.id !== `replyFormContainer-${commentId}`) {
                    container.style.display = 'none';
                    container.innerHTML = '';
                }
            });

            replyFormContainer.style.display = 'block';
            replyFormContainer.innerHTML = `
            <form class="comment-form edit-form" style="display: flex; gap: 8px; align-items: flex-start;">
                <input type="hidden" name="action" value="edit_comment">
                <input type="hidden" name="comment_id" value="${commentId}">
                <div style="flex: 1;">
                    <textarea name="comment_content" required style="width: 100%; background: #3a3b3c; border: none; border-radius: 12px; padding: 10px; color: #fff; resize: none; min-height: 60px;">${this.escapeHtml(originalContent)}</textarea>
                    <div style="display: flex; gap: 8px; margin-top: 8px; justify-content: flex-end;">
                        <button type="button" class="cancel-edit-btn" data-comment-id="${commentId}" style="background: transparent; border: 1px solid #555; color: #aaa; padding: 6px 16px; border-radius: 16px; cursor: pointer;">Cancelar</button>
                        <button type="submit" style="background: #00f576; color: #000; border: none; padding: 6px 20px; border-radius: 16px; font-weight: bold; cursor: pointer;">Salvar</button>
                    </div>
                </div>
            </form>
        `;

            const form = replyFormContainer.querySelector('form');
            form.addEventListener('submit', (e) => this.handleCommentFormSubmit(e));

            replyFormContainer.querySelector('.cancel-edit-btn').addEventListener('click', () => {
                replyFormContainer.style.display = 'none';
                replyFormContainer.innerHTML = '';
            });

            replyFormContainer.querySelector('textarea').focus();
        }

        async handleDeleteClick(event) {
            const button = event.target.closest('.delete-comment-btn');
            if (!button) return;

            const commentId = button.dataset.commentId;

            if (!confirm('Tem certeza que deseja apagar este comentário?')) return;

            const formData = new FormData();
            formData.append('action', 'delete_comment');
            formData.append('comment_id', commentId);

            try {
                const response = await fetch((window.BASE_URL || '') + 'api/comments.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    this.showToast('Comentário apagado com sucesso!');
                    await this.loadComments();

                    if (data.comment_count !== undefined) {
                        this.updateCommentCount(this.state.currentItems[this.state.currentIndex]?.id, data.comment_count);
                    }
                } else {
                    this.showToast(data.message || 'Erro ao apagar comentário', 'error');
                }
            } catch (error) {
                console.error('Erro na requisição de apagar:', error);
                this.showToast('Erro ao comunicar com o servidor.', 'error');
            }
        }

        async handleCommentFormSubmit(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const actionType = formData.get('action');

            const item = this.state.currentItems[this.state.currentIndex];
            if (!item) return;

            if (!formData.get('feed_item_id')) {
                formData.set('feed_item_id', item.id);
            }

            try {
                const response = await fetch((window.BASE_URL || '') + 'api/comments.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    this.showToast(data.message || 'Operação realizada com sucesso!');
                    await this.loadComments();

                    if (data.comment_count !== undefined) {
                        this.updateCommentCount(item.id, data.comment_count);
                    }
                } else {
                    this.showToast(data.message || 'Erro ao processar comentário', 'error');
                }
            } catch (error) {
                console.error('Erro na requisição:', error);
                this.showToast('Erro ao comunicar com o servidor.', 'error');
            }
        }

        // ===============================
        // RENDER REELS
        // ===============================
        renderReels(itemsToRender = null) {
            const items = itemsToRender || this.state.currentItems;
            if (items.length === 0) return;
            this.scrollContainer.innerHTML = items.map((item, index) => this.renderReelItem(item, index)).join('');
        }

        renderReelItem(item, index) {
            const currentUserId = window.CURRENT_USER_ID || null;
            const canFollow = currentUserId && currentUserId != item.authorId && item.authorId;

            const isAdultLocked = item.isAdultContent && !this.state.userAgeVerified;
            const isSaleLocked = item.isForSale && !item.hasAccess && !item.isPostOwner;

            const adultLockClass = isAdultLocked ? 'adult-content-locked' : '';
            const saleClass = item.isForSale ? (isSaleLocked ? 'for-sale sale-locked' : 'for-sale') : '';

            const adultOverlay = isAdultLocked ? this.renderAdultLockOverlay(item) : '';
            const saleOverlay = (item.isForSale && item.hasAccess) ? this.renderSaleAcquiredOverlay() : '';
            const lockedOverlay = isSaleLocked ? this.renderVideoLockedOverlay(item) : '';

            // Lógica de Sensibilidade (NudeNet)
            // [MASSANGOS FIX] Sincronização: Se já foi desbloqueado no feed, não mostra blur no lightbox
            const isSensitive = item.aiStatus === 'done' && item.aiRisk !== 'low' && !this.state.unlocked_content[item.id] && !item.isPostOwner && item.aiUnlocked !== 'true';
            const sensitiveOverlay = isSensitive ? this.renderSensitiveOverlay(item) : '';
            const sensitiveClass = isSensitive ? 'blurred' : '';
            const sensitiveStyle = isSensitive ? 'filter: blur(40px);' : '';

            const showControls = !isAdultLocked && !isSaleLocked && !isSensitive;
            const videoSrc = (isAdultLocked || isSaleLocked) ? '' : this.escapeHtml(item.src);
            const videoStyle = (isAdultLocked || isSaleLocked) ? 'style="filter: blur(40px); transform: scale(0);"' : '';

            // FIX: Detectar orientação para vídeos 16:9 (1920x1080, etc.)
            // A classe is-landscape reserva o espaço correto antes do vídeo carregar,
            // tornando a transição thumbnail → vídeo imperceptível.
            const isLandscape = item.videoWidth && item.videoHeight
                ? item.videoWidth > item.videoHeight
                : (item.aspectRatio === 'landscape' || item.aspectRatio === '16:9');
            const landscapeClass = isLandscape ? 'is-landscape' : '';

            // Estilos pré-renderizados: aplicados antes de onloadedmetadata.
            // onloadedmetadata sobrepõe com valores finais precisos.
            // Inline style tem prioridade máxima sem necessidade de !important.
            const isMobileRender = typeof window !== 'undefined' && window.innerWidth <= 767;

            // MOBILE LANDSCAPE FIX: Em mobile, vídeos landscape também devem ocupar
            // o ecrã inteiro (fullscreen + cover), tal como os portrait.
            // A caixa 16:9 em 375px ficaria com apenas ~211px de altura — inaceitável.
            // As actions ficam absolutas sobre o vídeo (position:absolute via CSS mobile).
            const wrapperInlineStyle = isLandscape && !isMobileRender
                ? (() => {
                    const gutter = '200px';
                    return `style="aspect-ratio:16/9; width:min(calc(100vw - ${gutter}),calc(90vh * 16 / 9)); height:min(90vh,calc((100vw - ${gutter}) * 9 / 16)); max-width:calc(100vw - ${gutter}); max-height:90vh; flex-shrink:0; position:relative; overflow:hidden; border-radius:12px; display:flex; align-items:center; justify-content:center;"`;
                })()
                : isMobileRender
                    // Mobile (portrait E landscape): fullscreen, sem border-radius, cover
                    ? `style="width:100%; max-width:100%; height:100svh; height:100dvh; max-height:100svh; border-radius:0; overflow:hidden; position:relative;"`
                    : '';

            // Estilos inline no vídeo — transição imperceptível entre poster e vídeo.
            // Mobile landscape: object-fit cover para preencher o ecrã (corta bordas, mantém centro).
            const videoInlineStyle = (isLandscape && !isMobileRender)
                ? `width:100%; height:100%; object-fit:cover; display:block; background:#000; margin:0; padding:0; border:none; max-width:100%; max-height:100%; ${sensitiveStyle}`
                : isMobileRender
                    ? `display:block; width:100%; height:100%; max-height:none; object-fit:cover; background:#000; margin:0; padding:0; border:none; ${sensitiveStyle}`
                    : `display:block; background:#000; margin:0; padding:0; border:none; ${sensitiveStyle}`;

            // Thumbnail com mesmo aspect-ratio do vídeo para o poster
            const posterAttr = item.thumbnail ? `poster="${this.escapeHtml(item.thumbnail)}"` : '';


            const itemId = this.escapeHtml(item.id);
            const baseUrl = this.escapeHtml(window.BASE_URL || '');

            return `
            <div class="reel-item ${adultLockClass} ${saleClass}"
                 data-index="${index}"
                 data-feed-item-id="${itemId}">

                <!-- WRAPPER DO VÍDEO: contém apenas media + overlays + info + controlos -->
                <div class="reel-video-wrapper ${landscapeClass}" ${wrapperInlineStyle}>
                    <div class="reel-video-inner">

                        <video class="reels-video ${sensitiveClass}"
                               src="${this.escapeHtml(item.src)}"
                               loop playsinline preload="auto"
                               data-item-id="${this.escapeHtml(item.itemId)}"
                               data-item-type="${this.escapeHtml(item.itemType)}"
                               ${posterAttr}
                               ${videoStyle}
                               style="${videoInlineStyle}"></video>

                        ${adultOverlay}
                        ${lockedOverlay}
                        ${saleOverlay}
                        ${sensitiveOverlay}

                        <!-- Info overlay: autor + legenda + views -->
                        <div class="reels-info-overlay">
                            <div class="reels-user-info">
                                <div class="avatar-container">
                                    <img src="${this.escapeHtml(item.authorThumb)}"
                                         class="reels-user-thumb"
                                         onclick="window.location.href='${this.escapeHtml(item.authorUrl)}'"
                                         alt="${this.escapeHtml(item.authorName)}">
                                </div>
                                <div class="reels-author-details">
                                    <div class="author-line">
                                        <a href="${this.escapeHtml(item.authorUrl)}" class="reels-user-name">${this.escapeHtml(item.authorName)}</a>
                                        ${canFollow ? `
                                            <button class="follow-btn-mini"
                                                    onclick="App.toggleFollow('${this.escapeHtml(item.authorId)}', this)"
                                                    data-user-id="${this.escapeHtml(item.authorId)}">Seguir</button>
                                        ` : ''}
                                    </div>
                                    ${item.isRepost ? `<div class="reels-repost-info"><i class="fa-solid fa-retweet"></i> ${this.escapeHtml(item.sharedAuthorName)}</div>` : ''}
                                    <span class="reels-date">${this.escapeHtml(item.date)}</span>
                                </div>
                            </div>
                            ${item.caption ? `<div class="reels-caption">${this.escapeHtml(item.caption)}</div>` : ''}
                            <div class="reels-views-count">
                                <i class="fa-solid fa-eye"></i>
                                <span>${this.escapeHtml(item.viewsCount)} visualizações</span>
                            </div>
                        </div>

                        ${showControls ? `
                            <div class="reels-video-controls">
                                <button class="control-btn" data-action="toggle-mute" title="Mudo/Som">
                                    <i class="fa-solid ${this.state.isMuted ? 'fa-volume-xmark' : 'fa-volume-high'}"></i>
                                </button>
                                <button class="control-btn" data-action="toggle-play" title="Play/Pausa">
                                    <i class="fa-solid fa-play"></i>
                                </button>
                            </div>
                            <div class="reels-progress-container"><div class="reels-progress-bar"></div></div>
                        ` : ''}

                    </div><!-- /.reel-video-inner -->
                </div><!-- /.reel-video-wrapper -->

                <!-- ACTIONS SIDEBAR: FORA do wrapper para não ser cortada pelo overflow:hidden -->
                <div class="reels-actions-sidebar">

                    <!-- Like -->
                    <div class="reels-action-item">
                        <button class="reels-action-btn like-btn" data-action="like" aria-label="Gostar">
                            <i class="fa-regular fa-thumbs-up"></i>
                        </button>
                        <span class="reels-action-count like-count">${this.escapeHtml(item.likesCount)}</span>
                    </div>

                    <!-- Comentar -->
                    <div class="reels-action-item">
                        <button class="reels-action-btn comment-trigger" aria-label="Comentários"
                                onclick="event.stopPropagation(); window.toggleReelsComments(event);">
                            <i class="fa-regular fa-comment"></i>
                        </button>
                        <span class="reels-action-count comment-count">${this.escapeHtml(item.commentsCount)}</span>
                    </div>
                    <!-- Guardar -->
                    <div class="reels-action-item">
                        <button class="reels-action-btn save-btn ${item.isSaved ? 'active' : ''}"
                                aria-label="Guardar"
                                data-item-type="${this.escapeHtml(item.itemType)}"
                                data-item-id="${this.escapeHtml(String(item.itemId))}"
                                data-csrf="${this.escapeHtml(window.CSRF_TOKEN || '')}"
                                onclick="event.stopPropagation(); window.reelsManagerInstance.toggleSaveLightbox(this);">
                            <i class="${item.isSaved ? 'fa-solid' : 'fa-regular'} fa-bookmark"></i>
                        </button>
                        <span class="reels-action-count">${item.isSaved ? 'Guardado' : 'Guardar'}</span>
                    </div>

                    <!-- Partilhar -->
                    <div class="reels-action-item reels-share-item">
                        <button class="reels-action-btn share-btn" aria-label="Partilhar"
                                onclick="event.stopPropagation(); window.togglePremiumShareMenu('${itemId}');">
                            <i class="fa-regular fa-share-from-square"></i>
                        </button>
                        <span class="reels-action-count share-count" id="share-count-${itemId}">${this.escapeHtml(item.sharesCount || '0')}</span>

                        <!-- Dropdown de partilha -->
                        <div class="reels-share-dropdown" id="premium-share-menu-${itemId}" aria-hidden="true">
                            <button class="reels-share-option"
                                    onclick="event.stopPropagation(); window.copyToClipboard('${baseUrl}post.php?id=${itemId}', '${itemId}');">
                                <i class="fa-regular fa-link"></i>
                                Copiar link
                            </button>
                            <button class="reels-share-option"
                                    onclick="event.stopPropagation(); window.handleRepost('${itemId}'); window.togglePremiumShareMenu('${itemId}');">
                                <i class="fa-regular fa-retweet"></i>
                                Repostar
                            </button>
                        </div>
                    </div>

                </div><!-- /.reels-actions-sidebar -->

            </div><!-- /.reel-item -->
        `;
        }

        renderVideoLockedOverlay(item) {
            const priceFormatted = item.price.toFixed(2).replace('.', ',');
            const checkoutUrl = item.checkoutUrl || (window.BASE_URL || '') + `checkout.php?type=${item.itemType}&id=${item.itemId}`;
            const isVerified = item.isVerified ? 'true' : 'false';
            const previewId = `preview-${item.itemId}`;
            const durationFormatted = this.formatDuration(item.duration);

            // FIX 12: <style> com @keyframes removido daqui — era injectado a cada renderização
            // de reel, causando N cópias dos mesmos keyframes no DOM (memory leak + performance).
            // As animações 'pulse' e 'blink' foram movidas para premium_lightbox.css.
            return `
            <div class="video-locked-overlay" 
                 onclick="window.handleLockedReelClick(this)"
                 data-is-verified="${isVerified}"
                 data-checkout-url="${this.escapeHtml(checkoutUrl)}"
                 data-item-type="${this.escapeHtml(item.itemType)}"
                 data-item-id="${this.escapeHtml(item.itemId)}"
                 style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; 
                        background: rgba(0,0,0,0.4); 
                        display: flex; flex-direction: column; 
                        align-items: center; justify-content: center; 
                        color: #fff; z-index: 100; cursor: pointer;
                        border-radius: 12px; overflow: hidden;">
                
                <!-- VÍDEO DE PREVIEW: Loop de 5s -->
                <video id="${previewId}"
                       class="preview-video-blur"
                       src="${this.escapeHtml(item.src)}" 
                       muted playsinline webkit-playsinline preload="metadata" loop
                       style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; 
                              object-fit: cover; 
                              z-index: 0; opacity: 0; transition: opacity 0.6s ease;"></video>
                
                <div style="text-align: center; padding: 32px 28px; z-index: 1; position: relative; 
                            max-width: 90%;
                            box-shadow: 0 rgba(0,0,0,0.6);">
                    
                    <div style="margin-bottom: 22px; display: flex; align-items: center; justify-content: center; gap: 18px;">
                        <div class="locked-overlay-icon locked-overlay-icon--play">
                            <i class="fa-solid fa-play"></i>
                        </div>
                        <div class="locked-overlay-icon locked-overlay-icon--lock">
                            <i class="fas fa-lock" style="font-size: 1.8rem; color: #ff4757;"></i>
                        </div>
                    </div>
                    
                    <div style="margin: 10px 0; color: #aaa; font-size: 0.95rem; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fa-solid fa-eye" style="color: #666;"></i>
                        <span>${this.escapeHtml(item.viewsCount)} visualizações</span>
                    </div>
                    
                    <div style="margin: 24px 0; padding: 18px 32px; 
                                background: linear-gradient(135deg, rgba(0,245,118,0.25), rgba(0,212,170,0.15)); 
                                border-radius: 20px; 
                                border: 3px solid rgba(0,245,118,0.7);
                                display: flex; flex-direction: column; align-items: center; gap: 8px;
                                box-shadow: 0 10px 40px rgba(0,245,118,0.2);">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fa-solid fa-film" style="color: #00f576; font-size: 1.4rem;"></i>
                            <span style="color: #00f576; font-size: 2.4rem; font-weight: 900; letter-spacing: 2px; font-family: 'SF Mono', monospace; text-shadow: 0 2px 10px rgba(0,245,118,0.4);">
                                ${durationFormatted}
                            </span>
                        </div>
                        <span style="color: #00d4aa; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;">
                            duração total do vídeo
                        </span>
                    </div>
                    
                    <p style="font-size: 2rem; font-weight: bold; margin: 18px 0 10px 0; color: #fff; text-shadow: 0 3px 15px rgba(0,0,0,0.5);">
                        ${priceFormatted} <span style="font-size: 1.1rem; color: #aaa; font-weight: 500;">MT</span>
                    </p>
                    
                    <span style="font-size: 1rem; color: #ffd700; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 10px; text-transform: uppercase; letter-spacing: 1px;">
                        <i class="fa-solid fa-unlock"></i>
                        Desbloquear conteúdo
                    </span>
                    
                    <div style="margin-top: 18px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <span class="locked-overlay-blink"></span>
                        <span style="font-size: 0.85rem; color: #888; font-weight: 500;">
                            Preview gratuito em loop (5s)
                        </span>
                    </div>
                </div>
            </div>
        `;
        }

        formatDuration(totalSeconds) {
            if (!totalSeconds || isNaN(totalSeconds) || totalSeconds <= 0) return '00:00';

            const secs = parseInt(totalSeconds);
            const hrs = Math.floor(secs / 3600);
            const mins = Math.floor((secs % 3600) / 60);
            const remainingSecs = secs % 60;

            if (hrs > 0) {
                return `${hrs}:${mins.toString().padStart(2, '0')}:${remainingSecs.toString().padStart(2, '0')}`;
            }
            return `${mins}:${remainingSecs.toString().padStart(2, '0')}`;
        }

        renderAdultLockOverlay(item) {
            return `
            <div class="adult-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; z-index: 100; border-radius: 12px;">
                <div style="background: rgba(255,255,255,0.1); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                    <i class="fa-solid fa-lock" style="font-size: 32px; color: #ff4757;"></i>
                </div>
                <h3 style="margin: 0 0 10px 0; font-size: 20px;">Conteúdo Adulto</h3>
                <p style="margin: 0 0 20px 0; font-size: 14px; color: #aaa;">Apenas para maiores de 18 anos</p>
                <button onclick="window.verifyAge(this)" style="background: #ff4757; color: white; border: none; padding: 12px 30px; border-radius: 25px; font-weight: bold; cursor: pointer;">
                    <i class="fa-solid fa-check"></i> Tenho 18+ anos
                </button>
                <button onclick="window.scrollToReelByOffset(1)" style="background: transparent; color: #888; border: 1px solid #555; padding: 8px 20px; border-radius: 20px; margin-top: 10px; cursor: pointer;">
                    Pular
                </button>
            </div>
        `;
        }

        renderSensitiveOverlay(item) {
            return `
            <div class="sensitive-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; z-index: 101; border-radius: 12px; text-align: center; padding: 20px; backdrop-filter: blur(40px);">
                <i class="fas fa-eye-slash" style="font-size: 3rem; margin-bottom: 15px; color: #ff4757;"></i>
                <h3 style="margin: 0 0 10px 0; font-size: 22px; font-weight: bold;">Conteúdo Sensível</h3>
                <p style="margin: 0 0 20px 0; font-size: 14px; color: #eee;">Este vídeo pode conter conteúdo impróprio detetado pela nossa IA.</p>
                <button onclick="window.unlockSensitiveContent('${item.id}')" style="background: #fff; color: #000; border: none; padding: 12px 25px; border-radius: 25px; font-weight: bold; cursor: pointer; transition: transform 0.2s;">
                    Ver mesmo assim
                </button>
            </div>
        `;
        }

        renderSaleAcquiredOverlay() {
            return `
            <div style="position: absolute; top: 20px; left: 20px; background: rgba(0,0,0,0.7); color: #ffd700; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: bold; z-index: 50;">
                <i class="fa-solid fa-check-circle"></i> Conteúdo Adquirido
            </div>
        `;
        }

        escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const div = document.createElement('div');
            div.textContent = String(text);
            return div.innerHTML;
        }

        // ============================================================
        // LOAD REEL + PREVIEW LOOP + VIEWS
        // ============================================================
        loadReel() {
            const videos = this.scrollContainer.querySelectorAll('video.reels-video');
            const currentItem = this.state.currentItems[this.state.currentIndex];
            if (!currentItem) return;

            console.log(`[Reels] loadReel #${this.state.currentIndex}, duration: ${currentItem.duration}s`);

            this.stopAllPreviews();

            videos.forEach((v, idx) => {
                if (idx !== this.state.currentIndex) {
                    v.pause();
                    v.removeAttribute('src');
                    v.load();
                    v.currentTime = 0;
                    v.style.filter = '';
                    v.ontimeupdate = null;
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

                // ✅ FIX: Carregar comentários mesmo para vídeos bloqueados
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
                // Aplicar estilos inline no wrapper assim que as dimensões são conhecidas.
                // Inline style sobrepõe qualquer CSS externo sem precisar de !important.
                const wrapper = currentVideo.closest('.reel-video-wrapper');
                const isMobile = window.innerWidth <= 767;
                const isLandscapeVideo = currentVideo.videoWidth > currentVideo.videoHeight;

                if (wrapper && currentVideo.videoWidth && currentVideo.videoHeight) {
                    if (isLandscapeVideo && !isMobile) {
                        // DESKTOP landscape — caixa 16:9 centrada
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
                        // MOBILE landscape — fullscreen cover, igual ao portrait.
                        // A caixa 16:9 em ~375px daria só ~211px de altura (inaceitável).
                        // Actions ficam absolutas sobre o vídeo via CSS (posição: absolute em mobile).
                        wrapper.classList.remove('is-landscape'); // sem a classe 16:9 em mobile
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
                        // Portrait (mobile e desktop)
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
                            // Desktop portrait: repor estilos
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
            // FIX 11: ontimeupdate para loop de 5s de conteúdo sensível agora é
            // gerido dentro de setupProgressBar, eliminando a race condition.

            currentVideo.onplay = () => {
                this.sendViewRequest(currentItem.itemType, currentItem.itemId);
            };

            if (this.state.sidebarOpen) {
                this.loadComments();
            }
        }

        initPreview(item) {
            const previewId = `preview-${item.itemId}`;
            const video = document.getElementById(previewId);

            if (!video || this.state.activePreviews.has(previewId)) return;

            let started = false;
            let interval = null;

            const stop = () => {
                if (!started) return;
                started = false;
                clearInterval(interval);
                video.pause();
                video.style.opacity = '0.2';
                this.state.activePreviews.delete(previewId);
            };

            const start = () => {
                if (started) return;
                started = true;
                video.style.opacity = '1';
                video.currentTime = 0;

                video.play().then(() => {
                    interval = setInterval(() => {
                        if (video.currentTime >= 5) video.currentTime = 0;
                    }, 100);
                }).catch(() => {
                    video.style.opacity = '0.3';
                });

                this.state.activePreviews.set(previewId, { video, stop, interval });
            };

            video.addEventListener('canplay', start, { once: true });
            if (video.readyState >= 3) start();

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(e => {
                    if (!e.isIntersecting && started) {
                        stop();
                        observer.disconnect();
                    }
                });
            }, { threshold: 0.3 });
            observer.observe(video.closest('.reel-item'));
        }

        stopAllPreviews() {
            this.state.activePreviews.forEach(data => {
                clearInterval(data.interval);
                data.stop();
            });
            this.state.activePreviews.clear();
        }

        setupProgressBar(video) {
            const bar = video.closest('.reel-item')?.querySelector('.reels-progress-bar');
            if (!bar) return;

            // FIX 11: race condition — setupProgressBar sobrescrevia ontimeupdate já definido
            // para vídeo sensível (loop de 5s). Agora preserva ambos os handlers:
            // — atualiza a progress bar
            // — respeita o limite de 5s se o vídeo for sensível não-desbloqueado
            const currentItem = this.state.currentItems[this.state.currentIndex];
            const isSensitiveLoop = currentItem &&
                currentItem.aiStatus === 'done' &&
                currentItem.aiRisk !== 'low' &&
                !this.state.unlocked_content[currentItem.id];

            video.ontimeupdate = () => {
                if (video.duration) {
                    bar.style.width = `${(video.currentTime / video.duration) * 100}%`;
                }
                // Preservar limite de 5s para conteúdo sensível
                if (isSensitiveLoop && video.currentTime >= 5) {
                    video.currentTime = 0;
                }
            };
        }

        scrollToReel(index, smooth = true) {
            const h = this.scrollContainer?.clientHeight;
            if (!h) return;
            this.state.isScrolling = true;
            requestAnimationFrame(() => {
                this.scrollContainer.scrollTo({ top: index * h, behavior: smooth ? 'smooth' : 'auto' });
            });
            setTimeout(() => this.state.isScrolling = false, smooth ? 600 : 150);
        }

        scrollToReelByOffset(offset) {
            const newIndex = this.state.currentIndex + offset;
            if (newIndex >= 0 && newIndex < this.state.currentItems.length) {
                this.state.currentIndex = newIndex;
                this.scrollToReel(newIndex);
                // FIX 18: loadReel chamado com delay alinhado ao tempo de scroll (600ms smooth)
                // para evitar que o vídeo anterior seja carregado/pausado prematuramente.
                // O handler de scroll (handleScroll) já chama loadReel via timeout de 150ms,
                // mas scrollToReelByOffset forçava uma chamada imediata que criava conflito.
                setTimeout(() => {
                    if (this.state.currentIndex === newIndex) {
                        this.loadReel();
                    }
                }, 650);
            }
        }

        closeLightbox() {
            this.stopAllPreviews();
            this.scrollContainer?.querySelectorAll('video').forEach(v => {
                v.pause();
                v.removeAttribute('src');
                v.load();
            });
            this.lightbox?.classList.remove('active');
            document.body.classList.remove('lightbox-open');
            setTimeout(() => {
                this.lightbox && (this.lightbox.style.display = 'none');
                this.scrollContainer && (this.scrollContainer.innerHTML = '');
                this.state.currentItems = [];
                this.state.currentIndex = 0;
            }, 300);
        }

        toggleVideoPlayback() {
            const video = this.scrollContainer?.querySelectorAll('video.reels-video')[this.state.currentIndex];
            const item = this.state.currentItems[this.state.currentIndex];
            if (!video || !item) return;

            const locked = (item.isAdultContent && !this.state.userAgeVerified) ||
                (item.isForSale && !item.hasAccess && !item.isPostOwner);
            if (locked) {
                this.showToast('Conteúdo bloqueado', 'error');
                return;
            }

            video.paused ? video.play().then(() => this.updatePlayIcon(true)) : (video.pause(), this.updatePlayIcon(false));
        }

        updatePlayIcon(isPlaying) {
            const icon = this.scrollContainer?.querySelectorAll('.reel-item')[this.state.currentIndex]?.querySelector('[data-action="toggle-play"] i');
            if (icon) icon.className = isPlaying ? 'fa-solid fa-pause' : 'fa-solid fa-play';
        }

        /**
   * Toggle global mute state
   */
        toggleGlobalMute() {
            this.state.isMuted = !this.state.isMuted;

            const videos = this.scrollContainer?.querySelectorAll('video');
            videos?.forEach(v => v.muted = this.state.isMuted);

            const muteBtns = this.scrollContainer?.querySelectorAll('[data-action="toggle-mute"] i');
            muteBtns?.forEach(i => {
                i.className = `fa-solid ${this.state.isMuted ? 'fa-volume-mute' : 'fa-volume-high'}`;
            });

            const muteBtn = this.scrollContainer?.querySelector('[data-action="toggle-mute"]');
            if (muteBtn) {
                muteBtn.title = this.state.isMuted ? 'Ativar som' : 'Desativar som';
            }

            this.showToast(this.state.isMuted ? 'Som desativado' : 'Som ativado');
        }
        verifyAge(btn) {
            this.state.userAgeVerified = true;
            this.unblurContent(btn);
            this.showToast('Conteúdo desbloqueado!');
        }

        unblurContent(btn) {
            const item = this.state.currentItems[this.state.currentIndex];
            const reel = this.scrollContainer?.querySelector(`[data-feed-item-id="${item.id}"]`);
            if (reel) {
                reel.classList.remove('adult-content-locked');
                const video = reel.querySelector('video');
                if (video) {
                    video.style.filter = '';
                    video.src = item.src;
                    video.play().catch(() => { });
                }
                reel.querySelector('.adult-overlay')?.remove();
            }
        }

        toggleShareMenu(feedItemId) {
            const menu = document.getElementById(`premium-share-menu-${feedItemId}`);
            if (!menu) return;

            // Fecha outros menus abertos
            document.querySelectorAll('.reels-share-dropdown.is-open').forEach(m => {
                if (m !== menu) {
                    m.classList.remove('is-open');
                    m.setAttribute('aria-hidden', 'true');
                }
            });

            const isOpen = menu.classList.toggle('is-open');
            menu.setAttribute('aria-hidden', String(!isOpen));
        }

        handleRepost(feedItemId) {
            if (!confirm("Deseja repostar esta publicação?")) {
                return;
            }

            fetch(`${window.BASE_URL || ''}ajax/register_share.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `feed_item_id=${feedItemId}&action=repost`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // ✅ ATUALIZAR O CONTADOR NA UI DO MODAL
                        if (data.new_count !== undefined) {
                            this.updateShareCount(feedItemId, data.new_count);
                        }

                        // ✅ ATUALIZAR O CONTADOR NA PÁGINA PRINCIPAL (CASO EXISTA)
                        const mainCountEl = document.querySelector(`.post-card[data-feed-item-id="${feedItemId}"] #share-count-${feedItemId}`);
                        if (mainCountEl && data.new_count !== undefined) {
                            mainCountEl.textContent = data.new_count;
                        }

                        this.showToast(data.message || "Repost realizado com sucesso!");
                    } else {
                        this.showToast(data.error || "Erro ao repostar.", "error");
                    }
                })
                .catch(err => {
                    console.error("Erro na requisição:", err);
                    this.showToast("Erro ao processar o repost. Tente novamente.", "error");
                });
        }

        handleInteractions(action, element) {
            const item = this.state.currentItems[this.state.currentIndex];
            if (!item) return;

            const locked = (item.isAdultContent && !this.state.userAgeVerified) ||
                (item.isForSale && !item.hasAccess && !item.isPostOwner);

            if (locked && action !== 'close-lightbox') {
                this.showToast('Conteúdo bloqueado', 'error');
                return;
            }

            switch (action) {
                case 'like': this.performVote(item.id, 'like', element); break;
                case 'dislike': this.performVote(item.id, 'dislike', element); break;
                case 'comment': this.toggleSidebar(); break;
                case 'toggle-play': this.toggleVideoPlayback(); break;
                case 'toggle-mute': this.toggleGlobalMute(); break;
                case 'close-lightbox': this.closeLightbox(); break;
            }
        }

        async performVote(feedItemId, type, element) {
            const formData = new FormData();
            formData.append('feed_item_id', feedItemId);
            formData.append('action', type);

            try {
                const res = await fetch('api/photo_interactions.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    const reel = element.closest('.reel-item');
                    reel?.querySelector('.like-btn')?.classList.toggle('active', data.user_vote === 1);
                    const count = reel?.querySelector('.like-count');
                    if (count) count.textContent = data.likes;
                }
            } catch (e) {
                this.showToast('Erro ao votar', 'error');
            }
        }

        updateCommentCount(id, count) {
            const el = this.scrollContainer?.querySelector(`[data-feed-item-id="${id}"] .comment-count`);
            if (el) el.textContent = count;
        }

        deletePost(postId) {
            if (!confirm('Tem certeza?')) return;
            fetch('actions/post.php', {
                method: 'POST',
                body: new URLSearchParams({ action: 'delete_post', post_id: postId })
            }).then(res => res.json()).then(data => {
                if (data && data.success === false) {
                    this.showToast(data.message || 'Erro ao apagar post', 'error');
                    return;
                }
                this.showToast('Apagado!');
                // FIX 20: remover do estado e do DOM em vez de recarregar a página inteira.
                // O reload forçado interrompia a experiência de scrolling.
                const idx = this.state.currentItems.findIndex(i => String(i.id) === String(postId));
                if (idx !== -1) {
                    this.state.currentItems.splice(idx, 1);
                }
                // Remover o reel-item do DOM
                const reelEl = this.scrollContainer?.querySelector(`[data-feed-item-id="${postId}"]`);
                reelEl?.remove();
                // Remover o card do feed principal (se existir)
                const feedCard = document.querySelector(`.post-card[data-feed-item-id="${postId}"]`);
                feedCard?.remove();

                if (this.state.currentItems.length === 0) {
                    this.closeLightbox();
                } else {
                    // Ajustar índice se necessário
                    if (this.state.currentIndex >= this.state.currentItems.length) {
                        this.state.currentIndex = this.state.currentItems.length - 1;
                    }
                    this.scrollToReel(this.state.currentIndex, false);
                    this.loadReel();
                }
            }).catch(() => {
                this.showToast('Erro ao apagar. Tente novamente.', 'error');
            });
        }

        // Usa a função showToast do main.js se existir, senão cria uma fallback
        showToast(msg, type = 'success') {
            if (window.showToast && window.showToast !== this.showToast) {
                window.showToast(msg, type);
                return;
            }

            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:10000;';
                document.body.appendChild(container);
            }
            const toast = document.createElement('div');
            toast.textContent = msg;
            toast.style.cssText = `background:${type === 'error' ? '#e74c3c' : '#00f576'};color:${type === 'error' ? '#fff' : '#000'};padding:12px 24px;border-radius:25px;margin-top:10px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,0.3);animation:slideUp 0.3s ease;`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }
    }

    // ============================================
    // INICIALIZAÇÃO
    // ============================================
    document.addEventListener('DOMContentLoaded', () => {
        if (!window.reelsManager) {
            window.reelsManager = new ReelsManager();
            window.reelsManagerInstance = window.reelsManager;
            console.log('ReelsManager v4.6 inicializado (sem conflitos com main.js)');

            // FIX 19: expor funções globais mesmo quando #feedLightbox não existe na página,
            // para que chamadas PHP (onclick em HTML) não causem TypeError.
            // As funções verificam internamente se o lightbox existe antes de agir.
            if (!window.reelsManager.lightbox) {
                const noop = (msg) => () => console.warn(`[ReelsManager] ${msg} — #feedLightbox não encontrado nesta página.`);
                window.closeLightbox = noop('closeLightbox');
                window.scrollToReelByOffset = noop('scrollToReelByOffset');
                window.toggleGlobalMute = noop('toggleGlobalMute');
                window.toggleSidebar = noop('toggleSidebar');
                window.filterLightboxContent = noop('filterLightboxContent');
                window.toggleReelsComments = noop('toggleReelsComments');
                window.togglePremiumShareMenu = noop('togglePremiumShareMenu');
                // handleLockedReelClick e handleBuyClick são válidos mesmo sem lightbox
                // pois redirecionam para checkout/verification
                window.handleLockedReelClick = (el) => window.reelsManager.handleLockedReelClick(el);
                window.handleLockedVideoClickFromFeed = (el) => window.reelsManager.handleLockedVideoClickFromFeed(el);
                window.handleLockedVideoClick = (el) => window.reelsManager.handleLockedVideoClickFromFeed(el);
                window.copyToClipboard = (text, id) => window.reelsManager.copyToClipboard(text, id);
            }
        }
    });

} // Fim do guarda contra carga dupla