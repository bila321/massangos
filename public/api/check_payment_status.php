<?php
// public/api/check_payment_status.php
header('Content-Type: application/json');
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!is_logged_in()) {
    echo json_encode(['error' => 'Não autorizado']);
    exit();
}

$sale_id = (int)($_GET['sale_id'] ?? 0);
$user_id = get_current_user_id();

$stmt = $pdo->prepare("SELECT status FROM sales WHERE id = ? AND buyer_id = ?");
$stmt->execute([$sale_id, $user_id]);
$status = $stmt->fetchColumn();

echo json_encode(['status' => $status]);
