<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../vendor/autoload.php';
SecurityManager::initSecurity();

use Massango\Controllers\ViewTrackingController;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false]);
    exit();
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['HTTP_X_REAL_IP']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '0.0.0.0';

try {
    $ctrl   = new ViewTrackingController($pdo);
    $result = $ctrl->track($_POST, $_SESSION, $ip);
    if ($result['success'] && !($result['already_counted'] ?? false)) {
        $key = 'last_view_' . ($_POST['item_type'] ?? '') . '_' . (int)($_POST['item_id'] ?? 0);
        $_SESSION[$key] = time();
    }
    echo json_encode($result);
} catch (Exception $e) {
    error_log('[ViewTrackingController] ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro interno.']);
}
exit();
