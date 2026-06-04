<?php
ob_start();
error_reporting(E_ERROR);
// public/process_post.php

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Services\PostService;

SecurityManager::initSecurity();

if (!is_logged_in()) {
    set_message("VocÃƒÂª precisa estar logado para publicar posts.", "danger");
    redirect(BASE_URL . 'login.php');
    exit();
}

$userId = get_current_user_id();
$postService = new PostService($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'index.php');
    exit();
}

$action = $_POST['action'] ?? '';
$redirectTo = $_POST['redirect_to'] ?? 'index.php';

try {
    error_log("DEBUG process_post: action=$action, post_type=" . ($_POST['post_type'] ?? 'N/A') . ", files=" . json_encode(array_keys($_FILES)));
    if ($action === 'delete_post') {
        $postId = (int)($_POST['post_id'] ?? 0);
        // Assumindo que o PostService tem deletePost ou implementar aqui
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
        $success = $stmt->execute([$postId, $userId]);
        $result = $success ? ['success' => true, 'message' => 'Post apagado.'] : ['success' => false, 'message' => 'Erro ao apagar.'];
    } else {
        // Criar post (texto ou foto)
        // O formulÃƒÂ¡rio de foto usa 'image' no input file, mas o PostService usa 'post_image'
        if (isset($_FILES['image']) && !isset($_FILES['post_image'])) {
            $_FILES['post_image'] = $_FILES['image'];
        }
        $result = $postService->createPost($_POST);
    }
} catch (Exception $e) {
    error_log("Erro em process_post.php: " . $e->getMessage());
    $result = ['success' => false, 'message' => 'Erro interno: ' . $e->getMessage()];
}

if (is_ajax()) {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}

if ($result['success']) {
    set_message($result['message'], "success");
} else {
    set_message($result['message'], "danger");
}

redirect(BASE_URL . $redirectTo);

function is_ajax()
{
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

