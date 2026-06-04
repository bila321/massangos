<?php
/**
 * public/api/comments.php
 * Comentários gerais do álbum via feed_item_id
 */
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Massango\Models\Comment;

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Nao autenticado']);
    exit;
}

$current_user_id = (int) get_current_user_id();
$method = $_SERVER['REQUEST_METHOD'];
$data   = $method === 'POST'
    ? (json_decode(file_get_contents('php://input'), true) ?? [])
    : $_GET;

$feed_item_id = (int)($data['feed_item_id'] ?? 0);

if (!$feed_item_id) {
    echo json_encode(['success' => false, 'error' => 'feed_item_id invalido']);
    exit;
}

// ── GET — carregar comentários ────────────────────────────────────────────────
if ($method === 'GET') {
    $comments = Comment::getCommentsForFeedItem($pdo, $feed_item_id, $current_user_id);
    $total    = Comment::getCommentCountForFeedItem($pdo, $feed_item_id);
    echo json_encode(['success' => true, 'comments' => $comments, 'total' => $total]);
    exit;
}

// ── POST — adicionar comentário geral ─────────────────────────────────────────
if ($method === 'POST') {
    $content   = trim($data['content'] ?? '');
    $parent_id = (int)($data['parent_comment_id'] ?? 0) ?: null;

    if (empty($content)) {
        echo json_encode(['success' => false, 'error' => 'Comentario vazio']);
        exit;
    }
    if (mb_strlen($content) > 2000) {
        echo json_encode(['success' => false, 'error' => 'Demasiado longo']);
        exit;
    }

    $safe = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

    $stmt = $pdo->prepare(
        "INSERT INTO comments (feed_item_id, user_id, parent_comment_id, content)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([$feed_item_id, $current_user_id, $parent_id, $safe]);
    $comment_id = (int)$pdo->lastInsertId();

    $total = Comment::getCommentCountForFeedItem($pdo, $feed_item_id);

    echo json_encode([
        'success'    => true,
        'comment_id' => $comment_id,
        'total'      => $total,
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Metodo nao suportado']);
