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
            })
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