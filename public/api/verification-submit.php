<?php
/**
 * public/api/verification-submit.php
 *
 * Secure REST API endpoint for identity verification submissions
 * Handles file uploads, validation, and FastAPI integration
 *
 * FIXES APLICADOS:
 *  - BUG CRÍTICO: trigger_ai_identity_verification() retorna array, não bool.
 *    Antes: !$ai_triggered avaliava sempre false (array não-vazio é true em PHP).
 *    Agora: verificar $ai_result['success'] === false correctamente.
 *  - ai_status inicial na INSERT é 'pending' (não depende do trigger).
 *  - Quando trigger falha → ai_status='queued' (distingue de pending normal).
 *  - Log detalhado inclui status e erro retornados pelo trigger.
 *  - Pasta UPLOAD_BASE_DIR criada automaticamente com verificação de permissões.
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../services/ai/identity/php_trigger.php';

SecurityManager::initSecurity();

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

$user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// ============================================
// CONSTANTES
// ============================================

const MAX_UPLOAD_SIZE     = 10 * 1024 * 1024; // 10MB
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const ALLOWED_VIDEO_TYPES = ['video/webm', 'video/mp4', 'video/ogg'];
const UPLOAD_BASE_DIR     = dirname(dirname(dirname(__DIR__))) . '/storage/uploads/verifications';

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

function sanitize_verification_input(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validate_phone_number(string $phone): bool {
    $phone = preg_replace('/[^\d+]/', '', $phone);
    return (bool) preg_match('/^(\+258|0)[0-9]{8,9}$/', $phone);
}

function validate_birth_date(string $date): bool {
    $birthDate = DateTime::createFromFormat('Y-m-d', $date);
    if (!$birthDate) return false;
    $age = (new DateTime())->diff($birthDate)->y;
    return $age >= 18 && $age <= 120;
}

/**
 * Valida ficheiro base64 (imagem ou vídeo).
 */
function validate_base64_file(string $base64_data, array $allowed_types, int $max_size): array {
    if (empty($base64_data)) {
        return ['valid' => false, 'error' => 'Dados base64 vazios'];
    }

    if (strpos($base64_data, 'data:') === 0) {
        $last_comma_pos = strrpos($base64_data, ',');
        if ($last_comma_pos === false) {
            return ['valid' => false, 'error' => 'Formato Data URL inválido'];
        }
        $base64_payload = substr($base64_data, $last_comma_pos + 1);
        $data = base64_decode($base64_payload, true);
    } else {
        $data = base64_decode($base64_data, true);
    }

    if ($data === false || empty($data)) {
        return ['valid' => false, 'error' => 'Dados base64 inválidos ou falha na decodificação'];
    }

    if (strlen($data) > $max_size) {
        return ['valid' => false, 'error' => 'Arquivo muito grande. Máximo: ' . ($max_size / 1024 / 1024) . 'MB'];
    }

    $finfo     = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_buffer($finfo, $data);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        return ['valid' => false, 'error' => 'Tipo de arquivo não permitido. Detectado: ' . $mime_type];
    }

    return ['valid' => true, 'mime_type' => $mime_type, 'data' => $data];
}

/**
 * Guarda ficheiro base64 em disco de forma segura.
 */
function save_base64_file(string $base64_data, string $filename, string $upload_dir): array {
    $validation = validate_base64_file(
        $base64_data,
        array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES),
        MAX_UPLOAD_SIZE
    );

    if (!$validation['valid']) {
        return ['success' => false, 'error' => $validation['error']];
    }

    // Sanitizar filename — prevenir path traversal
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename));

    // Criar diretório com verificação explícita de permissões
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $error_msg = 'Não foi possível criar diretório de upload: ' . $upload_dir;
            error_log('[verification-submit] ' . $error_msg);
            return ['success' => false, 'error' => 'Erro de configuração do servidor. Contacte o administrador.'];
        }
    }

    if (!is_writable($upload_dir)) {
        error_log('[verification-submit] Diretório não gravável: ' . $upload_dir);
        return ['success' => false, 'error' => 'Erro de permissões no servidor. Contacte o administrador.'];
    }

    $ext             = pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin';
    $unique_filename = uniqid('', true) . '_' . time() . '.' . $ext;
    $filepath        = $upload_dir . '/' . $unique_filename;

    // Protecção extra contra path traversal
    $real_dir  = realpath($upload_dir);
    $check_dir = realpath(dirname($filepath));
    if ($real_dir === false || $check_dir === false || strpos($check_dir, $real_dir) !== 0) {
        return ['success' => false, 'error' => 'Caminho de arquivo inválido'];
    }

    if (file_put_contents($filepath, $validation['data']) === false) {
        error_log('[verification-submit] Falha ao escrever ficheiro: ' . $filepath);
        return ['success' => false, 'error' => 'Não foi possível salvar o arquivo'];
    }

    return [
        'success'       => true,
        'filename'      => $unique_filename,
        'filepath'      => $filepath,
        'relative_path' => 'verifications/' . basename(dirname($filepath)) . '/' . $unique_filename,
    ];
}

// ============================================
// PROCESSAMENTO PRINCIPAL
// ============================================

try {
    // Sanitizar inputs
    $full_name     = sanitize_verification_input($_POST['full_name']     ?? '');
    $nickname      = sanitize_verification_input($_POST['nickname']      ?? '');
    $birth_date    = sanitize_verification_input($_POST['birth_date']    ?? '');
    $province      = sanitize_verification_input($_POST['province']      ?? '');
    $contact_phone = sanitize_verification_input($_POST['contact_phone'] ?? '');

    // Validar campos obrigatórios
    if (empty($full_name) || empty($nickname) || empty($birth_date) || empty($province) || empty($contact_phone)) {
        throw new Exception('Todos os campos são obrigatórios');
    }

    if (strlen($full_name) < 5 || strlen($full_name) > 100) {
        throw new Exception('Nome completo deve ter entre 5 e 100 caracteres');
    }

    if (!validate_birth_date($birth_date)) {
        throw new Exception('Data de nascimento inválida. Deve ter pelo menos 18 anos');
    }

    if (!validate_phone_number($contact_phone)) {
        throw new Exception('Número de telefone inválido');
    }

    $valid_provinces = ['Maputo','Gaza','Inhambane','Sofala','Manica','Tete','Zambézia','Nampula','Niassa','Cabo Delgado'];
    if (!in_array($province, $valid_provinces)) {
        throw new Exception('Província inválida');
    }

    // Obter ficheiros base64
    $id_front_base64 = $_POST['id_front'] ?? '';
    $id_back_base64  = $_POST['id_back']  ?? '';
    $video_base64    = $_POST['video']    ?? '';

    if (empty($id_front_base64) || empty($id_back_base64) || empty($video_base64)) {
        throw new Exception('Todos os arquivos são obrigatórios');
    }

    // Pasta específica do utilizador dentro de uploads/verifications/
    $user_upload_dir = UPLOAD_BASE_DIR . '/' . $user_id;

    // Guardar ficheiros
    $front_result = save_base64_file($id_front_base64, 'id_front.jpg', $user_upload_dir);
    if (!$front_result['success']) {
        throw new Exception('Erro ao salvar frente do documento: ' . $front_result['error']);
    }

    $back_result = save_base64_file($id_back_base64, 'id_back.jpg', $user_upload_dir);
    if (!$back_result['success']) {
        @unlink($front_result['filepath']);
        throw new Exception('Erro ao salvar verso do documento: ' . $back_result['error']);
    }

    $video_result = save_base64_file($video_base64, 'verification_video.webm', $user_upload_dir);
    if (!$video_result['success']) {
        @unlink($front_result['filepath']);
        @unlink($back_result['filepath']);
        throw new Exception('Erro ao salvar vídeo: ' . $video_result['error']);
    }

    // Inserir registo na BD com ai_status='pending' explícito
    $stmt = $pdo->prepare("
        INSERT INTO user_verifications (
            user_id, full_name, nickname, birth_date, province, contact_phone,
            id_front_path, id_back_path, video_path, status, ai_status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())
    ");

    $success = $stmt->execute([
        $user_id,
        $full_name,
        $nickname,
        $birth_date,
        $province,
        $contact_phone,
        $front_result['relative_path'],
        $back_result['relative_path'],
        $video_result['relative_path'],
    ]);

    if (!$success) {
        throw new Exception('Erro ao criar registro de verificação na base de dados');
    }

    $verification_id = (int) $pdo->lastInsertId();

    // Actualizar status do utilizador
    $pdo->prepare("UPDATE users SET verification_status = 'pending' WHERE id = ?")
        ->execute([$user_id]);

    // ─────────────────────────────────────────────────────────────────────
    // DISPARAR VERIFICAÇÃO IA (modo assíncrono)
    //
    // FIX CRÍTICO: trigger_ai_identity_verification() retorna um ARRAY,
    // não um bool. Verificar ['success'] explicitamente.
    // Antes: !$ai_triggered → sempre false (array não-vazio é truthy em PHP).
    // ─────────────────────────────────────────────────────────────────────
    $ai_result    = trigger_ai_identity_verification(
        $user_id,
        $verification_id,
        $front_result['filepath'],
        $back_result['filepath'],
        $video_result['filepath'],
        true // async
    );
    $ai_triggered = $ai_result['success'] === true; // FIX: bool explícito

    if (!$ai_triggered) {
        // Log detalhado com status e erro retornados pelo trigger
        error_log(sprintf(
            '[verification-submit] AVISO: Trigger IA falhou | user=%d | ver=%d | status=%s | error=%s | '
            . 'Verifique se o FastAPI está em http://127.0.0.1:8000 (run: uvicorn main:app --reload)',
            $user_id,
            $verification_id,
            $ai_result['status'] ?? 'unknown',
            $ai_result['error']  ?? 'unknown'
        ));

        // Marcar 'queued' — diferente de 'pending': "tentou mas falhou, precisa retry"
        $pdo->prepare("UPDATE user_verifications SET ai_status = 'queued' WHERE id = ?")
            ->execute([$verification_id]);
    }

    http_response_code(200);
    echo json_encode([
        'success'         => true,
        'message'         => 'Verificação enviada com sucesso',
        'verification_id' => $verification_id,
        'status'          => 'pending',
        'ai_triggered'    => $ai_triggered,
        // Incluir info de diagnóstico apenas em dev (remover em produção)
        'ai_status'       => $ai_result['status'] ?? null,
        'ai_error'        => $ai_triggered ? null : ($ai_result['error'] ?? null),
    ]);

} catch (Exception $e) {
    error_log('[verification-submit] Erro: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
    exit;
}
