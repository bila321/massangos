<?php
/**
 * public/api/comments.php — VERSÃO CONSOLIDADA v2
 * Unifica: api/comments.php + process_comment.php + ajax/get_comments.php (GET simples)
 * 
 * Suporta:
 * - GET ?feed_item_id=123 [&full=1]
 * - POST JSON ou form-data:
 *   action=add_comment|add_reply, feed_item_id, content/comment_content, parent_comment_id
 *   action=vote_comment, comment_id, vote_type
 *   action=delete_comment, comment_id, is_post_owner
 */

define('SECURE_ACCESS', true);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
SecurityManager::initSecurity();
require_once __DIR__ . '/../../vendor/autoload.php';

use Massango\Models\Comment;
use Massango\Services\CommentService;
use Massango\Core\Database;

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$pdo = Database::getInstance();
$current_user_id = (int) get_current_user_id();
$commentService = new CommentService($pdo, $current_user_id);

$method = $_SERVER['REQUEST_METHOD'];

// Aceita JSON OU form-data (para compatibilidade com process_comment.php antigo)
$input = $method === 'POST' 
    ? (json_decode(file_get_contents('php://input'), true) ?? $_POST)
    : $_GET;

$feed_item_id = (int)($input['feed_item_id'] ?? 0);
$action = $input['action'] ?? ($method === 'POST' ? 'add_comment' : 'get');

// ── GET ─────────────────────────────────────────────────────
if ($method === 'GET' && $feed_item_id) {
    $comments = Comment::getCommentsForFeedItem($pdo, $feed_item_id, $current_user_id);
    $total = Comment::getCommentCountForFeedItem($pdo, $feed_item_id);
    
    // Se ?full=1, devolve formato do ajax/get_comments.php (compatibilidade)
    if (!empty($input['full'])) {
        // Reutiliza lógica do ajax/get_comments.php simplificada
        $commentsFormatted = array_map(function($c) use ($current_user_id) {
            return [
                'id' => $c['id'],
                'user_id' => $c['user_id'],
                'username' => $c['username'],
                'content' => $c['content'],
                'likes' => (int)($c['likes_count'] ?? 0),
                'dislikes' => (int)($c['dislikes_count'] ?? 0),
                'user_vote' => $c['user_vote'] ?? null,
                'created_at' => $c['created_at'],
                'replies' => $c['replies'] ?? []
            ];
        }, $comments);
        
        echo json_encode([
            'success' => true,
            'comments' => $commentsFormatted,
            'total' => $total
        ]);
        exit;
    }
    
    echo json_encode(['success' => true, 'comments' => $comments, 'total' => $total]);
    exit;
}

// ── POST ────────────────────────────────────────────────────
if ($method === 'POST') {
    
    // Normaliza nomes de campos (process_comment usa comment_content)
    $content = trim($input['content'] ?? $input['comment_content'] ?? '');
    $parent_id = (int)($input['parent_comment_id'] ?? 0) ?: null;
    $comment_id = (int)($input['comment_id'] ?? 0);
    $vote_type = $input['vote_type'] ?? '';
    
    switch ($action) {
        case 'add_comment':
        case 'add_reply':
            if (!$feed_item_id || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
                exit;
            }
            
            $result = $commentService->addComment($feed_item_id, $content, $parent_id);
            
            // Garante compatibilidade com view_album.js (espera comment_html)
            if (!isset($result['comment_html']) && !empty($result['comment_id'])) {
                $newComment = Comment::getCommentById($pdo, (int)$result['comment_id']);
                if ($newComment && function_exists('render_comment_html')) {
                    $newComment['replies'] = [];
                    $newComment['likes_count'] = 0;
                    $newComment['dislikes_count'] = 0;
                    $newComment['user_vote'] = null;
                    $result['comment_html'] = render_comment_html($newComment, $current_user_id, false, $pdo, 0);
                }
            }
            
            $result['total'] = Comment::getCommentCountForFeedItem($pdo, $feed_item_id);
            echo json_encode($result);
            exit;
            
        case 'vote_comment':
            if (!$comment_id || !in_array($vote_type, ['like', 'dislike'], true)) {
                echo json_encode(['success' => false, 'message' => 'Dados de voto inválidos']);
                exit;
            }
            
            $result = $commentService->voteComment($comment_id, $vote_type);
            echo json_encode($result);
            exit;
            
        case 'delete_comment':
            $is_post_owner = filter_var($input['is_post_owner'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (!$comment_id) {
                echo json_encode(['success' => false, 'message' => 'ID inválido']);
                exit;
            }
            
            $result = $commentService->deleteComment($comment_id, $is_post_owner);
            echo json_encode($result);
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
            exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Método não suportado']);
