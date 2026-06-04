<?php
// public/process_verification.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/face_api_helper.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\User;

if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $nickname = sanitize_input($_POST['nickname'] ?? '');
    $birth_date = sanitize_input($_POST['birth_date'] ?? '');
    $province = sanitize_input($_POST['province'] ?? '');
    $contact_phone = sanitize_input($_POST['contact_phone'] ?? '');

    // Validação básica
    if (empty($full_name) || empty($nickname) || empty($birth_date) || empty($province) || empty($contact_phone)) {
        echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios']);
        exit();
    }

    // Processar mídias (Base64 da câmera)
    $id_front = $_POST['id_front'] ?? '';
    $id_back = $_POST['id_back'] ?? '';
    $video = $_POST['video'] ?? '';

    if (empty($id_front) || empty($id_back) || empty($video)) {
        echo json_encode(['success' => false, 'message' => 'Todas as capturas de mídia são obrigatórias']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Salvar arquivos
        $verification_dir = UPLOAD_DIR . 'verifications/' . $user_id . '/';
        if (!is_dir($verification_dir)) {
            mkdir($verification_dir, 0775, true);
        }

        $id_front_path = save_base64_image($id_front, $verification_dir . 'id_front_' . uniqid() . '.jpg');
        $id_back_path = save_base64_image($id_back, $verification_dir . 'id_back_' . uniqid() . '.jpg');
        $video_path = save_base64_video($video, $verification_dir . 'video_' . uniqid() . '.webm');

        if (!$id_front_path || !$id_back_path || !$video_path) {
            throw new Exception("Erro ao salvar arquivos de mídia");
        }

        // Relativo para o banco
        $rel_front = str_replace(UPLOAD_DIR, '', $id_front_path);
        $rel_back = str_replace(UPLOAD_DIR, '', $id_back_path);
        $rel_video = str_replace(UPLOAD_DIR, '', $video_path);

        // --- INTEGRAÇÃO COM API DE VERIFICAÇÃO FACIAL ---
        $ai_result = call_face_verification_api($video_path, $id_front_path);
        
        $ai_status = 'ai_error';
        $ai_similarity = 0.0;
        $ai_notes = $ai_result['error'] ?? 'Erro desconhecido';
        $final_status = 'pending'; // Padrão é pendente para segurança

        if ($ai_result['success']) {
            $ai_similarity = $ai_result['score'] ?? 0.0;
            $ai_status = ($ai_result['status'] === 'approved') ? 'ai_approved' : 
                         (($ai_result['status'] === 'manual_review') ? 'manual_review' : 
                         (($ai_result['status'] === 'queued') ? 'queued' : 'ai_rejected'));
            $ai_notes = "Score: " . ($ai_similarity * 100) . "% | Status IA: " . $ai_result['status'];
            
            // Se for aprovado pela IA, podemos opcionalmente já aprovar no sistema
            if ($ai_result['status'] === 'approved') {
                $final_status = 'approved';
            } elseif ($ai_result['status'] === 'rejected') {
                $final_status = 'rejected';
            }
        }
        // ------------------------------------------------

        // Inserir na tabela user_verifications com dados da IA
        $stmt = $pdo->prepare("
            INSERT INTO user_verifications 
            (user_id, full_name, nickname, birth_date, province, id_front_path, id_back_path, video_path, contact_phone, status, ai_status, ai_similarity, ai_notes, ai_result_json) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, $full_name, $nickname, $birth_date, $province, 
            $rel_front, $rel_back, $rel_video, $contact_phone, 
            $final_status, $ai_status, $ai_similarity, $ai_notes, json_encode($ai_result)
        ]);

        // Atualizar status do usuário
        $stmt_user = $pdo->prepare("UPDATE users SET verification_status = ? WHERE id = ?");
        $stmt_user->execute([$final_status, $user_id]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Pedido de verificação enviado com sucesso!']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit();
}

function save_base64_image($base64_string, $output_file)
{
    $data = explode(',', $base64_string);
    if (count($data) < 2) return false;
    $content = base64_decode($data[1]);
    if (file_put_contents($output_file, $content)) {
        return $output_file;
    }
    return false;
}

function save_base64_video($base64_string, $output_file)
{
    $data = explode(',', $base64_string);
    if (count($data) < 2) return false;
    $content = base64_decode($data[1]);
    if (file_put_contents($output_file, $content)) {
        return $output_file;
    }
    return false;
}
