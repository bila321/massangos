<?php

/**
 * public/ajax/verification_actions.php
 * 
 * Endpoint AJAX para ações de revisão de verificações.
 * Suporta aprovação, rejeição e marcação para revisão manual.
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';

header('Content-Type: application/json');

// Validar acesso admin
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$admin_id = get_current_user_id();

// Verificar se é admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$admin_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !in_array($user['role'], ['admin', 'superadmin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acesso negado']);
    exit();
}

// Processar ação
$action = sanitize_input($_POST['action'] ?? '');
$verification_id = (int)($_POST['verification_id'] ?? 0);
$user_id = (int)($_POST['user_id'] ?? 0);
$notes = sanitize_input($_POST['notes'] ?? '');
$risk_level = sanitize_input($_POST['risk_level'] ?? 'low');

// Validar risk_level
if (!in_array($risk_level, ['low', 'medium', 'high'])) {
    $risk_level = 'low';
}

if ($verification_id <= 0 || $user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Verificar que a verificação existe e está pendente
    $stmt = $pdo->prepare("SELECT id, status FROM user_verifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$verification_id, $user_id]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$verification) {
        throw new Exception("Verificação não encontrada");
    }

    if ($action === 'approve') {
        // Aprovar
        $stmt = $pdo->prepare("
            UPDATE user_verifications 
            SET status = 'approved', 
                admin_notes = ?, 
                reviewed_by = ?, 
                reviewed_at = NOW(),
                risk_level = ?
            WHERE id = ?
        ");
        $stmt->execute([$notes, $admin_id, $risk_level, $verification_id]);

        // Atualizar usuário
        $stmt_user = $pdo->prepare("
            UPDATE users 
            SET is_verified_creator = 1, verification_status = 'approved' 
            WHERE id = ?
        ");
        $stmt_user->execute([$user_id]);

        // Log
        $log_details = json_encode([
            'verification_id' => $verification_id,
            'user_id' => $user_id,
            'action' => 'approve',
            'risk_level' => $risk_level
        ]);
        $stmt_log = $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt_log->execute([$admin_id, 'VERIFICATION_APPROVED', $log_details, get_client_ip()]);

        $message = 'Verificação aprovada com sucesso';
    } elseif ($action === 'reject') {
        // Rejeitar
        $stmt = $pdo->prepare("
            UPDATE user_verifications 
            SET status = 'rejected', 
                admin_notes = ?, 
                reviewed_by = ?, 
                reviewed_at = NOW(),
                risk_level = ?
            WHERE id = ?
        ");
        $stmt->execute([$notes, $admin_id, $risk_level, $verification_id]);

        // Atualizar usuário
        $stmt_user = $pdo->prepare("
            UPDATE users 
            SET verification_status = 'rejected' 
            WHERE id = ?
        ");
        $stmt_user->execute([$user_id]);

        // Log
        $log_details = json_encode([
            'verification_id' => $verification_id,
            'user_id' => $user_id,
            'action' => 'reject',
            'risk_level' => $risk_level
        ]);
        $stmt_log = $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt_log->execute([$admin_id, 'VERIFICATION_REJECTED', $log_details, get_client_ip()]);

        $message = 'Verificação rejeitada';
    } elseif ($action === 'manual_review') {
        // Marcar para revisão manual
        $stmt = $pdo->prepare("
            UPDATE user_verifications 
            SET admin_notes = ?, 
                reviewed_by = ?, 
                reviewed_at = NOW(),
                risk_level = ?
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$notes, $admin_id, $risk_level, $verification_id]);

        // Log
        $log_details = json_encode([
            'verification_id' => $verification_id,
            'user_id' => $user_id,
            'action' => 'manual_review',
            'risk_level' => $risk_level
        ]);
        $stmt_log = $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt_log->execute([$admin_id, 'VERIFICATION_MANUAL_REVIEW', $log_details, get_client_ip()]);

        $message = 'Verificação marcada para revisão manual';
    } else {
        throw new Exception("Ação desconhecida: $action");
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $message,
        'verification_id' => $verification_id
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro: ' . $e->getMessage()
    ]);
}

function get_client_ip()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
