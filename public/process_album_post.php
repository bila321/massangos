<?php
ob_start();
error_reporting(E_ERROR);
// public/process_album_post.php

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Services\AlbumService;

SecurityManager::initSecurity();

if (!is_logged_in()) {
    set_message("VocÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âª precisa estar logado para criar ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lbuns.", "danger");
    redirect(BASE_URL . 'login.php');
    exit();
}

$userId = get_current_user_id();
$albumService = new AlbumService($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'index.php');
    exit();
}

$action = $_POST['action'] ?? '';
$redirectTo = $_POST['redirect_to'] ?? 'index.php';

switch ($action) {
    case 'delete_album':
        $albumId = (int)($_POST['album_id'] ?? 0);
        $result = $albumService->deleteAlbum($albumId);
        break;

    default:
        // Criar ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¡lbum
        $result = $albumService->createAlbum($_POST, $_FILES['images'] ?? []);
        break;
}

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
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
