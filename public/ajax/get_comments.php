<?php
/**
 * DEPRECATED - Consolidado em public/api/comments.php
 * Data: 20260606_175439
 * Todas as chamadas devem usar: /api/comments.php
 */

/**
 * public/ajax/get_comments.php
 * Refactored for Clean Code, Security (anti-SQLi/XSS), and Performance.
 * Returns JSON instead of raw HTML.
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Massango\Models\Comment;
use Massango\Models\User;
use Massango\Models\FeedItem;
use Massango\Models\Post;
use Massango\Models\Video;
use Massango\Models\Album;

header('Content-Type: application/json; charset=utf-8');

// Get and sanitize input
$feed_item_id = isset($_GET['feed_item_id']) ? (int)$_GET['feed_item_id'] : null;
$current_user_id = get_current_user_id();

if (!$feed_item_id) {
    echo json_encode(['success' => false, 'error' => 'ID do item não especificado.']);
    exit;
}

try {
    // FeedItem::getFeedItemById should use prepared statements internally
    $feed_item = FeedItem::getFeedItemById($pdo, $feed_item_id);
    if (!$feed_item) {
        echo json_encode(['success' => false, 'error' => 'Item não encontrado.']);
        exit;
    }

    $post_author_id = $feed_item['user_id'];
    $post_author = User::getUserById($pdo, $post_author_id);

    if (!$post_author) {
        echo json_encode(['success' => false, 'error' => 'Autor não encontrado.']);
        exit;
    }

    $is_post_owner = ($current_user_id && $post_author_id == $current_user_id);

    // Get content/description based on item type
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

    // Check if current user follows the author
    $is_following = false;
    if ($current_user_id && $current_user_id != $post_author_id) {
        $is_following = User::isFollowing($pdo, $current_user_id, $post_author_id);
    }

    // Prepare header data
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

    // Get comments
    $comments_raw = Comment::getCommentsForFeedItem($pdo, $feed_item_id, $current_user_id);

    // Transform comments into a clean data structure
    $comments = array_map(function ($comment) use ($current_user_id, $is_post_owner) {
        return formatComment($comment, $current_user_id, $is_post_owner);
    }, $comments_raw);

    echo json_encode([
        'success' => true,
        'header' => $header_data,
        'comments' => $comments,
        'current_user_id' => $current_user_id,
        'base_url' => BASE_URL,
        'upload_url' => UPLOAD_URL
    ]);
} catch (Exception $e) {
    error_log("Erro em get_comments.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar comentários.']);
}

/**
 * Formats a comment and its replies for JSON output
 */
function formatComment($comment, $current_user_id, $is_post_owner)
{
    $formatted = [
        'id' => $comment['id'],
        'user_id' => $comment['user_id'],
        'username' => $comment['username'],
        'profile_picture' => UPLOAD_URL . ($comment['profile_picture'] ?? 'profiles/default_profile.png'),
        'content' => $comment['content'],
        'likes' => (int)($comment['likes'] ?? 0),
        'user_vote' => $comment['user_vote'] ?? null,
        'formatted_created_at' => $comment['formatted_created_at'] ?? 'agora',
        'is_owner' => $current_user_id && $comment['user_id'] == $current_user_id,
        'can_delete' => ($current_user_id && ($comment['user_id'] == $current_user_id || $is_post_owner)),
        'replies' => []
    ];

    if (!empty($comment['replies'])) {
        foreach ($comment['replies'] as $reply) {
            $formatted['replies'][] = formatComment($reply, $current_user_id, $is_post_owner);
        }
    }

    return $formatted;
}
