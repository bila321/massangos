<?php

/**
 * api/comments.php - ENDPOINT ÚNICO DE COMENTÁRIOS (CONSOLIDADO)
 *
 * Data: 2026-06-06
 * Substitui:
 *   - public/ajax/get_comments.php (eliminado)
 *   - public/process_comment.php (agora é wrapper de 3 linhas)
 *
 * Funcionalidades:
 *   GET  ?feed_item_id=XX [&full=1]     → lista comentários + header
 *   POST action=add_comment | add_reply | vote_comment | delete_comment
 */

define('SECURE_ACCESS', true);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';

SecurityManager::initSecurity();
require_once __DIR__ . '/../../vendor/autoload.php';

use Massango\Models\Comment;
use Massango\Models\User;
use Massango\Models\FeedItem;
use Massango\Models\Post;
use Massango\Models\Video;
use Massango\Models\Album;
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
$input = $method === 'POST'
    ? (json_decode(file_get_contents('php://input'), true) ?? $_POST)
    : $_GET;

$feed_item_id = (int)($input['feed_item_id'] ?? 0);
$action = $input['action'] ?? ($method === 'POST' ? 'add_comment' : 'get');

// ============================================================
// GET - Listar comentários (com header completo)
// ============================================================
if ($method === 'GET' && $feed_item_id) {

    // 1. Dados do header (do antigo get_comments.php)
    try {
        $feed_item = FeedItem::getFeedItemById($pdo, $feed_item_id);
        if (!$feed_item) {
            echo json_encode(['success' => false, 'error' => 'Item não encontrado.']);
            exit;
        }

        $post_author_id = $feed_item['user_id'];
        $post_author = User::getUserById($pdo, $post_author_id);
        $is_post_owner = ($current_user_id && $post_author_id == $current_user_id);

        // Conteúdo do post
        $post_content = '';
        $is_repost = false;
        $original_author_data = null;

        if ($feed_item['item_type'] === 'post') {
            $post = Post::getPostById($pdo, $feed_item['item_id']);
            if ($post) {
                $post_content = $post['content'] ?? '';
                if (!empty($post['is_repost']) && !empty($post['original_post_id'])) {
                    $is_repost = true;
                    $original_post = Post::getPostById($pdo, $post['original_post_id']);
                    if ($original_post) {
                        $original_author = User::getUserById($pdo, $original_post['user_id']);
                        if ($original_author) {
                            $original_author_data = [
                                'id' => $original_author['id'],
                                'username' => $original_author['username'],
                                'profile_picture' => UPLOAD_URL . ($original_author['profile_picture'] ?? 'profiles/default_profile.png')
                            ];
                        }
                    }
                }
            }
        } elseif ($feed_item['item_type'] === 'video') {
            $video = Video::getVideoById($pdo, $feed_item['item_id']);
            $post_content = $video['caption'] ?? '';
        } elseif ($feed_item['item_type'] === 'album') {
            $album = Album::getAlbumById($pdo, $feed_item['item_id']);
            $post_content = $album['caption'] ?? '';
        }

        $is_following = false;
        if ($current_user_id && $current_user_id != $post_author_id) {
            $is_following = User::isFollowing($pdo, $current_user_id, $post_author_id);
        }

        $header_data = [
            'post_author' => [
                'id' => $post_author['id'],
                'username' => $post_author['username'],
                'profile_picture' => UPLOAD_URL . ($post_author['profile_picture'] ?? 'profiles/default_profile.png'),
                'is_following' => $is_following
            ],
            'is_repost' => $is_repost,
            'original_author' => $original_author_data,
            'post_content' => $post_content,
            'is_post_owner' => $is_post_owner
        ];
    } catch (Exception $e) {
        $header_data = null;
    }

    // 2. Comentários
    $comments_raw = Comment::getCommentsForFeedItem($pdo, $feed_item_id, $current_user_id);
    $total = Comment::getCommentCountForFeedItem($pdo, $feed_item_id);

    // Formatação compatível com o antigo ajax/get_comments.php
    $comments = array_map(function ($comment) use ($current_user_id, $is_post_owner) {
        return [
            'id' => $comment['id'],
            'user_id' => $comment['user_id'],
            'username' => $comment['username'],
            'profile_picture' => UPLOAD_URL . ($comment['profile_picture'] ?? 'profiles/default_profile.png'),
            'content' => $comment['content'],
            'likes' => (int)($comment['likes_count'] ?? 0),
            'dislikes' => (int)($comment['dislikes_count'] ?? 0),
            'user_vote' => $comment['user_vote'] ?? null,
            'created_at' => $comment['created_at'],
            'formatted_created_at' => $comment['formatted_created_at'] ?? 'agora',
            'is_owner' => $current_user_id && $comment['user_id'] == $current_user_id,
            'can_delete' => ($current_user_id && ($comment['user_id'] == $current_user_id || $is_post_owner)),
            'replies' => $comment['replies'] ?? []
        ];
    }, $comments_raw);

    // Compatibilidade com ?full=1 (formato antigo)
    if (!empty($input['full'])) {
        echo json_encode([
            'success' => true,
            'header' => $header_data,
            'comments' => $comments,
            'total' => $total,
            'current_user_id' => $current_user_id,
            'base_url' => BASE_URL,
            'upload_url' => UPLOAD_URL
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'header' => $header_data,
        'comments' => $comments,
        'total' => $total
    ]);
    exit;
}

// ============================================================
// POST - Ações
// ============================================================
if ($method === 'POST') {

    $content     = trim($input['content'] ?? $input['comment_content'] ?? '');
    $parent_id   = (int)($input['parent_comment_id'] ?? 0) ?: null;
    $comment_id  = (int)($input['comment_id'] ?? 0);
    $vote_type   = $input['vote_type'] ?? '';
    $is_post_owner = filter_var($input['is_post_owner'] ?? false, FILTER_VALIDATE_BOOLEAN);

    switch ($action) {
        case 'add_comment':
        case 'add_reply':
            if (!$feed_item_id || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
                exit;
            }

            $result = $commentService->addComment($feed_item_id, $content, $parent_id);

            // Suporte a optimistic UI (comment_html)
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
