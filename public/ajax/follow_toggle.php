<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Massango\Models\User;

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Você precisa estar logado.']);
    exit;
}

$current_user_id = get_current_user_id();
$target_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if ($target_user_id <= 0 || $target_user_id === $current_user_id) {
    echo json_encode(['success' => false, 'error' => 'Usuário inválido.']);
    exit;
}

try {
    $is_following = User::isFollowing($pdo, $current_user_id, $target_user_id);

    if ($is_following) {
        $result = User::unfollowUser($pdo, $current_user_id, $target_user_id);
        $action = 'unfollowed';
        $label = 'Seguir';
    } else {
        // Verifica se o perfil é privado
        $target_user = User::getUserById($pdo, $target_user_id);
        if ($target_user && $target_user['profile_privacy'] === 'followers') {
            $result = User::sendFollowRequest($pdo, $current_user_id, $target_user_id);
            $action = 'requested';
            $label = 'Pendente';
        } else {
            $result = User::followUser($pdo, $current_user_id, $target_user_id);
            $action = 'followed';
            $label = 'Seguindo';
        }
    }

    if ($result) {
        echo json_encode([
            'success' => true,
            'action' => $action,
            'label' => $label,
            'user_id' => $target_user_id
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erro ao processar a ação.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro no servidor: ' . $e->getMessage()]);
}
