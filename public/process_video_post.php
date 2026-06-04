<?php
ob_start();
error_reporting(E_ERROR);
// public/process_video_post.php

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Services\VideoService;

SecurityManager::initSecurity();

if (!is_logged_in()) {
    set_message("VocÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Âª precisa estar logado para publicar vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­deos.", "danger");
    redirect(BASE_URL . 'login.php');
    exit();
}

$userId = get_current_user_id();
$videoService = new VideoService($pdo, $userId);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'index.php');
    exit();
}

$action = $_POST['action'] ?? '';
$redirectTo = $_POST['redirect_to'] ?? 'index.php';

switch ($action) {
    case 'delete_video':
        $videoId = (int)($_POST['video_id'] ?? 0);
        $result = $videoService->deleteVideo($videoId);
        break;

    default:
        // Criar vÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â­deo
        $result = $videoService->createVideo($_POST, $_FILES['video'] ?? []);
        break;
}

if (is_ajax_request()) {
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

function is_ajax_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

