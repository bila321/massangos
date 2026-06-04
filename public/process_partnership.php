<?php
// public/process_partnership.php

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\AlbumPartner;
use Massango\Models\Notification;

SecurityManager::initSecurity();

if (!is_logged_in()) {
    set_message("Você precisa estar logado para realizar esta ação.", "danger");
    redirect(BASE_URL . 'login.php');
    exit();
}

$user_id = get_current_user_id();
$action = $_POST['action'] ?? '';
$partner_id = (int)($_POST['partner_id'] ?? 0);

if ($partner_id <= 0) {
    set_message("ID de parceria inválido.", "danger");
    redirect(BASE_URL . 'notifications.php');
    exit();
}

try {
    // Verificar se a parceria pertence ao usuário logado
    $stmt = $pdo->prepare("
        SELECT ap.id, ap.album_id, ap.user_id, a.user_id as creator_id, a.name as album_name
        FROM album_partners ap
        JOIN albums a ON ap.album_id = a.id
        WHERE ap.id = ?
    ");
    $stmt->execute([$partner_id]);
    $partnership = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$partnership) {
        set_message("Parceria não encontrada.", "danger");
        redirect(BASE_URL . 'notifications.php');
        exit();
    }

    if ($partnership['user_id'] != $user_id) {
        set_message("Você não tem permissão para realizar esta ação.", "danger");
        redirect(BASE_URL . 'notifications.php');
        exit();
    }

    $album_id = $partnership['album_id'];
    $creator_id = $partnership['creator_id'];
    $album_name = $partnership['album_name'];

    if ($action === 'accept') {
        if (AlbumPartner::acceptPartnership($pdo, $partner_id)) {
            set_message("Você aceitou a parceria do álbum '" . htmlspecialchars($album_name) . "'!", "success");

            // Enviar notificação ao criador do álbum informando que a parceria foi aceita
            $stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt_user->execute([$user_id]);
            $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

            $message = "@" . $user_info['username'] . " aceitou a parceria do álbum '" . $album_name . "'.";
            Notification::createNotification(
                $pdo,
                $creator_id,
                $message,
                BASE_URL . "view_album.php?id=" . $album_id,
                $user_id,
                'album_partnership_accepted',
                $partner_id
            );
        } else {
            set_message("Erro ao aceitar a parceria.", "danger");
        }
    } elseif ($action === 'reject') {
        if (AlbumPartner::rejectPartnership($pdo, $partner_id)) {
            set_message("Você recusou a parceria do álbum '" . htmlspecialchars($album_name) . "'.", "success");

            // Enviar notificação ao criador do álbum informando que a parceria foi recusada
            $stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt_user->execute([$user_id]);
            $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);

            $message = "@" . $user_info['username'] . " recusou a parceria do álbum '" . $album_name . "'.";
            Notification::createNotification(
                $pdo,
                $creator_id,
                $message,
                BASE_URL . "view_album.php?id=" . $album_id,
                $user_id,
                'album_partnership_rejected',
                $partner_id
            );
        } else {
            set_message("Erro ao recusar a parceria.", "danger");
        }
    } else {
        set_message("Ação inválida.", "danger");
    }

    redirect(BASE_URL . 'notifications.php');
    exit();
} catch (Exception $e) {
    error_log("Erro em process_partnership.php: " . $e->getMessage());
    set_message("Erro ao processar a parceria: " . $e->getMessage(), "danger");
    redirect(BASE_URL . 'notifications.php');
    exit();
}
