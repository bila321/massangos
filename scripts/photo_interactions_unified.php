<?php
/**
 * photo_interactions.php - ENDPOINT UNIFICADO DE LIKES (Compatibilidade JS corrigida)
 */

define('SECURE_ACCESS', true);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';

SecurityManager::initSecurity();

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

$pdo = $GLOBALS['pdo'] ?? Database::getInstance();
$current_user_id = (int) get_current_user_id();

$method = $_SERVER['REQUEST_METHOD'];
$input = $method === 'POST'
    ? (json_decode(file_get_contents('php://input'), true) ?? $_POST)
    : $_GET;

$action = $input['action'] ?? 'like';

// ============================================================
// LIKE (Toggle) - Compatível com view_album.js
// ============================================================
if ($action === 'like') {

    $photo_id = (int)($input['photo_id'] ?? $input['item_id'] ?? 0);

    if (!$photo_id && !empty($input['feed_item_id'])) {
        $feed_item_id = (int)$input['feed_item_id'];
        $stmt = $pdo->prepare("SELECT item_id FROM feed_items WHERE id = ? AND item_type = 'album' LIMIT 1");
        $stmt->execute([$feed_item_id]);
        $photo_id = (int)$stmt->fetchColumn();
    }

    if (!$photo_id) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit;
    }

    try {
        $check = $pdo->prepare("SELECT id FROM photo_likes WHERE photo_id = ? AND user_id = ?");
        $check->execute([$photo_id, $current_user_id]);

        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM photo_likes WHERE photo_id = ? AND user_id = ?")->execute([$photo_id, $current_user_id]);
            $liked = false;
            $user_vote = null;
        } else {
            $pdo->prepare("INSERT INTO photo_likes (photo_id, user_id, created_at) VALUES (?, ?, NOW())")->execute([$photo_id, $current_user_id]);
            $liked = true;
            $user_vote = 'like';
        }

        $count = $pdo->prepare("SELECT COUNT(*) FROM photo_likes WHERE photo_id = ?");
        $count->execute([$photo_id]);
        $likes = $count->fetchColumn();

        // Devolve ambos os formatos para compatibilidade
        echo json_encode([
            'success'   => true,
            'liked'     => $liked,
            'user_vote' => $user_vote,
            'likes'     => (int)$likes
        ]);
    } catch (Exception $e) {
        error_log("Like error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erro ao processar like']);
    }
    exit;
}

// ============================================================
// VOTE COMMENT
// ============================================================
if ($action === 'vote_comment') {
    $comment_id = (int)($input['comment_id'] ?? 0);
    $vote_type  = $input['vote_type'] ?? 'like';

    if (!$comment_id || !in_array($vote_type, ['like', 'dislike'])) {
        echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
        exit;
    }

    try {
        $check = $pdo->prepare("SELECT vote_type FROM comment_votes WHERE comment_id = ? AND user_id = ?");
        $check->execute([$comment_id, $current_user_id]);
        $existing = $check->fetchColumn();

        if ($existing === $vote_type) {
            $pdo->prepare("DELETE FROM comment_votes WHERE comment_id = ? AND user_id = ?")->execute([$comment_id, $current_user_id]);
            $user_vote = null;
        } else {
            $pdo->prepare("
                INSERT INTO comment_votes (comment_id, user_id, vote_type, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE vote_type = VALUES(vote_type)
            ")->execute([$comment_id, $current_user_id, $vote_type]);
            $user_vote = $vote_type;
        }

        $likes = $pdo->prepare("SELECT COUNT(*) FROM comment_votes WHERE comment_id = ? AND vote_type = 'like'");
        $likes->execute([$comment_id]);
        $likesCount = $likes->fetchColumn();

        $dislikes = $pdo->prepare("SELECT COUNT(*) FROM comment_votes WHERE comment_id = ? AND vote_type = 'dislike'");
        $dislikes->execute([$comment_id]);
        $dislikesCount = $dislikes->fetchColumn();

        echo json_encode([
            'success'   => true,
            'likes'     => (int)$likesCount,
            'dislikes'  => (int)$dislikesCount,
            'user_vote' => $user_vote
        ]);
    } catch (Exception $e) {
        error_log("vote_comment error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erro ao votar']);
    }
    exit;
}

// ============================================================
// LIKE COMMENT (Foto)
// ============================================================
if ($action === 'like_comment') {
    $comment_id = (int)($input['comment_id'] ?? 0);
    if (!$comment_id) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit;
    }

    try {
        $check = $pdo->prepare("SELECT id FROM photo_comment_likes WHERE comment_id = ? AND user_id = ?");
        $check->execute([$comment_id, $current_user_id]);

        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM photo_comment_likes WHERE comment_id = ? AND user_id = ?")->execute([$comment_id, $current_user_id]);
            $liked = false;
        } else {
            $pdo->prepare("INSERT INTO photo_comment_likes (comment_id, user_id, created_at) VALUES (?, ?, NOW())")->execute([$comment_id, $current_user_id]);
            $liked = true;
        }

        $count = $pdo->prepare("SELECT COUNT(*) FROM photo_comment_likes WHERE comment_id = ?");
        $count->execute([$comment_id]);

        echo json_encode(['success' => true, 'liked' => $liked, 'likes' => (int)$count->fetchColumn()]);
    } catch (Exception $e) {
        error_log("like_comment error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erro ao processar']);
    }
    exit;
}

// ============================================================
// LISTAR COMENTÁRIOS DE FOTO
// ============================================================
if ($action === 'comments' && !empty($input['photo_id'])) {
    $photo_id = (int)$input['photo_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT 
                pc.id, pc.user_id, pc.content, pc.created_at,
                u.username, u.profile_picture,
                (SELECT COUNT(*) FROM photo_comment_likes WHERE comment_id = pc.id) as likes_count,
                EXISTS(SELECT 1 FROM photo_comment_likes WHERE comment_id = pc.id AND user_id = ?) as user_liked
            FROM photo_comments pc
            JOIN users u ON u.id = pc.user_id
            WHERE pc.photo_id = ?
            ORDER BY pc.created_at ASC
        ");
        $stmt->execute([$current_user_id, $photo_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'comments' => $comments]);
    } catch (Exception $e) {
        error_log("comments error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Erro ao carregar comentários']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Ação inválida']);
