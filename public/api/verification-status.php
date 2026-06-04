<?php
/**
 * public/api/verification-status.php
 *
 * API endpoint for retrieving verification status
 * Supports polling for real-time status updates
 *
 * FIXES:
 *  - ai_status NULL ou 'queued' são normalizados para 'pending' na resposta,
 *    para o polling JS não parar prematuramente nem tratar NULL como erro.
 *  - Adicionado campo `ai_triggered` para o frontend saber se a IA foi disparada.
 *  - Adicionado `retry_available` para indicar se o utilizador pode re-tentar.
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';

SecurityManager::initSecurity();

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $verification_id = isset($_GET['verification_id']) ? intval($_GET['verification_id']) : 0;

    if ($verification_id <= 0) {
        throw new Exception('ID de verificação inválido');
    }

    $stmt = $pdo->prepare("
        SELECT
            id, user_id, status, ai_status, ai_similarity, ai_liveness, ai_notes,
            admin_notes, risk_level, reviewed_by, reviewed_at, created_at, updated_at
        FROM user_verifications
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$verification_id, $user_id]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$verification) {
        throw new Exception('Verificação não encontrada');
    }

    // FIX: Normalizar ai_status — NULL e 'queued' são tratados como 'pending'
    // para o polling JS continuar a aguardar correctamente.
    $raw_ai_status = $verification['ai_status'];
    $ai_status_normalized = (empty($raw_ai_status) || $raw_ai_status === 'queued')
        ? 'pending'
        : $raw_ai_status;

    // FIX: Indicar se a IA foi disparada com sucesso
    $ai_triggered = ($raw_ai_status !== 'queued' && !empty($raw_ai_status));

    // FIX: Indicar se retry está disponível (IA deu erro ou não foi disparada)
    $retry_available = in_array($raw_ai_status, ['ai_error', 'queued', null, '']);

    $response = [
        'success'         => true,
        'verification_id' => (int)$verification['id'],
        'status'          => $verification['status'],
        'ai_status'       => $ai_status_normalized,
        'ai_status_raw'   => $raw_ai_status,        // para debug
        'ai_triggered'    => $ai_triggered,
        'retry_available' => $retry_available,
        'ai_similarity'   => floatval($verification['ai_similarity'] ?? 0),
        'ai_liveness'     => (bool)$verification['ai_liveness'],
        'ai_notes'        => $verification['ai_notes'],
        'admin_notes'     => $verification['admin_notes'],
        'risk_level'      => $verification['risk_level'],
        'reviewed_by'     => $verification['reviewed_by'],
        'reviewed_at'     => $verification['reviewed_at'],
        'created_at'      => $verification['created_at'],
        'updated_at'      => $verification['updated_at']
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
