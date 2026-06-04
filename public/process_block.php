<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\User;

if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado.']);
    exit;
}

$current_user_id = get_current_user_id();
$target_user_id = $_POST['user_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$target_user_id || !$action) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

$success = false;
$message = '';

if ($action === 'block') {
    if (User::blockUser($pdo, $current_user_id, $target_user_id)) {
        $success = true;
        $message = 'Usuário bloqueado com sucesso.';
    } else {
        $message = 'Erro ao bloquear usuário.';
    }
} elseif ($action === 'unblock') {
    if (User::unblockUser($pdo, $current_user_id, $target_user_id)) {
        $success = true;
        $message = 'Usuário desbloqueado com sucesso.';
    } else {
        $message = 'Erro ao desbloquear usuário.';
    }
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
} else {
    set_message($message, $success ? 'success' : 'danger');
    redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
}
