// public/assets/js/track_views.js
// Contagem de visualizacoes para videos e albuns

/**
 * Envia requisicao para contar visualizacao
 * @param {string} itemType - 'video' ou 'album'
 * @param {number} itemId - ID do item
 * @param {number} feedItemId - ID do feed item (opcional, para atualizar UI)
 */
function sendViewRequest(itemType, itemId, feedItemId) {
    if (!itemId || itemId <= 0) {
        console.warn('sendViewRequest: itemId invalido', itemId);
        return;
    }

    const formData = new FormData();
    formData.append('item_type', itemType);
    formData.append('item_id', itemId);

    fetch(`${window.BASE_URL || ''}actions/view.php`, {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && !data.already_counted && data.new_count) {
                updateViewCountUI(itemType, itemId, data.new_count);
            }
        })
        .catch(error => {
            // Silencioso - nao quebra a experiencia
            console.debug('View tracking error:', error);
        });
}

/**
 * Atualiza o contador de visualizacoes na UI
 * @param {string} itemType
 * @param {number} itemId
 * @param {number} newCount
 */
function updateViewCountUI(itemType, itemId, newCount) {
    // Procura por elementos com data-views-id
    const viewEl = document.querySelector(`[data-views-id="${itemType}-${itemId}"]`);
    if (viewEl) {
        viewEl.textContent = newCount.toLocaleString('pt-PT') + ' visualizacoes';
        return;
    }

    // Fallback: procura dentro do post-card
    const selectors = [
        `.post-card[data-feed-item-id] video[data-item-id="${itemId}"]`,
        `.post-card[data-feed-item-id] .video-stats`,
        `.post-card[data-feed-item-id] a[href*="view_album.php?id=${itemId}"]`
    ];

    selectors.forEach(selector => {
        const el = document.querySelector(selector);
        if (el) {
            const container = el.closest('.post-card') || el.closest('.single-post-section');
            if (container) {
                const statsEl = container.querySelector('.video-stats span, .video-stats');
                if (statsEl) {
                    const icon = statsEl.querySelector('i');
                    if (icon) {
                        statsEl.innerHTML = `<i class="fa-solid fa-eye"></i> ${newCount.toLocaleString('pt-PT')} visualizacoes`;
                    } else {
                        statsEl.textContent = newCount.toLocaleString('pt-PT') + ' visualizacoes';
                    }
                }
            }
        }
    });
}

// ============================================================
// INTERSECTION OBSERVER - Conta quando elemento entra na tela
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    const viewedItems = new Set();

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const itemType = el.dataset.itemType;
                const itemId = parseInt(el.dataset.itemId);

                if (itemType && itemId && !viewedItems.has(`${itemType}-${itemId}`)) {
                    viewedItems.add(`${itemType}-${itemId}`);
                    sendViewRequest(itemType, itemId);
                }
            }
        });
    }, {
        threshold: 0.5 // 50% visivel
    });

    // Observa videos no feed: cobre <div class="lightbox-trigger"> com thumbnail
    // e tambem <video> directo (sem thumbnail). Ambos tem data-item-type e data-item-id.
    document.querySelectorAll(
        '.post-card .lightbox-trigger[data-item-type="video"][data-item-id],' +
        '.post-card video[data-item-type="video"][data-item-id],' +
        '.single-post-section video[data-item-type="video"][data-item-id]'
    ).forEach(el => observer.observe(el));

    // Observa albuns no feed
    document.querySelectorAll('.post-card .album-cover-link[data-item-id]').forEach(link => {
        if (!link.dataset.itemType) link.dataset.itemType = 'album';
        observer.observe(link);
    });
});