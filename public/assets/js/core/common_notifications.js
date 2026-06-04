// assets/js/common_notifications.js

/**
 * Marca uma notificação específica como lida via AJAX e atualiza a UI.
 * @param {number} notificationId O ID da notificação a ser marcada.
 * @param {HTMLElement} notificationItem O elemento DOM (<li>) da notificação na lista.
 * @returns {Promise<boolean>} Uma promessa que resolve para TRUE se a operação foi bem-sucedida, FALSE caso contrário.
 */
function markNotificationAsRead(notificationId, notificationItem) {
    // Certifique-se de que BASE_URL está definida globalmente (ex: no <head> via PHP)
    if (typeof BASE_URL === 'undefined' || BASE_URL === null) {
        console.error('Erro: BASE_URL não definida. Certifique-se de que está configurada no seu HTML.');
        return Promise.resolve(false); // Retorna uma promessa resolvida com false
    }

    return fetch(BASE_URL + 'process_notification.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        // *** MUDANÇA AQUI: Usando URLSearchParams para formatar o corpo da requisição ***
        body: new URLSearchParams({
            'action': 'mark_read',
            'notification_id': notificationId
        }).toString()
    })
    .then(response => {
        if (!response.ok) {
            // Lança um erro se a resposta HTTP não for bem-sucedida (ex: 404, 500)
            return response.text().then(text => {
                console.error(`HTTP error! status: ${response.status}, message: ${text}`);
                throw new Error(`HTTP error! status: ${response.status}, message: ${text}`);
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            console.log('Notificação marcada como lida:', data.message);
            // Atualiza o estado visual do item da notificação
            notificationItem.classList.add('read');
            notificationItem.classList.remove('unread');
            
            // Remove o botão "Marcar como lida" se existir
            const markReadBtn = notificationItem.querySelector('.btn-mark-read');
            if (markReadBtn) {
                markReadBtn.remove();
            }

            // Atualiza a contagem de notificações não lidas no UI (ex: no cabeçalho)
            if (typeof updateUnreadCount === 'function') {
                updateUnreadCount(data.unread_count);
            } else {
                console.warn("Função updateUnreadCount não definida em common_notifications.js.");
            }
            return true; // Indica sucesso
        } else {
            console.error('Erro ao marcar notificação como lida:', data.message);
            alert('Erro ao marcar notificação como lida: ' + (data.message || 'Erro desconhecido.'));
            return false; // Indica falha
        }
    })
    .catch(error => {
        console.error('Falha na requisição AJAX para marcar notificação:', error);
        alert('Ocorreu um erro ao tentar marcar a notificação. Tente novamente.');
        return false; // Indica falha
    });
}

/**
 * Atualiza a contagem de notificações não lidas na interface do usuário (UI).
 * Procura um elemento com o ID 'unreadNotificationsCount' para exibir a contagem.
 * @param {number} count A nova contagem de notificações não lidas.
 */
function updateUnreadCount(count) {
    const unreadCountElement = document.getElementById('unreadNotificationsCount');
    if (unreadCountElement) {
        unreadCountElement.textContent = count;
        // Mostra ou esconde o elemento baseado na contagem
        if (count > 0) {
            unreadCountElement.style.display = 'inline-block'; // Ou 'flex', dependendo do seu CSS
        } else {
            unreadCountElement.style.display = 'none';
        }
    } else {
        console.warn("Elemento com ID 'unreadNotificationsCount' não encontrado para atualizar a contagem.");
    }
}
