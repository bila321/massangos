<?php
// public/actions/post.php
// Endpoint AJAX para criação de publicações (texto, foto, álbum, vídeo).
// Chamado pelo create-post.js via XHR. Responde sempre JSON.

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../../app/bootstrap.php';

use Massango\Controllers\CreatePostController;

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

// Autenticação
if (!is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Não autorizado.']);
    exit;
}

$redirectTo = $_POST['redirect_to'] ?? 'index.php';

try {
    $ctrl   = new CreatePostController($pdo, (int)get_current_user_id());
    $result = $ctrl->handle($_POST, $_FILES);
} catch (Throwable $e) {
    error_log('[actions/post.php] ' . $e->getMessage());
    $result = ['success' => false, 'message' => 'Erro interno do servidor.'];
}

// Resposta AJAX
if (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Fallback sem JS (form normal)
set_message($result['message'], $result['success'] ? 'success' : 'danger');
redirect(BASE_URL . $redirectTo);
