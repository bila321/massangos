(function buildLightbox() {
    const moreMenuHtml = VA_LB_IS_OWNER ? `
            <div class="va-lb-action-group">
                <button class="va-lb-action-btn va-lb-btn-dark" data-action="lb-more" title="Mais opções">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                </button>
                <div class="va-lb-more-menu" id="vaLbMoreMenu" style="display:none;">
                    <a href="${VA_LB_EDIT_URL}" class="va-lb-menu-item">
                        <i class="fa-solid fa-pen"></i> Editar álbum
                    </a>
                    <button class="va-lb-menu-item va-lb-menu-danger" data-action="delete-album" data-album-id="${ALBUM_ID}">
                        <i class="fa-solid fa-trash"></i> Apagar álbum
                    </button>
                </div>
            </div>` : '';

    const likeGroupHtml = VA_LB_HAS_FEED ? `
            <div class="va-lb-action-group">
                <button class="va-lb-action-btn va-lb-btn-like ${VA_LB_USER_VOTE === 'like' ? 'active' : ''}"
                    id="vaLbLikeBtn" data-action="lb-like" title="Curtir">
                    <i class="fa-regular fa-star"></i>
                </button>
                <span class="va-lb-action-label" id="vaLbLikeCount">${VA_LB_LIKES}</span>
            </div>` : '';

    const commentGroupHtml = VA_LB_HAS_FEED ? `
            <div class="va-lb-action-group">
                <button class="va-lb-action-btn va-lb-btn-dark" data-action="toggle-lb-comments" title="Comentários">
                    <i class="fa-regular fa-message"></i>
                </button>
                <span class="va-lb-action-label" id="vaLbCommentCount">0</span>
            </div>` : '';

    const commentInputHtml = VA_LB_HAS_FEED ? `
            <div class="va-lb-comment-form-area">
                <div class="va-lb-comment-form">
                    <img src="${VA_LB_ME_PIC}" class="va-lb-comment-avatar" alt="Tu">
                    <div class="va-lb-comment-input-wrap">
                        <textarea class="va-lb-comment-input" id="vaLbCommentInput"
                            placeholder="Adicione um comentário..." rows="1"></textarea>
                        <button class="va-lb-comment-send" data-action="lb-send-comment" title="Enviar">
                            <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>` : '';

    const lb = document.createElement('div');
    lb.className = 'va-lb-overlay';
    lb.id = 'vaLightbox';
    lb.setAttribute('role', 'dialog');
    lb.setAttribute('aria-modal', 'true');
    lb.innerHTML = `
            <!-- Botão Fechar -->
            <button class="va-lb-close-btn" data-action="lb-close" title="Fechar (Esc)">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <!-- Navegação -->
            <button class="va-lb-nav prev" id="vaLbPrev" data-action="lb-prev" title="Anterior">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button class="va-lb-nav next" id="vaLbNext" data-action="lb-next" title="Próxima">
                <i class="fa-solid fa-chevron-right"></i>
            </button>

            <!-- Conteúdo principal -->
            <div class="va-lb-main-content">
                <div class="va-lb-media" id="vaLbMedia">
                    <div id="vaLbImgWrap">
                        <div id="vaLbImgInner">
                            <img class="va-lb-img" id="vaLbImg" src="" alt="Foto do álbum">
                        </div>
                    </div>
                    <div class="va-lb-counter" id="vaLbCounter"></div>
                </div>
            </div>

            <!-- Barra inferior: Autor + Acções -->
            <div class="va-lb-bottom-bar">
                <!-- Informações do Autor -->
                <div class="va-lb-author-info">
                    <div class="va-lb-author-header">
                        <img src="${VA_LB_AUTHOR_AVATAR}" class="va-lb-author-avatar" alt="${VA_LB_AUTHOR_NAME}">
                        <div class="va-lb-author-text">
                            <a href="${VA_LB_AUTHOR_URL}" class="va-lb-author-name">${VA_LB_AUTHOR_NAME}</a>
                            <span class="va-lb-album-context">
                                <i class="fa-solid fa-images" style="font-size:10px;opacity:0.7;"></i>
                                ${VA_LB_ALBUM_TITLE}
                            </span>
                        </div>
                    </div>
                    <p class="va-lb-caption" id="vaLbCaption"></p>
                </div>

                <!-- Barra de Acções -->
                <div class="va-lb-action-bar">
                    ${likeGroupHtml}
                    ${commentGroupHtml}
                    <div class="va-lb-action-group">
                        <button class="va-lb-action-btn va-lb-btn-dark" data-action="lb-save" title="Guardar">
                            <i class="fa-regular fa-bookmark"></i>
                        </button>
                        <span class="va-lb-action-label" id="vaLbSaveCount">0</span>
                    </div>
                    <div class="va-lb-action-group">
                        <button class="va-lb-action-btn va-lb-btn-dark" data-action="lb-share" title="Partilhar">
                            <i class="fa-regular fa-paper-plane"></i>
                        </button>
                        <span class="va-lb-action-label" id="vaLbShareLabel">Partilhar</span>
                    </div>
                    ${moreMenuHtml}
                </div>
            </div>

            <!-- Sidebar de Comentários -->
            <aside class="va-lb-comments-sidebar" id="vaLbCommentsSidebar">
                <div class="va-lb-sidebar-header">
                    <h3><i class="fa-regular fa-message"></i> Comentários</h3>
                    <button class="va-lb-sidebar-close" data-action="toggle-lb-comments">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="va-lb-comments-list" id="vaLbCommentsWrap">
                    <div style="text-align:center;padding:30px;color:#aaa;">
                        <i class="fas fa-spinner fa-spin"></i> Carregando comentários...
                    </div>
                </div>
                ${commentInputHtml}
            </aside>`;

    // Injectar directamente no body
    document.body.appendChild(lb);

    // Auto-resize textarea
    const ta = lb.querySelector('#vaLbCommentInput');
    if (ta) {
        ta.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 80) + 'px';
        });
        ta.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                vaSubmitComment('vaLbCommentInput');
            }
        });
    }

    // Swipe mobile
    let sx = 0;
    const mainContent = lb.querySelector('.va-lb-main-content');
    mainContent.addEventListener('touchstart', e => {
        sx = e.touches[0].clientX;
    }, {
        passive: true
    });
    mainContent.addEventListener('touchend', e => {
        const dx = e.changedTouches[0].clientX - sx;
        if (Math.abs(dx) > 50) vaNavPhoto(dx < 0 ? 1 : -1);
    }, {
        passive: true
    });
})();

// …
// LIGHTBOX … abrir / fechar / navegar
// …
let vaLbCommentsLoaded = false;

function vaOpenLightbox(idx) {
    vaCurrentIdx = idx;
    // Abre sem blur apenas se esta foto foi revelada no grid pelo utilizador
    vaLbBlurRevealed = (vaRevealedThumbIdx === idx);
    vaLoadPhoto(idx);
    const lb = document.getElementById('vaLightbox');
    if (!lb) return;
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';
    vaUpdateNav();
    // Sincronizar contadores individuais da foto
    // Sincronizar contadores individuais da foto
    vaSyncPhotoCounters(VA_PHOTOS[idx]);
    vaLbCommentsLoaded = false;
}

function vaCloseLightbox() {
    const lb = document.getElementById('vaLightbox');
    if (!lb) return;
    lb.classList.remove('open');
    lb.classList.remove('sidebar-open');
    const sidebar = document.getElementById('vaLbCommentsSidebar');
    if (sidebar) sidebar.classList.remove('open');
    // Restaurar scroll … forçar mesmo que haja outros overlays
    document.body.style.overflow = '';
    document.documentElement.style.overflow = '';
    document.body.style.position = '';
    document.body.style.top = '';
    vaLbCommentsLoaded = false;
    vaLbBlurRevealed = false;
    // Restaurar blur em todas as fotos do grid … álbum volta ao estado protegido
    if (typeof vaRestoreAllThumbs === 'function') vaRestoreAllThumbs();
}

function vaToggleLbSidebar() {
    const lb = document.getElementById('vaLightbox');
    const sidebar = document.getElementById('vaLbCommentsSidebar');
    if (!sidebar) return;
    sidebar.classList.toggle('open');
    lb.classList.toggle('sidebar-open', sidebar.classList.contains('open'));
    if (sidebar.classList.contains('open')) {
        vaLbCommentsLoaded = false; // recarregar sempre … cada foto tem os seus comentarios
        vaReloadPhotoComments();
    }
}

function vaLoadPhoto(idx) {
    const photo = VA_PHOTOS[idx];
    if (!photo) return;

    const img = document.getElementById('vaLbImg');
    const imgInner = document.getElementById('vaLbImgInner');
    const mediaEl = document.getElementById('vaLbMedia');

    // … 1. Definir estado de blur ANTES de trocar a imagem …
    const needsBlur = photo.show_blur && !vaLbBlurRevealed;
    if (imgInner) imgInner.style.filter = needsBlur ? 'blur(18px) saturate(0.4)' : 'none';

    // … 2. Remover shield anterior …
    const oldShield = mediaEl.querySelector('.va-lb-blur-shield');
    if (oldShield) oldShield.remove();

    // … 3. Trocar imagem …
    img.classList.add('loading');
    const tmp = new Image();
    tmp.onload = function () {
        img.src = photo.src;
        img.classList.remove('loading');
    };
    tmp.onerror = function () {
        img.classList.remove('loading');
    };
    tmp.src = photo.src;

    // … 4. Metadata …
    const caption = document.getElementById('vaLbCaption');
    if (caption) caption.textContent = photo.caption || '';
    document.getElementById('vaLbCounter').textContent = (idx + 1) + ' | ' + VA_PHOTOS.length;
    vaUpdateNav();

    // ✅ CORREÇÃO: Sincronizar botões e contadores ao navegar entre fotos
    vaSyncPhotoCounters(photo);

    // … 5. Shield de blur (se necessário) …
    if (needsBlur) {
        const shield = document.createElement('div');
        shield.className = 'va-lb-blur-shield';
        shield.innerHTML = `
            <i class="fa-solid fa-eye-slash"></i>
            <p>Conteúdo pode ser explícito</p>
            <small>${photo.explicit_pct}% de conteúdo adulto detectado</small>
            <button class="va-lb-reveal-btn" id="vaLbRevealBtn">
                <i class="fa-solid fa-eye"></i>&nbsp; Ver mesmo assim
            </button>`;
        mediaEl.appendChild(shield);
        document.getElementById('vaLbRevealBtn').addEventListener('click', function (e) {
            e.stopPropagation();
            vaLbBlurRevealed = true;
            if (imgInner) {
                imgInner.style.transition = 'filter 0.25s ease';
                imgInner.style.filter = 'none';
                imgInner.addEventListener('transitionend', function cleanup() {
                    imgInner.style.transition = '';
                    imgInner.removeEventListener('transitionend', cleanup);
                });
            }
            shield.remove();
        });
    }
}

function vaSyncPhotoCounters(photo) {
    if (!photo) return;

    const lbLikeBtn =
        document.getElementById('vaLbLikeBtn');

    const lbLikeCnt =
        document.getElementById('vaLbLikeCount');

    const lbComCnt =
        document.getElementById('vaLbCommentCount');

    if (lbLikeBtn) {
        lbLikeBtn.classList.toggle(
            'active',
            !!photo.user_liked
        );
    }

    if (lbLikeCnt) {
        lbLikeCnt.textContent =
            photo.likes_count ?? 0;
    }

    if (lbComCnt) {
        lbComCnt.textContent =
            photo.comments_count ?? 0;
    }

    // ── Sincronizar ícone de bookmark da foto ──
    const lbSaveBtn = document.querySelector('[data-action="lb-save"]');
    if (lbSaveBtn) {
        lbSaveBtn.classList.toggle('active', !!photo.user_saved);
        const icon = lbSaveBtn.querySelector('i');
        if (icon) {
            icon.className = photo.user_saved
                ? 'fa-solid fa-bookmark'
                : 'fa-regular fa-bookmark';
        }
    }

    const lbSaveCnt = document.getElementById('vaLbSaveCount');
    if (lbSaveCnt) {
        lbSaveCnt.textContent = photo.saves_count ?? 0;
    }

    fetch(
        BASE_URL +
        'api/photo_interactions.php?action=comments&photo_id=' +
        photo.id
    )
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;

            photo.comments_count = data.total;

            const cnt =
                document.getElementById(
                    'vaLbCommentCount'
                );

            if (cnt) {
                cnt.textContent = data.total;
            }
        })
        .catch(() => { });
}


function vaUpdateNav() {
    const prev = document.getElementById('vaLbPrev');
    const next = document.getElementById('vaLbNext');
    if (prev) prev.disabled = vaCurrentIdx === 0;
    if (next) next.disabled = vaCurrentIdx === VA_PHOTOS.length - 1;
}

// ← ADICIONAR AQUI ↓
function vaNavPhoto(dir) {
    const next = vaCurrentIdx + dir;

    // Não sair dos limites do array
    if (next < 0 || next >= VA_PHOTOS.length) return;

    vaCurrentIdx = next;

    // Cada foto tem o seu próprio estado de blur
    vaLbBlurRevealed = false;

    // Carrega imagem + metadata + sincroniza contadores
    vaLoadPhoto(vaCurrentIdx);

    // Se sidebar estiver aberta, recarrega comentários da nova foto
    const sidebar = document.getElementById('vaLbCommentsSidebar');
    if (sidebar && sidebar.classList.contains('open')) {
        vaLbCommentsLoaded = false;
        vaReloadPhotoComments();
    }
}

// Determina se o input é do lightbox (foto) ou da página (álbum)
function vaSubmitComment(inputId) {
    const isLightbox = inputId === 'vaLbCommentInput';
    if (isLightbox) {
        vaSubmitPhotoComment(inputId);
    } else {
        vaSubmitAlbumComment(inputId);
    }
}

// Comentário numa foto individual … guardado em photo_comments
// e aparece também na área geral do álbum com marcador "#N"
function vaSubmitPhotoComment(inputId) {
    const ta = document.getElementById(inputId);
    const photo = VA_PHOTOS[vaCurrentIdx];
    if (!ta || !photo) return;
    const text = ta.value.trim();
    if (!text) return;

    const sendBtn = ta.closest('.va-lb-comment-input-wrap')?.querySelector('.va-lb-comment-send');
    if (sendBtn) sendBtn.disabled = true;

    fetch(BASE_URL + 'api/photo_interactions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ photo_id: photo.id, action: 'comment', content: text })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                ta.value = '';
                ta.style.height = 'auto';

                // Actualizar contador da foto
                photo.comments_count = (photo.comments_count ?? 0) + 1;
                const lbCnt = document.getElementById('vaLbCommentCount');
                if (lbCnt) lbCnt.textContent = photo.comments_count;

                // Injectar na área geral com marcador da foto
                const photoNum = vaCurrentIdx + 1;
                vaInjectPhotoCommentInAlbum(text, photoNum, vaCurrentIdx);

                // Actualizar contador geral
                const totalLabel = document.getElementById('vaPageCommentCountLabel');
                if (totalLabel) {
                    const cur = parseInt(totalLabel.textContent.replace(/\D/g, '')) || 0;
                    totalLabel.textContent = '(' + (cur + 1) + ')';
                }

                // Recarregar sidebar do lightbox
                vaLbCommentsLoaded = false;
                vaReloadPhotoComments();
            }
        })
        .catch(console.error)
        .finally(() => { if (sendBtn) sendBtn.disabled = false; });
}

// Comentário no álbum inteiro … guardado em comments via feed_item_id
function vaSubmitAlbumComment(inputId) {
    const ta = document.getElementById(inputId);
    if (!ta || !FEED_ITEM_ID) return;
    const text = ta.value.trim();
    if (!text) return;
    fetch(BASE_URL + 'api/comments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ feed_item_id: FEED_ITEM_ID, content: text })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success || data.comment) {
                ta.value = '';
                ta.style.height = 'auto';
                vaInjectAlbumCommentInList(text);
                const lbl = document.getElementById('vaPageCommentCountLabel');
                if (lbl) { const c = parseInt(lbl.textContent.replace(/\D/g, '')) || 0; lbl.textContent = '(' + (c + 1) + ')'; }
            }
        })
        .catch(console.error);
}

// Injectar comentário de foto na lista geral do álbum (sem reload)
// Injectar comentario geral do album no DOM sem reload
function vaInjectAlbumCommentInList(text) {
    const list = document.getElementById('vaCommentsListInline');
    if (!list) return;
    const empty = list.querySelector('.no-comments, .va-no-comments');
    if (empty) empty.remove();
    const meAvatar = typeof VA_LB_ME_PIC !== 'undefined' ? VA_LB_ME_PIC : '';
    const safe = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const now = new Date().toISOString().replace('T', ' ').substring(0, 19);
    const li = document.createElement('li');
    li.className = 'comment-item just-posted';
    li.dataset.likes = '0';
    li.dataset.createdAt = now;
    li.innerHTML = `
        <img src="${meAvatar}" class="comment-avatar" onerror="this.style.display='none'">
        <div class="comment-body">
            <div class="comment-text-wrapper">
                <div class="comment-text"><p>${safe}</p></div>
            </div>
            <div class="comment-actions">
                <span class="comment-time">agora</span>
                <button class="btn-comment-like" data-comment-id="0" data-vote-type="like" data-source="album" disabled>
                    <i class="fa-regular fa-heart"></i>
                    <span class="comment-likes-count"></span>
                </button>
            </div>
        </div>`;
    list.appendChild(li);
    li.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function vaInjectPhotoCommentInAlbum(text, photoNum, photoJsIdx) {
    const list = document.getElementById('vaCommentsListInline');
    if (!list) return;

    // Remover estado "sem comentários"
    const empty = list.querySelector('.va-no-comments');
    if (empty) empty.remove();

    const meAvatar = typeof VA_LB_ME_PIC !== 'undefined' ? VA_LB_ME_PIC : '';
    const safeText = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    const div = document.createElement('div');
    div.className = 'comment-item va-comment-from-photo';
    div.dataset.photoIdx = photoJsIdx;
    div.dataset.likes = '0';
    div.dataset.createdAt = new Date().toISOString().replace('T', ' ').substring(0, 19);
    div.title = 'Clique para abrir a Foto #' + photoNum;
    div.style.cursor = 'pointer';
    div.onclick = () => vaOpenLightbox(photoJsIdx);
    div.innerHTML = `
        <img src="${meAvatar}" class="comment-avatar" onerror="this.style.display='none'">
        <div class="comment-body">
            <div class="comment-text-wrapper">
                <div class="comment-header">
                    <span class="va-photo-comment-tag"
                        onclick="event.stopPropagation(); vaOpenLightbox(${photoJsIdx})"
                        title="Abrir Foto #${photoNum}" style="cursor:pointer;">
                        <i class="fa-solid fa-image"></i>
                        Foto #${photoNum}
                        <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:9px;opacity:0.6;"></i>
                    </span>
                </div>
                <div class="comment-text"><p>${safeText}</p></div>
            </div>
            <div class="comment-actions">
                <span class="comment-time">agora</span>
            </div>
        </div>`;

    list.appendChild(div);
    div.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}


// …
// APAGAR FOTO individual
// …
function vaDeletePhoto(photoId, btn) {
    if (!confirm('Apagar esta foto?')) return;
    fetch(BASE_URL + 'api/photos.php', {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            photo_id: photoId
        })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const thumb = btn.closest('.va-thumb');
                if (thumb) {
                    thumb.style.opacity = '0';
                    thumb.style.transform = 'scale(0.85)';
                    thumb.style.transition = '0.3s';
                    setTimeout(() => thumb.remove(), 300);
                }
            }
        })
        .catch(console.error);
}

// …
// LIKE individual da FOTO no lightbox
// …
function vaPerformPhotoLike(btn) {
    const photo = VA_PHOTOS[vaCurrentIdx];
    if (!photo || !photo.id) return;

    if (btn) btn.disabled = true;

    fetch(BASE_URL + 'api/photo_interactions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            photo_id: photo.id,
            action: 'like'
        })
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;

            // Guardar estado actualizado na foto actual
            photo.user_liked = !!data.liked;
            photo.likes_count = data.likes ?? 0;

            // Actualizar botão do lightbox
            const lbLikeBtn = document.getElementById('vaLbLikeBtn');
            const lbLikeCnt = document.getElementById('vaLbLikeCount');

            if (lbLikeBtn) {
                lbLikeBtn.classList.toggle('active', !!data.liked);
            }

            if (lbLikeCnt) {
                lbLikeCnt.textContent = data.likes ?? 0;
            }
        })
        .catch(console.error)
        .finally(() => {
            if (btn) btn.disabled = false;
        });
}
// …
// LIKE … reutilizável para página e lightbox
// …
function vaPerformLike(btn) {
    if (!FEED_ITEM_ID) return;
    const fd = new FormData();
    fd.append('feed_item_id', FEED_ITEM_ID);
    fd.append('action', 'like');

    fetch(BASE_URL + 'api/photo_interactions.php', {
        method: 'POST',
        body: fd
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const isActive = data.user_vote === 1 || data.user_vote === 'like';
                document.querySelectorAll('[data-action="like"]').forEach(b => {
                    b.classList.toggle('active', isActive);
                });
                const pageCnt = document.getElementById('vaPageLikeCount');
                if (pageCnt && data.likes !== undefined) pageCnt.textContent = data.likes;
            }
        })
        .catch(console.error);
}

// …
// GUARDAR … reutilizável para página e lightbox
// …
function vaPerformSave(btn) {
    if (!FEED_ITEM_ID) return;
    const fd = new FormData();
    fd.append('feed_item_id', FEED_ITEM_ID);
    fd.append('action', 'save');

    fetch(BASE_URL + 'ajax/toggle_save.php', {
        method: 'POST',
        body: fd
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Sincronizar todos os botões de save
                document.querySelectorAll('[data-action="save"], [data-action="lb-save"]').forEach(b => {
                    b.classList.toggle('active', !!data.saved);
                    const icon = b.querySelector('i');
                    if (icon) icon.className = data.saved ? 'fa-solid fa-bookmark' : 'fa-regular fa-bookmark';
                });
            }
        })
        .catch(console.error);
}

// ─────────────────────────────────────────────
// Abrir automaticamente uma foto quando a URL
// contém #photo-123
// ─────────────────────────────────────────────
window.addEventListener('load', function () {
    const hash = window.location.hash || '';

    const m = hash.match(/^#photo-(\d+)$/);
    if (!m) return;

    const photoId = parseInt(m[1], 10);

    const idx = VA_PHOTOS.findIndex(
        p => parseInt(p.id, 10) === photoId
    );

    if (idx >= 0) {
        setTimeout(() => {
            vaOpenLightbox(idx);
        }, 300);
    }
});

// …
// SAVE da FOTO individual no lightbox — usa toggle_save.php com item_type=photo
// …
let vaPhotoSaveBusy = false;

function vaPerformPhotoSave(btn, event) {
    // Só aceitar clique real do utilizador
    if (!event || event.isTrusted !== true) return;

    // Evitar chamadas repetidas
    if (vaPhotoSaveBusy) return;

    const photo = VA_PHOTOS[vaCurrentIdx];
    if (!photo || !photo.id) return;

    vaPhotoSaveBusy = true;
    if (btn) btn.disabled = true;

    const fd = new FormData();
    fd.append('item_type', 'photo');
    fd.append('item_id', photo.id);
    fd.append('csrf_token', CSRF_TOKEN);

    fetch(BASE_URL + 'ajax/toggle_save.php', {
        method: 'POST',
        body: fd
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                console.error('Erro ao guardar foto:', data);
                return;
            }

            photo.user_saved = !!data.saved;

            const lbSaveBtn = document.querySelector('[data-action="lb-save"]');

            if (lbSaveBtn) {
                lbSaveBtn.classList.toggle('active', !!data.saved);

                const icon = lbSaveBtn.querySelector('i');
                if (icon) {
                    icon.className = data.saved
                        ? 'fa-solid fa-bookmark'
                        : 'fa-regular fa-bookmark';
                }
            }
            // Actualizar contador
            photo.saves_count = (photo.saves_count ?? 0) + (data.saved ? 1 : -1);
            if (photo.saves_count < 0) photo.saves_count = 0;

            const lbSaveCnt = document.getElementById('vaLbSaveCount');
            if (lbSaveCnt) {
                lbSaveCnt.textContent = photo.saves_count;
            }
        })
        .catch(console.error)
        .finally(() => {
            vaPhotoSaveBusy = false;
            if (btn) btn.disabled = false;
        });
}

// …
// PARTILHAR
// …
function vaPerformShare() {
    const url = window.location.href;
    if (navigator.share) {
        navigator.share({
            url
        }).catch(() => { });
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
            // feedback visual breve
            document.querySelectorAll('[data-action="share"], [data-action="lb-share"]').forEach(b => {
                const orig = b.innerHTML;
                b.innerHTML = '<i class="fa-solid fa-check"></i>';
                setTimeout(() => b.innerHTML = orig, 2000);
            });
        });
    }
}

// …
// APAGAR …LBUM
// …
function vaDeleteAlbum(albumId) {
    if (!confirm('Tem certeza que deseja apagar este álbum? Esta ação não pode ser desfeita.')) return;
    const fd = new FormData();
    fd.append('action', 'delete_album');
    fd.append('album_id', albumId);
    fd.append('redirect_to', 'index.php');

    fetch(BASE_URL + 'actions/album.php', {
        method: 'POST',
        body: fd
    })
        .then(() => {
            window.location.href = BASE_URL + 'index.php';
        })
        .catch(console.error);
}

// …
// DELEGA…O DE EVENTOS … padrão data-action (post.php)
// …
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) {
        // Fechar menus ao clicar fora
        document.querySelectorAll('.va-lb-more-menu, .va-more-menu').forEach(m => m.style.display = 'none');
        return;
    }

    const action = btn.dataset.action;

    switch (action) {

        // … Fechar lightbox …
        case 'lb-close':
            vaCloseLightbox();
            break;

        // … Navegar (lightbox) …
        case 'lb-prev':
            vaNavPhoto(-1);
            break;
        case 'lb-next':
            vaNavPhoto(1);
            break;

        case 'like':
            e.stopPropagation();
            vaPerformLike(btn);
            break;

        case 'lb-like':
            e.stopPropagation();
            vaPerformPhotoLike(btn);
            break;

        // … Scroll para comentários (página) …
        case 'scroll-comments':
            e.stopPropagation();
            document.getElementById('vaCommentInput')?.focus({
                preventScroll: false
            });
            document.querySelector('.va-comments')?.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
            break;

        // … Toggle sidebar de comentários (lightbox) …
        case 'toggle-lb-comments':
            e.stopPropagation();
            vaToggleLbSidebar();
            break;

        // … Guardar (página e lightbox) …
        case 'save':
            e.stopPropagation();
            vaPerformSave(btn);
            break;

        case 'lb-save':
            e.preventDefault();
            e.stopPropagation();
            vaPerformPhotoSave(btn, e);
            break;

        // … Partilhar (página e lightbox) …
        case 'share':
        case 'lb-share':
            e.stopPropagation();
            vaPerformShare();
            break;

        // … Enviar comentário (lightbox) …
        case 'lb-send-comment':
            e.stopPropagation();
            vaSubmitComment('vaLbCommentInput');
            break;

        // … Menu mais (página) …
        case 'more-page':
            e.stopPropagation();
            const pageMenu = document.getElementById('vaPageMoreMenu');
            if (pageMenu) pageMenu.style.display = pageMenu.style.display === 'none' ? 'block' : 'none';
            break;

        // … Menu mais (lightbox) …
        case 'lb-more':
            e.stopPropagation();
            const lbMenu = document.getElementById('vaLbMoreMenu');
            if (lbMenu) lbMenu.style.display = lbMenu.style.display === 'none' ? 'block' : 'none';
            break;

        // … Apagar álbum …
        case 'delete-album':
            e.stopPropagation();
            document.querySelectorAll('.va-lb-more-menu, .va-more-menu').forEach(m => m.style.display = 'none');
            vaDeleteAlbum(btn.dataset.albumId);
            break;
    }
});

// …
// TECLADO … ESC fecha lightbox, … → navega
// …
document.addEventListener('keydown', function (e) {
    const lb = document.getElementById('vaLightbox');
    if (!lb || !lb.classList.contains('open')) return;
    if (e.key === 'Escape') vaCloseLightbox();
    else if (e.key === 'ArrowLeft') vaNavPhoto(-1);
    else if (e.key === 'ArrowRight') vaNavPhoto(1);
});

// … Auto-resize textareas da P…GINA …
document.querySelectorAll('.va-comment-textarea').forEach(function (ta) {
    ta.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });
    ta.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (this.id) vaSubmitComment(this.id);
        }
    });
});// …
// RENDER DE COMENT…RIOS … com likes e respostas
// Substitui o bloco vaReloadPhotoComments existente
// …

// Construir HTML de um comentário (raiz ou resposta)
function vaRenderComment(c, isReply) {
    const pic = c.profile_picture ? (UPLOAD_URL + c.profile_picture) : '';
    const safe = (c.content || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const liked = c.user_liked ? 'active' : '';
    const likes = c.likes_count || 0;

    // Permissões: dono do comentário ou dono do álbum
    const isCommentOwner = (typeof CURRENT_USER_ID !== 'undefined') && CURRENT_USER_ID && (c.user_id == CURRENT_USER_ID);
    const isAlbumOwner = (typeof VA_LB_IS_OWNER !== 'undefined') && VA_LB_IS_OWNER;
    const canDelete = isCommentOwner || isAlbumOwner;
    const isLoggedIn = (typeof CURRENT_USER_ID !== 'undefined') && !!CURRENT_USER_ID;

    // Dropdown "⋮" — só renderiza se houver pelo menos uma acção disponível
    const dropdownHtml = (isCommentOwner || canDelete) ? `
        <div class="comment-actions-dropdown">
            <button class="dropdown-toggle" aria-label="Opções do comentário" aria-expanded="false">&#x22EE;</button>
            <div class="dropdown-menu" style="display:none;">
                ${isCommentOwner ? `<button class="edit-comment-btn"
                    data-comment-id="${c.id}"
                    data-content="${safe}"
                    data-source="photo">Editar</button>` : ''}
                ${canDelete ? `<button class="delete-comment-btn"
                    data-comment-id="${c.id}"
                    data-source="photo">Apagar</button>` : ''}
            </div>
        </div>` : '';

    // Respostas aninhadas
    let repliesHtml = '';
    if (!isReply && c.replies && c.replies.length > 0) {
        repliesHtml =
            '<ul class="comment-list comment-replies">' +
            c.replies.map(r => vaRenderComment(r, true)).join('') +
            '</ul>';
    }

    return `
    <li class="comment-item" data-comment-id="${c.id}" data-user-id="${c.user_id || ''}">
        <img src="${pic}" class="comment-avatar"
             onerror="this.style.visibility='hidden'">
        <div class="comment-body">
            <div class="comment-text-wrapper">
                <div class="comment-header">
                    <span class="comment-author">${c.username || ''}</span>
                    ${dropdownHtml}
                </div>
                <div class="comment-text"><p>${safe}</p></div>
            </div>
            <div class="comment-actions">
                <span class="comment-time">${c.pc_time || ''}</span>
                <button class="btn-comment-like ${liked}"
                       data-comment-id="${c.id}"
                       data-vote-type="like"
                       data-source="photo"
                       title="Gostei">
                       <i class="fa-${c.user_liked ? 'solid' : 'regular'} fa-heart"></i>
                    <span class="comment-likes-count">${likes > 0 ? likes : ''}</span>
                </button>
                ${isLoggedIn && !isReply ? `<button class="btn-reply-comment"
                        data-comment-id="${c.id}"
                        data-author="${c.username || ''}"
                        title="Responder">
                    Responder
                </button>` : ''}
            </div>
            ${isLoggedIn && !isReply ? `<div class="reply-form-container" id="replyFormContainer-lb-${c.id}" style="display:none;">
                <div class="reply-input-area">
                    <img src="${typeof VA_LB_ME_PIC !== 'undefined' ? VA_LB_ME_PIC : ''}" class="comment-avatar reply-form-avatar" onerror="this.style.display='none'">
                    <textarea name="comment_content" class="reply-textarea"
                        placeholder="Responder a ${c.username || ''}…" rows="1"></textarea>
                    <button type="button" class="btn-send-comment va-lb-reply-send" data-comment-id="${c.id}">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>` : ''}
        </div>
        ${repliesHtml}
    </li>`;
}

// Recarregar comentários da FOTO no sidebar do lightbox
function vaReloadPhotoComments() {
    const photo = VA_PHOTOS[vaCurrentIdx];
    if (!photo) return;

    fetch(BASE_URL + 'api/photo_interactions.php?action=comments&photo_id=' + photo.id)
        .then(r => r.json())
        .then(data => {
            const wrap = document.getElementById('vaLbCommentsWrap');
            if (!wrap) return;

            if (data.comments && data.comments.length > 0) {
                wrap.innerHTML = '<ul class="comment-list comments-list">' +
                    data.comments.map(c => vaRenderComment(c, false)).join('') +
                    '</ul>';
            } else {
                wrap.innerHTML = '<div class="no-comments"><i class="fa-regular fa-comment-dots"></i><br>Nenhum comentário ainda.<br>Seja o primeiro!</div>';
            }

            // Actualizar contador
            photo.comments_count = data.total;
            const lbCnt = document.getElementById('vaLbCommentCount');
            if (lbCnt) lbCnt.textContent = data.total;

            wrap.scrollTop = wrap.scrollHeight;
        })
        .catch(console.error);
}

// Alias
function vaReloadComments() { vaReloadPhotoComments(); }

// Toggle form de resposta no lightbox (usa replyFormContainer-lb-{id})
function vaShowReplyInput(commentId, btn) {
    document.querySelectorAll('.reply-form-container').forEach(f => {
        if (f.id !== 'replyFormContainer-lb-' + commentId) f.style.display = 'none';
    });

    const container = document.getElementById('replyFormContainer-lb-' + commentId);
    if (!container) return;

    const isOpen = container.style.display !== 'none';
    container.style.display = isOpen ? 'none' : 'block';

    if (!isOpen) {
        const ta = container.querySelector('textarea[name="comment_content"]');
        if (ta) {
            ta.focus();
            ta.addEventListener('input', function () {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
            ta.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    vaSubmitReply(commentId);
                }
            });
        }
    }
}

// Submeter resposta no lightbox
function vaSubmitReply(parentCommentId) {
    const container = document.getElementById('replyFormContainer-lb-' + parentCommentId);
    const photo = VA_PHOTOS[vaCurrentIdx];
    if (!container || !photo) return;

    const ta = container.querySelector('textarea[name="comment_content"]');
    if (!ta) return;

    const text = ta.value.trim();
    if (!text) { ta.focus(); return; }

    const sendBtn = container.querySelector('.va-lb-reply-send');
    if (sendBtn) sendBtn.disabled = true;

    fetch(BASE_URL + 'api/photo_interactions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            photo_id: photo.id,
            action: 'comment',
            content: text,
            parent_comment_id: parentCommentId
        })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                ta.value = '';
                container.style.display = 'none';
                vaLbCommentsLoaded = false;
                vaReloadPhotoComments();
            }
        })
        .catch(console.error)
        .finally(() => { if (sendBtn) sendBtn.disabled = false; });
}

// Handler para botão Enviar no lightbox (delegação)
document.addEventListener('click', function (e) {
    const sendBtn = e.target.closest('.va-lb-reply-send');
    if (!sendBtn) return;
    e.stopPropagation();
    vaSubmitReply(sendBtn.dataset.commentId);
});


// ═══════════════════════════════════════════════════════════════════
// SISTEMA DE COMENTÁRIOS — lista inline do álbum
// Likes + respostas para comentários do álbum e de fotos
// ═══════════════════════════════════════════════════════════════════

// ── Delegação unificada de cliques ────────────────────────────────
document.addEventListener('click', function (e) {

    // 1a. Like em comentário do álbum (li.comment-item sem va-comment-from-photo)
    // 1b. Like em comentário de foto (li.comment-item.va-comment-from-photo)
    // SUBSTITUIR o bloco 1a/1b (linhas ~1147-1158):
    var likeBtn = e.target.closest('.btn-comment-like');
    if (likeBtn) {
        e.stopPropagation();
        // Comentário de foto: tem data-source="photo" (na página e no lightbox)
        if (likeBtn.dataset.source === 'photo') {
            vaTogglePhotoCommentLike(likeBtn);
        } else {
            vaToggleAlbumCommentLike(likeBtn);
        }
        return;
    }

    // 2. Responder em comentário de foto (div.va-comment-from-photo)
    var replyBtn = e.target.closest('.btn-reply-comment');
    if (replyBtn && replyBtn.classList.contains('va-pc-reply-btn')) {
        e.preventDefault();
        e.stopPropagation();
        var photoItem = replyBtn.closest('.comment-item');
        if (photoItem) vaShowPhotoCommentReplyForm(photoItem, replyBtn.dataset.commentId, replyBtn);
        return;
    }

    // 3. Responder em comentário do álbum (li.comment-item)
    if (replyBtn) {
        e.preventDefault();
        e.stopPropagation();
        vaToggleAlbumReplyForm(replyBtn.dataset.commentId);
        return;
    }

    // 4. Cancelar resposta
    var cancelBtn = e.target.closest('.cancel-reply-btn');
    if (cancelBtn) {
        e.preventDefault();
        var cid = cancelBtn.dataset.commentId;
        var fc = document.getElementById('replyFormContainer-' + cid);
        if (fc) fc.style.display = 'none';
        return;
    }

    // 5. Toggle "Ver/Ocultar respostas"
    var toggleBtn = e.target.closest('.btn-toggle-replies');
    if (toggleBtn) {
        var parentLi = document.querySelector('.comment-item[data-comment-id="' + toggleBtn.dataset.commentId + '"]');
        if (!parentLi) return;
        var repliesUl = parentLi.querySelector('ul.comment-replies');
        if (!repliesUl) return;
        var isOpen = toggleBtn.dataset.open === 'true';
        repliesUl.style.display = isOpen ? 'none' : 'block';
        toggleBtn.dataset.open = isOpen ? 'false' : 'true';
        var count = repliesUl.querySelectorAll(':scope > li.comment-item').length;
        toggleBtn.textContent = isOpen
            ? 'Ver ' + count + ' resposta' + (count !== 1 ? 's' : '')
            : 'Ocultar respostas';
        return;
    }

    // 6. Toggle dropdown "⋮"
    var dropToggle = e.target.closest('.dropdown-toggle');
    if (dropToggle) {
        e.stopPropagation();
        var menu = dropToggle.nextElementSibling;
        if (!menu || !menu.classList.contains('dropdown-menu')) return;
        var isVisible = menu.style.display !== 'none';
        // Fecha todos os dropdowns abertos antes de abrir este
        document.querySelectorAll('.dropdown-menu').forEach(function (m) {
            m.style.display = 'none';
        });
        document.querySelectorAll('.dropdown-toggle').forEach(function (b) {
            b.setAttribute('aria-expanded', 'false');
        });
        if (!isVisible) {
            menu.style.display = 'block';
            dropToggle.setAttribute('aria-expanded', 'true');
        }
        return;
    }

}, true);

// Fechar dropdowns ao clicar fora
document.addEventListener('click', function (e) {
    if (!e.target.closest('.comment-actions-dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(function (m) {
            m.style.display = 'none';
        });
        document.querySelectorAll('.dropdown-toggle').forEach(function (b) {
            b.setAttribute('aria-expanded', 'false');
        });
    }
});

// ── Editar comentário ────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.edit-comment-btn');
    if (!btn) return;
    e.stopPropagation();

    var commentId = btn.dataset.commentId;
    var source = btn.dataset.source || 'album'; // 'album' | 'photo'
    var current = btn.dataset.content || '';

    // Fechar dropdown
    var menu = btn.closest('.dropdown-menu');
    if (menu) menu.style.display = 'none';

    // Encontrar a bolha do comentário
    var item = document.querySelector('.comment-item[data-comment-id="' + commentId + '"]');
    if (!item) return;
    var textEl = item.querySelector('.comment-text p');
    if (!textEl) return;

    // Evitar abrir dois editores no mesmo comentário
    if (item.querySelector('.va-edit-form')) return;

    var editForm = document.createElement('div');
    editForm.className = 'va-edit-form';
    editForm.innerHTML =
        '<textarea class="va-edit-textarea reply-textarea">' +
        current.replace(/</g, '&lt;').replace(/>/g, '&gt;') +
        '</textarea>' +
        '<div class="reply-form-actions">' +
        '<button class="reply-btn-cancel va-edit-cancel">Cancelar</button>' +
        '<button class="reply-btn-send va-edit-save" data-comment-id="' + commentId + '" data-source="' + source + '">Guardar</button>' +
        '</div>';

    // Substitui o parágrafo pelo form de edição
    textEl.parentNode.insertBefore(editForm, textEl);
    textEl.style.display = 'none';
    editForm.querySelector('.va-edit-textarea').focus();
});

// Cancelar edição
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.va-edit-cancel');
    if (!btn) return;
    var form = btn.closest('.va-edit-form');
    if (!form) return;
    var textEl = form.parentNode.querySelector('.comment-text p');
    if (textEl) textEl.style.display = '';
    form.remove();
});

// Guardar edição
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.va-edit-save');
    if (!btn || btn.dataset.busy === '1') return;

    var commentId = btn.dataset.commentId;
    var source = btn.dataset.source || 'album';
    var form = btn.closest('.va-edit-form');
    var ta = form ? form.querySelector('.va-edit-textarea') : null;
    if (!ta) return;

    var newContent = ta.value.trim();
    if (!newContent) { ta.focus(); return; }

    btn.dataset.busy = '1';
    btn.textContent = '…';

    var endpoint = source === 'photo'
        ? (window.BASE_URL || '/') + 'api/photo_interactions.php'
        : (window.BASE_URL || '/') + 'api/comments.php';

    var payload = source === 'photo'
        ? { action: 'edit_comment', comment_id: parseInt(commentId, 10), content: newContent }
        : { action: 'edit', id: parseInt(commentId, 10), content: newContent };

    if (window.CSRF_TOKEN) payload.csrf_token = window.CSRF_TOKEN;

    fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                var item = document.querySelector('.comment-item[data-comment-id="' + commentId + '"]');
                if (item) {
                    var textEl = item.querySelector('.comment-text p');
                    if (textEl) {
                        textEl.textContent = newContent;
                        textEl.style.display = '';
                    }
                    // Actualizar data-content no botão de editar para a próxima vez
                    var editBtn = item.querySelector('.edit-comment-btn');
                    if (editBtn) editBtn.dataset.content = newContent;
                }
                if (form) form.remove();
            } else {
                alert(data.error || 'Erro ao guardar. Tenta novamente.');
                btn.dataset.busy = '0';
                btn.textContent = 'Guardar';
            }
        })
        .catch(function () {
            alert('Erro de rede. Tenta novamente.');
            btn.dataset.busy = '0';
            btn.textContent = 'Guardar';
        });
});

// ── Apagar comentário ────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.delete-comment-btn');
    if (!btn) return;
    e.stopPropagation();

    var commentId = btn.dataset.commentId;
    var source = btn.dataset.source || 'album';

    var menu = btn.closest('.dropdown-menu');
    if (menu) menu.style.display = 'none';

    if (!confirm('Apagar este comentário? Esta acção não pode ser desfeita.')) return;

    var endpoint = source === 'photo'
        ? (window.BASE_URL || '/') + 'api/photo_interactions.php'
        : (window.BASE_URL || '/') + 'api/comments.php';

    var payload = source === 'photo'
        ? { action: 'delete_comment', comment_id: parseInt(commentId, 10) }
        : { action: 'delete', id: parseInt(commentId, 10) };

    if (window.CSRF_TOKEN) payload.csrf_token = window.CSRF_TOKEN;

    fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                // Remove o <li> ou <ul> wrapper do comentário
                var item = document.querySelector('.comment-item[data-comment-id="' + commentId + '"]');
                if (item) {
                    // Se o pai for um <ul> com só este filho, remove o <ul> também
                    var parent = item.parentNode;
                    item.remove();
                    if (parent && parent.tagName === 'UL' && parent.children.length === 0) {
                        parent.remove();
                    }
                }
                // Actualizar contador
                var countLabel = document.getElementById('vaPageCommentCountLabel');
                if (countLabel) {
                    var n = parseInt(countLabel.textContent.replace(/\D/g, ''), 10) || 1;
                    countLabel.textContent = '(' + Math.max(0, n - 1) + ')';
                }
            } else {
                alert(data.error || 'Erro ao apagar. Tenta novamente.');
            }
        })
        .catch(function () {
            alert('Erro de rede. Tenta novamente.');
        });
});

// ── Intercept submit do form de resposta → AJAX ───────────────────
document.addEventListener('submit', function (e) {
    var form = e.target.closest('.reply-form');
    if (!form) return;
    e.preventDefault();
    var container = form.closest('.reply-form-container');
    var commentId = container ? container.id.replace('replyFormContainer-', '') : null;
    if (commentId) vaSubmitAlbumReply(commentId, container);
});

function vaTogglePhotoCommentLike(btn) {
    if (!btn || btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';

    var commentId = parseInt(btn.dataset.commentId, 10);

    fetch((window.BASE_URL || '/') + 'api/photo_interactions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
            action: 'like_comment',
            comment_id: commentId
        })
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                btn.classList.toggle('active', !!data.liked);
                var icon = btn.querySelector('i');
                if (icon) icon.className = data.liked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
                var countEl = btn.querySelector('.comment-likes-count');
                if (countEl) countEl.textContent = (data.likes > 0) ? data.likes : '';
            }
        })
        .catch(function (err) { console.error('Erro like comentario foto:', err); })
        .finally(function () { btn.dataset.busy = '0'; });
}

// ── Like num comentário do álbum ──────────────────────────────────
function vaToggleAlbumCommentLike(btn) {
    if (!btn || btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';

    var commentId = parseInt(btn.dataset.commentId, 10);
    if (!commentId) { btn.dataset.busy = '0'; return; }

    fetch((window.BASE_URL || '/') + 'api/comments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'vote_comment', comment_id: commentId, vote_type: 'like' })
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success) return;
            var liked = data.user_vote === 'like';
            var likes = parseInt(data.likes, 10) || 0;
            btn.classList.toggle('active', liked);
            var countEl = btn.querySelector('.comment-likes-count');
            if (countEl) countEl.textContent = likes > 0 ? likes : '';
            var item = btn.closest('.comment-item');
            if (item) item.dataset.likes = likes;
            vaReorderAlbumCommentsByLikes();
        })
        .catch(function (err) { console.error('Erro like comentario:', err); })
        .finally(function () { btn.dataset.busy = '0'; });
}

// ── Reordenar comentários por likes ──────────────────────────────
function vaReorderAlbumCommentsByLikes() {
    var list = document.getElementById('vaCommentsListInline');
    if (!list) return;
    var items = Array.prototype.slice.call(
        list.querySelectorAll(':scope > .comment-item, :scope > li.comment-item')
    );
    if (items.length < 2) return;
    items.sort(function (a, b) {
        var la = parseInt(a.dataset.likes, 10) || 0;
        var lb = parseInt(b.dataset.likes, 10) || 0;
        if (lb !== la) return lb - la;
        return (a.dataset.createdAt || '') < (b.dataset.createdAt || '') ? -1 : 1;
    });
    items.forEach(function (item) {
        item.style.transition = 'opacity 0.2s';
        item.style.opacity = '0.5';
        list.appendChild(item);
    });
    setTimeout(function () { items.forEach(function (i) { i.style.opacity = '1'; }); }, 50);
}

// ── Toggle form de resposta (comentários do álbum) ────────────────
function vaToggleAlbumReplyForm(commentId) {
    document.querySelectorAll('.reply-form-container').forEach(function (c) {
        if (c.id !== 'replyFormContainer-' + commentId) c.style.display = 'none';
    });

    var container = document.getElementById('replyFormContainer-' + commentId);
    if (!container) return;

    var isOpen = container.style.display === 'block';
    container.style.display = isOpen ? 'none' : 'block';

    if (!isOpen) {
        var ta = container.querySelector('textarea[name="comment_content"]');
        if (ta) {
            ta.focus();
            ta.addEventListener('input', function () {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
            ta.addEventListener('keydown', function (ev) {
                if (ev.key === 'Enter' && !ev.shiftKey) {
                    ev.preventDefault();
                    vaSubmitAlbumReply(commentId, container);
                }
            });
        }
    }
}

// ── Enviar resposta a comentário do álbum (AJAX) ──────────────────
function vaSubmitAlbumReply(parentCommentId, container) {
    if (!container) container = document.getElementById('replyFormContainer-' + parentCommentId);
    if (!container) return;

    var ta = container.querySelector('textarea[name="comment_content"]');
    var feedItemIdInput = container.querySelector('input[name="feed_item_id"]');
    if (!ta || !feedItemIdInput) return;

    var text = ta.value.trim();
    if (!text) { ta.focus(); return; }

    var feedItemId = parseInt(feedItemIdInput.value, 10);
    if (!feedItemId) return;

    var submitBtn = container.querySelector('button[type="submit"]');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = '…'; }

    fetch((window.BASE_URL || '/') + 'api/comments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({
            action: 'add_reply',
            feed_item_id: feedItemId,
            content: text,
            parent_comment_id: parseInt(parentCommentId, 10)
        })
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || !data.success) {
                alert(data.message || 'Erro ao enviar resposta.');
                return;
            }
            ta.value = '';
            ta.style.height = 'auto';
            container.style.display = 'none';
            vaInjectAlbumReplyInList(parentCommentId, data);
            var countLabel = document.getElementById('vaPageCommentCountLabel');
            if (countLabel && data.total) countLabel.textContent = '(' + data.total + ')';
        })
        .catch(function (err) { console.error('Erro reply álbum:', err); })
        .finally(function () {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Responder'; }
        });
}

// ── Injectar resposta no DOM (comentários do álbum) ───────────────
function vaInjectAlbumReplyInList(parentCommentId, data) {
    var parentLi = document.querySelector('.comment-item[data-comment-id="' + parentCommentId + '"]');
    if (!parentLi) return;

    var meAvatar = typeof VA_LB_ME_PIC !== 'undefined' ? VA_LB_ME_PIC : '';
    var meUsername = typeof VA_ME_USERNAME !== 'undefined' ? VA_ME_USERNAME : '';
    var safe = (data.content || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    var now = new Date().toISOString().replace('T', ' ').substring(0, 19);

    var li = document.createElement('li');
    li.className = 'comment-item just-posted';
    li.dataset.commentId = data.comment_id || '0';
    li.dataset.likes = '0';
    li.dataset.createdAt = now;
    li.style.marginLeft = '25px';
    li.innerHTML =
        '<img src="' + meAvatar + '" class="comment-avatar" onerror="this.style.display=\'none\'">' +
        '<div class="comment-body">' +
        '<div class="comment-text-wrapper">' +
        '<div class="comment-header">' +
        '<span class="comment-author">' + meUsername + '</span>' +
        '</div>' +
        '<div class="comment-text"><p>' + safe + '</p></div>' +
        '</div>' +
        '<div class="comment-actions">' +
        '<span class="comment-time">agora</span>' +
        '<button class="btn-comment-like" data-comment-id="' + (data.comment_id || '0') + '" data-vote-type="like" data-source="album">' +
        '<i class="fa-regular fa-heart"></i> <span class="comment-likes-count"></span>' +
        '</button>' +
        '</div>' +
        '</div>';

    var repliesUl = parentLi.querySelector('ul.comment-replies');
    if (!repliesUl) {
        repliesUl = document.createElement('ul');
        repliesUl.className = 'comment-list comment-replies';
        var existingToggle = parentLi.querySelector('.btn-toggle-replies');
        if (existingToggle) {
            parentLi.querySelector('.comment-body').insertBefore(repliesUl, existingToggle);
        } else {
            parentLi.querySelector('.comment-body').appendChild(repliesUl);
        }
    }
    repliesUl.style.display = 'block';
    repliesUl.appendChild(li);
    li.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    // Actualizar/criar btn-toggle-replies
    var count = repliesUl.querySelectorAll(':scope > li.comment-item').length;
    var toggleBtn = parentLi.querySelector('.btn-toggle-replies');
    if (!toggleBtn) {
        toggleBtn = document.createElement('button');
        toggleBtn.className = 'btn-toggle-replies';
        toggleBtn.dataset.commentId = parentCommentId;
        parentLi.querySelector('.comment-body').appendChild(toggleBtn);
    }
    toggleBtn.textContent = 'Ocultar respostas';
    toggleBtn.dataset.open = 'true';
}

// ── Form inline de resposta para comentários de foto ─────────────
function vaShowPhotoCommentReplyForm(commentItem, commentId, btn) {
    var existingForm = commentItem.querySelector('.va-inline-reply-form');
    if (existingForm) { existingForm.remove(); return; }

    document.querySelectorAll('.va-inline-reply-form').forEach(function (f) { f.remove(); });

    var photoId = parseInt(commentItem.dataset.photoId, 10);
    var photoIdx = parseInt(commentItem.dataset.photoIdx, 10);
    var author = btn.dataset.author || '';
    var meAvatar = typeof VA_LB_ME_PIC !== 'undefined' ? VA_LB_ME_PIC : '';

    var form = document.createElement('div');
    form.className = 'va-inline-reply-form';
    form.innerHTML =
        '<img src="' + meAvatar + '" class="va-irf-avatar comment-avatar" onerror="this.style.display=\'none\'">' +
        '<div class="va-irf-body">' +
        '<textarea class="va-reply-ta va-irf-textarea" placeholder="Responder a ' + author + '…" rows="2"></textarea>' +
        '<div class="va-irf-actions">' +
        '<button class="va-reply-cancel va-irf-btn-cancel">Cancelar</button>' +
        '<button class="va-reply-send va-irf-btn-send">Enviar</button>' +
        '</div>' +
        '</div>';

    commentItem.querySelector('.comment-body').appendChild(form);
    var ta = form.querySelector('.va-reply-ta');
    ta.focus();

    form.querySelector('.va-reply-cancel').addEventListener('click', function () { form.remove(); });

    function sendPhotoReply() {
        var text = ta.value.trim();
        if (!text) return;
        var sendBtn = form.querySelector('.va-reply-send');
        sendBtn.disabled = true;

        fetch((window.BASE_URL || '/') + 'api/photo_interactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'comment',
                photo_id: photoId,
                content: text,
                parent_comment_id: parseInt(commentId, 10)
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) { alert(data.message || 'Erro ao responder.'); return; }
                form.remove();

                // Injectar a resposta no vaReplies-{id} se existir no DOM
                var repliesDiv = document.getElementById('vaReplies-' + commentId);
                if (repliesDiv) {
                    var safe = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                    var now = new Date().toISOString().replace('T', ' ').substring(0, 19);
                    var d = document.createElement('div');
                    d.className = 'comment-item va-pc-reply va-pc-reply--injected just-posted';
                    d.dataset.likes = '0';
                    d.dataset.createdAt = now;
                    d.innerHTML =
                        '<img src="' + meAvatar + '" class="comment-avatar" onerror="this.style.display=\'none\'">' +
                        '<div class="comment-body">' +
                        '<div class="comment-text-wrapper"><div class="comment-text"><p>' + safe + '</p></div></div>' +
                        '<div class="comment-actions"><span class="comment-time"></span></div>' +
                        '</div>';
                    repliesDiv.appendChild(d);
                    d.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }

                // Recarregar sidebar do lightbox se aberto na mesma foto
                var curIdx = typeof vaCurrentIdx !== 'undefined' ? vaCurrentIdx
                    : (window.vaCurrentIdx !== undefined ? window.vaCurrentIdx : -1);
                if (typeof vaLbCommentsLoaded !== 'undefined' && curIdx === photoIdx) {
                    vaLbCommentsLoaded = false;
                    if (typeof vaReloadPhotoComments === 'function') vaReloadPhotoComments();
                }
            })
            .catch(function (err) { console.error('Erro reply foto:', err); })
            .finally(function () { sendBtn.disabled = false; });
    }

    form.querySelector('.va-reply-send').addEventListener('click', sendPhotoReply);
    ta.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); sendPhotoReply(); }
    });
}