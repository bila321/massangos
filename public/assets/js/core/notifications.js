/**
 * massangosu — Notifications (v3)
 * Correcções:
 *  1. Seletor de classe corrigido: `.notification-item` → `.notif-item`
 *  2. Deteção de "não lida" corrigida: `.notification-item.read` → `.notif-item:not(.is-unread)`
 *  3. Botão "marcar como lida" correcto: `.btn-mark-read` dentro de `.notif-item`
 *  4. BASE_URL lido de window.BASE_URL (injetado pelo PHP)
 *  5. Feedback visual imediato ao marcar/limpar sem reload
 */

document.addEventListener('DOMContentLoaded', function () {

    const list = document.getElementById('notificationsList');
    const clearBtn = document.querySelector('.btn-clear-notifications');

    /* ── 1. Marcar notificação individual como lida ──────────────────── */
    if (list) {
        list.addEventListener('click', function (e) {

            /* Clique no botão "Marcar como lida" */
            const markBtn = e.target.closest('.btn-mark-read');
            if (!markBtn) return;

            /* Item pai (.notif-item) */
            const item = markBtn.closest('.notif-item');
            if (!item) return;

            const notifId = item.dataset.notificationId;
            if (!notifId) return;

            e.preventDefault();

            markAsRead(notifId)
                .then(ok => {
                    if (ok) {
                        /* Remove classe de não lida e esconde o botão */
                        item.classList.remove('is-unread');
                        const avatarWrap = item.querySelector('.notif-avatar-wrap');
                        if (avatarWrap) avatarWrap.classList.remove('is-unread');

                        const timeEl = item.querySelector('.notif-time');
                        if (timeEl) timeEl.classList.remove('fresh');

                        const actions = item.querySelector('.notif-actions');
                        if (actions) actions.remove();

                        /* Remove o ponto indicador (pseudo-element via classe) */
                        /* A classe is-unread já foi removida — o ::before desaparece */
                    }
                })
                .catch(err => console.error('Erro ao marcar como lida:', err));
        });
    }

    /* ── 2. Limpar todas as notificações lidas ───────────────────────── */
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {

            if (!confirm('Tem certeza que deseja limpar todas as notificações lidas?')) return;

            const baseUrl = (typeof window.BASE_URL !== 'undefined')
                ? window.BASE_URL
                : '/';

            fetch(baseUrl + 'process_notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'clear_read' })
            })
                .then(res => {
                    if (!res.ok) {
                        return res.text().then(txt => {
                            throw new Error(`HTTP ${res.status}: ${txt}`);
                        });
                    }
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        /* Remove visualmente os itens lidos (.notif-item sem .is-unread) */
                        list.querySelectorAll('.notif-item:not(.is-unread)').forEach(el => el.remove());

                        /* Remove separadores de grupo que ficaram órfãos */
                        _cleanOrphanGroupLabels();

                        /* Se não restar nenhum item, mostra estado vazio */
                        if (list.querySelectorAll('.notif-item').length === 0) {
                            list.innerHTML = `
                            <div class="notif-empty">
                                <i class="fa-solid fa-bell-slash"></i>
                                <p>Ainda não tens notificações.</p>
                            </div>`;
                        }

                        /* Actualiza contador global se disponível */
                        if (typeof updateUnreadCount === 'function') {
                            updateUnreadCount(data.unread_count ?? 0);
                        }

                        alert(data.message || 'Notificações lidas eliminadas.');
                    } else {
                        alert(data.message || 'Erro ao limpar notificações.');
                    }
                })
                .catch(err => {
                    console.error('Erro ao limpar notificações:', err);
                    alert('Ocorreu um erro ao tentar limpar as notificações. Tente novamente.');
                });
        });
    }

    /* ── Helpers privados ────────────────────────────────────────────── */

    /**
     * Envia pedido ao servidor para marcar uma notificação como lida.
     * @param {string|number} id
     * @returns {Promise<boolean>}
     */
    function markAsRead(id) {
        const baseUrl = (typeof window.BASE_URL !== 'undefined')
            ? window.BASE_URL
            : '/';

        return fetch(baseUrl + 'process_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'mark_read', notification_id: id })
        })
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    /* Actualiza contador global se disponível */
                    if (typeof updateUnreadCount === 'function') {
                        updateUnreadCount(data.unread_count ?? 0);
                    }
                    return true;
                }
                console.warn('Servidor recusou mark_read:', data.message);
                return false;
            });
    }

    /**
     * Remove labels de grupo (.notif-group-label) que não tenham
     * nenhum .notif-item a seguir (após remoção dos lidos).
     */
    function _cleanOrphanGroupLabels() {
        list.querySelectorAll('.notif-group-label').forEach(label => {
            /* Procura o próximo sibling que seja um notif-item */
            let next = label.nextElementSibling;
            while (next && next.classList.contains('notif-group-label')) {
                next = next.nextElementSibling;
            }
            if (!next || !next.classList.contains('notif-item')) {
                label.remove();
            }
        });
    }
});