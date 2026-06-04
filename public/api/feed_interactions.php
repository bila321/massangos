<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

require_once __DIR__ . '/../../vendor/autoload.php';

use Massango\Models\Comment;
use Massango\Models\Like;
use Massango\Models\FeedItem;

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$feed_item_id = filter_input(INPUT_GET, 'feed_item_id', FILTER_VALIDATE_INT);
$current_user_id = get_current_user_id();

if ($action === 'get_data' && $feed_item_id) {
    $like_info = Like::getFeedItemLikesDislikesCount($pdo, $feed_item_id);
    $user_vote = $current_user_id ? Like::getUserFeedItemVote($pdo, $feed_item_id, $current_user_id) : null;
    $comments = Comment::getCommentsForFeedItem($pdo, $feed_item_id, $current_user_id);

    // Obter o dono do post para permissões de exclusão
    $stmt = $pdo->prepare("SELECT user_id FROM feed_items WHERE id = ?");
    $stmt->execute([$feed_item_id]);
    $item = $stmt->fetch();
    $is_post_owner = ($current_user_id && $item && $item['user_id'] == $current_user_id);

    echo json_encode([
        'success' => true,
        'likes' => $like_info['likes'],
        'dislikes' => $like_info['dislikes'],
        'user_vote' => $user_vote,
        'comments' => $comments,
        'is_post_owner' => $is_post_owner
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ação inválida']);
