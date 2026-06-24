/**
 * saved.js — Pagina de itens guardados
 * Depende de: window.BASE_URL, window.CSRF_TOKEN
 */
async function unsaveItem(btn, itemId, itemType) {
    btn.disabled = true;
    const card = btn.closest('.saved-grid-item');
    try {
        const res = await fetch(window.BASE_URL + 'ajax/toggle_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                csrf_token: window.CSRF_TOKEN,
                item_type: itemType,
                item_id: itemId,
            }),
        });
        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await res.text();
            console.error('Resposta inesperada:', text);
            btn.disabled = false;
            return;
        }
        const data = await res.json();
        if (data.success && !data.saved) {
            card.style.transition = 'opacity 0.3s, transform 0.3s';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.85)';
            setTimeout(() => card.remove(), 300);
            const countEl = document.querySelector('.saved-count');
            if (countEl) {
                const newCount = Math.max(0, (parseInt(countEl.textContent) || 0) - 1);
                countEl.textContent = newCount + ' item' + (newCount !== 1 ? 's' : '');
            }
        } else {
            btn.disabled = false;
        }
    } catch (err) {
        console.error('Erro ao remover guardado:', err);
        btn.disabled = false;
    }
}

function unblurSaved(blurId) {
    const card = document.getElementById(blurId);
    if (!card) return;
    const blurImg = card.querySelector('.media-blur');
    if (blurImg) blurImg.classList.remove('media-blur');
    const container = card.querySelector('.media-blur-container');
    if (container) container.classList.remove('media-blur-container');
    const overlay = card.querySelector('.media-overlay-msg');
    if (overlay) overlay.remove();
}

// === PREMIUM LIGHTBOX PARA VÍDEOS SALVOS - VERSÃO FORÇADA E FULLSCREEN ===
document.addEventListener('click', function (e) {
    const trigger = e.target.closest('.premium-lightbox-trigger');
    if (!trigger) return;

    e.preventDefault();
    const itemId = parseInt(trigger.dataset.itemId);
    if (!itemId) return;

    console.log('🎥 Abrindo vídeo salvo ID:', itemId);

    const rm = window.reelsManagerInstance;
    if (!rm) {
        console.error('ReelsManager não encontrado');
        return;
    }

    // Forçar fullscreen e limpar layout
    document.body.classList.add('lightbox-open');
    if (rm.lightbox) {
        rm.lightbox.style.display = 'flex';
        rm.lightbox.classList.add('active');
        rm.lightbox.style.zIndex = '99999';
    }

    // Item completo manual
    const fakeItem = {
        id: itemId,
        itemId: itemId,
        itemType: 'video',
        src: trigger.dataset.src || trigger.dataset.videoUrl || '',
        thumbnail: trigger.querySelector('img') ? trigger.querySelector('img').src : '',
        caption:
            trigger.closest('.saved-grid-item')?.querySelector('.saved-item-meta span')
                ?.textContent || '',
        viewsCount: '0',
        likesCount: '0',
        commentsCount: '0',
        authorName: 'Utilizador',
        authorThumb: '',
        authorUrl: '#',
        isForSale: false,
        hasAccess: true,
        aiStatus: 'low',
        aiRisk: 'low',
        duration: 15,
        videoWidth: 1280,
        videoHeight: 720,
    };

    rm.state.currentItems = [fakeItem];
    rm.state.currentIndex = 0;

    rm.renderReels();
    rm.loadReel();

    console.log('✅ Lightbox forçado com item manual + fullscreen');
});
