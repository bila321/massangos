<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Massango\Controllers\BlockController;

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Nao autorizado.']);
    exit();
}

try {
    $ctrl   = new BlockController($pdo, get_current_user_id());
    $result = $ctrl->handle($_POST);
} catch (Exception $e) {
    error_log('[BlockController] ' . $e->getMessage());
    $result = ['success' => false, 'message' => 'Erro interno.'];
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    echo json_encode($result);
    exit();
}
set_message($result['message'], $result['success'] ? 'success' : 'danger');
redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
