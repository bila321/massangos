<?php
// public/create-post.php — entry point, só renderiza o formulário (GET)
// O POST vai para public/actions/post.php (chamado pelo create-post.js via AJAX)

define('SECURE_ACCESS', true);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Só aceita GET — POST vai directo para actions/post.php via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(405);
    exit;
}

(new \Massango\Controllers\CreatePostController($pdo, (int)get_current_user_id()))->show();
