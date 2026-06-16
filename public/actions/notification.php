<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../vendor/autoload.php';
SecurityManager::initSecurity();

use Massango\Controllers\NotificationController;

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Nao autorizado.']);
    exit();
}

try {
    $ctrl   = new NotificationController($pdo, get_current_user_id());
    $result = $ctrl->handle($_POST);
    $result['unread_count'] = $ctrl->unreadCount();
    echo json_encode($result);
} catch (Exception $e) {
    error_log('[NotificationController] ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno.']);
}
exit();
