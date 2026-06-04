/**
 * Sistema de Interações Massangos
 * Gerencia Repost, Partilha de Link e outras interações dinâmicas.
 */

// ============================================
// CONFIGURAÇÕES GLOBAIS (Fallbacks)
// ============================================

if (typeof BASE_URL === 'undefined') {
    // Este bloco NÃO deve executar em produção.
    // Se executar, significa que header2.php não está a emitir window.BASE_URL.
    const _fallback = window.location.origin + '/massango/public/';
    console.error(
        '[Massangos] BASE_URL não foi definido pelo servidor PHP.\n' +
        'Fallback aplicado: ' + _fallback + '\n' +
        'Verifique: header2.php deve conter <script>window.BASE_URL = <?= json_encode(BASE_URL) ?>;<\/script>'
    );
    window.BASE_URL = _fallback;
}

// ============================================
// FUNÇÕES GLOBAIS (acessíveis via onclick)
// ============================================

/**
 * Alterna a visibilidade do menu de partilha.
 * @param {number} postId - ID do post.
 */
function toggleShareMenu(postId, event) {
    if (event) event.stopPropagation();

    const menu = document.getElementById(`share-menu-${postId}`);
    if (!menu) {
        console.error(`Menu de partilha não encontrado: share-menu-${postId}`);
        return;
    }

    // Fecha outros menus abertos primeiro
    document.querySelectorAll('.share-dropdown').forEach(m => {
        if (m.id !== `share-menu-${postId}`) m.style.display = 'none';
    });

    const isVisible = menu.style.display === 'block';
    menu.style.display = isVisible ? 'none' : 'block';
}

/**
 * Copia o link do post para a área de transferência e registra a partilha.
 * @param {string} text - URL do post.
 * @param {number} feedItemId - ID do item do feed.
 */
function copyToClipboard(text, feedItemId, event) {
    if (event) event.stopPropagation();

    navigator.clipboard.writeText(text).then(() => {
        alert("Link copiado para a área de transferência!");

        // Registrar a partilha do tipo 'link' via AJAX
        fetch(`${window.BASE_URL}ajax/register_share.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `feed_item_id=${feedItemId}&action=link`
        });

        // Fechar o menu após copiar
        const menu = document.getElementById(`share-menu-${feedItemId}`);
        if (menu) menu.style.display = 'none';
    }).catch(err => {
        console.error("Erro ao copiar link:", err);

        // Fallback para browsers antigos ou sem HTTPS
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        try {
            document.execCommand('copy');
            alert("Link copiado para a área de transferência!");
        } catch (e) {
            alert("Não foi possível copiar o link automaticamente.");
        }

        document.body.removeChild(textarea);
    });
}

/**
 * Gerencia o Repost de uma publicação.
 * @param {number} feedItemId - ID do item do feed.
 */
function handleRepost(feedItemId, event) {
    if (event) event.stopPropagation();

    if (!confirm("Deseja repostar esta publicação?")) {
        return;
    }

    fetch(`${window.BASE_URL}ajax/register_share.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `feed_item_id=${feedItemId}&action=repost`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || "Repost realizado com sucesso!");
                location.reload();
            } else {
                alert(data.error || "Erro ao repostar.");
            }
        })
        .catch(err => {
            console.error("Erro na requisição:", err);
            alert("Erro ao processar o repost. Tente novamente.");
        });
}

window.App = window.App || {};

/**
 * Gerencia o botão de Seguir/Deixar de Seguir via AJAX.
 * @param {number} userId - ID do usuário a ser seguido.
 * @param {HTMLElement} btn - O elemento do botão clicado.
 */
App.toggleFollow = function (userId, btn) {
    if (btn.disabled) return;
    btn.disabled = true;

    const formData = new FormData();
    formData.append('user_id', userId);

    fetch(`${window.BASE_URL}ajax/follow_toggle.php`, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                btn.textContent = data.label;
                btn.classList.toggle('following', data.action === 'followed');
                btn.classList.toggle('pending', data.action === 'requested');
            } else {
                alert(data.error || "Erro ao processar ação de seguir.");
            }
        })
        .catch(err => {
            console.error("Erro na requisição:", err);
            alert("Erro de conexão ao tentar seguir.");
        })
        .finally(() => {
            btn.disabled = false;
        });
};

// Fallback para compatibilidade temporária se necessário, 
// mas o objetivo é migrar tudo para App.toggleFollow
window.toggleFollow = App.toggleFollow;

/**
 * Oculta uma publicação do feed via AJAX.
 * @param {number} feedItemId - ID do item do feed.
 * @param {HTMLElement} btn - O elemento do botão clicado.
 */
function hidePost(feedItemId, btn) {
    if (!confirm("Deseja ocultar esta publicação do seu feed?")) return;

    const card = btn.closest('.post-card');
    if (card) {
        card.style.transition = 'all 0.3s ease';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.9)';
        setTimeout(() => card.style.display = 'none', 300);
    }

    const formData = new FormData();
    formData.append('feed_item_id', feedItemId);

    fetch(`${window.BASE_URL}ajax/hide_post.php`, {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error("Erro ao ocultar no banco:", data.error);
            }
        })
        .catch(err => console.error("Erro na requisição:", err));
}

// ============================================
// DOMContentLoaded - Event Listeners
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    /**
     * Fechar menus de partilha ao clicar fora.
     */
    document.addEventListener('click', (event) => {
        if (!event.target.closest('.share-container')) {
            document.querySelectorAll('.share-dropdown').forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });

    // Auto-esconder alertas após 5 segundos
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease-out';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Fechar modais genéricos
    document.querySelectorAll('.close-button').forEach(button => {
        button.addEventListener('click', () => {
            const modal = button.closest('.modal');
            if (modal) modal.style.display = 'none';
        });
    });

    window.addEventListener('click', (event) => {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });
});