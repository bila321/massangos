<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\User;
use Massango\Models\Notification;

if (!is_logged_in()) {
    redirect(BASE_URL . 'login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['follower_id'])) {
    $current_user_id = get_current_user_id();
    $follower_id = (int)$_POST['follower_id'];
    $action = $_POST['action'];
    $redirect_url = $_POST['redirect_url'] ?? BASE_URL . 'notifications.php';

    $follower_user = User::getUserById($pdo, $follower_id);
    if (!$follower_user) {
        set_message("Usuário não encontrado.", "danger");
        redirect($redirect_url);
    }

    $current_user_data = User::getUserById($pdo, $current_user_id);
    $current_user_username = $current_user_data['username'] ?? 'Alguém';

    if ($action === 'accept') {
        if (User::acceptFollowRequest($pdo, $follower_id, $current_user_id)) {
            set_message("Você aceitou o pedido de seguimento de " . htmlspecialchars($follower_user['username']) . "!", "success");

            // Notificar o seguidor que o pedido foi aceite
            Notification::createNotification(
                $pdo,
                $follower_id,
                "O usuário " . htmlspecialchars($current_user_username) . " aceitou seu pedido de seguimento.",
                BASE_URL . 'profile.php?id=' . $current_user_id,
                $current_user_id,
                'follow_request_accepted',
                $current_user_id
            );

            // Marcar a notificação de pedido como lida (opcional, se houver uma específica)
        } else {
            set_message("Erro ao aceitar pedido.", "danger");
        }
    } elseif ($action === 'reject') {
        if (User::rejectFollowRequest($pdo, $follower_id, $current_user_id)) {
            set_message("Você rejeitou o pedido de seguimento de " . htmlspecialchars($follower_user['username']) . ".", "info");
        } else {
            set_message("Erro ao rejeitar pedido.", "danger");
        }
    }

    redirect($redirect_url);
} else {
    redirect(BASE_URL);
}
