<?php
/**
 * public/api/photo_interactions.php
 * Likes/comentários/respostas/likes em comentários por foto de álbum.
 */
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

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

$photo_id   = (int) ($data['photo_id']   ?? 0);
$comment_id = (int) ($data['comment_id'] ?? 0);
$action     = trim($data['action'] ?? '');

// ── Verificar foto ────────────────────────────────────────────────────────────
if ($photo_id > 0) {
    $s = $pdo->prepare("SELECT id FROM album_photos WHERE id = ? LIMIT 1");
    $s->execute([$photo_id]);
    if (!$s->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Foto nao encontrada']);
        exit;
    }
}

// ════════════════════════════════════════════════════════════════════════════
// LIKE NA FOTO
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'POST' && $action === 'like' && $photo_id) {
    $s = $pdo->prepare("SELECT id FROM photo_likes WHERE photo_id=? AND user_id=? LIMIT 1");
    $s->execute([$photo_id, $current_user_id]);
    if ($s->fetchColumn()) {
        $pdo->prepare("DELETE FROM photo_likes WHERE photo_id=? AND user_id=?")->execute([$photo_id, $current_user_id]);
        $liked = false;
    } else {
        $pdo->prepare("INSERT INTO photo_likes (photo_id,user_id,type) VALUES (?,?,'like')")->execute([$photo_id, $current_user_id]);
        $liked = true;
    }
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM photo_likes WHERE photo_id=? AND type='like'");
    $cnt->execute([$photo_id]);
    echo json_encode(['success' => true, 'liked' => $liked, 'likes' => (int)$cnt->fetchColumn()]);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// COMENTÁRIO / RESPOSTA NA FOTO
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'POST' && $action === 'comment' && $photo_id) {
    $content          = trim($data['content'] ?? '');
    $parent_id        = (int)($data['parent_comment_id'] ?? 0) ?: null;

    if (empty($content)) { echo json_encode(['success' => false, 'error' => 'Vazio']); exit; }
    if (mb_strlen($content) > 2000) { echo json_encode(['success' => false, 'error' => 'Demasiado longo']); exit; }

    // Verificar parent pertence à mesma foto
    if ($parent_id) {
        $sp = $pdo->prepare("SELECT id FROM photo_comments WHERE id=? AND photo_id=? LIMIT 1");
        $sp->execute([$parent_id, $photo_id]);
        if (!$sp->fetch()) { $parent_id = null; }
    }

    $safe = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $pdo->prepare("INSERT INTO photo_comments (photo_id, user_id, parent_comment_id, content) VALUES (?,?,?,?)")
        ->execute([$photo_id, $current_user_id, $parent_id, $safe]);
    $new_id = (int)$pdo->lastInsertId();

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM photo_comments WHERE photo_id=? AND parent_comment_id IS NULL");
    $cnt->execute([$photo_id]);

    // Espelho na tabela comments geral (apenas comentários raiz, não respostas)
    $album_comment_id = null;
    if (!$parent_id) {
        try {
            $fi = $pdo->prepare("SELECT fi.id FROM feed_items fi JOIN albums a ON a.id = fi.item_id WHERE fi.item_type='album' AND (SELECT album_id FROM album_photos WHERE id=?) = a.id LIMIT 1");
            $fi->execute([$photo_id]);
            $feed_item_id = $fi->fetchColumn();
            if ($feed_item_id) {
                $pos = $pdo->prepare("SELECT COUNT(*)+1 FROM album_photos WHERE album_id=(SELECT album_id FROM album_photos WHERE id=?) AND id<=?");
                $pos->execute([$photo_id, $photo_id]);
                $photo_pos = (int)$pos->fetchColumn();
                $mirror = $pdo->prepare("INSERT INTO comments (feed_item_id,user_id,content,source_photo_id,photo_position) VALUES (?,?,?,?,?)");
                $mirror->execute([$feed_item_id, $current_user_id, "[Foto #{$photo_pos}] {$safe}", $photo_id, $photo_pos]);
                $album_comment_id = (int)$pdo->lastInsertId();
            }
        } catch (\Exception $e) { /* colunas source_photo_id/photo_position podem não existir */ }
    }

    echo json_encode([
        'success'          => true,
        'comment_id'       => $new_id,
        'album_comment_id' => $album_comment_id,
        'total'            => (int)$cnt->fetchColumn(),
        'is_reply'         => $parent_id !== null,
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// LIKE NUM COMENTÁRIO
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'POST' && $action === 'like_comment' && $comment_id) {
    $s = $pdo->prepare("SELECT id FROM photo_comment_likes WHERE comment_id=? AND user_id=? LIMIT 1");
    $s->execute([$comment_id, $current_user_id]);
    if ($s->fetchColumn()) {
        $pdo->prepare("DELETE FROM photo_comment_likes WHERE comment_id=? AND user_id=?")->execute([$comment_id, $current_user_id]);
        $liked = false;
    } else {
        $pdo->prepare("INSERT INTO photo_comment_likes (comment_id,user_id) VALUES (?,?)")->execute([$comment_id, $current_user_id]);
        $liked = true;
    }
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM photo_comment_likes WHERE comment_id=?");
    $cnt->execute([$comment_id]);
    echo json_encode(['success' => true, 'liked' => $liked, 'likes' => (int)$cnt->fetchColumn()]);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// CARREGAR COMENTÁRIOS (com respostas e likes)
// ════════════════════════════════════════════════════════════════════════════
if ($method === 'GET' && $action === 'comments' && $photo_id) {
    // Todos os comentários da foto com likes
    $s = $pdo->prepare("
        SELECT
            pc.id, pc.photo_id, pc.parent_comment_id, pc.content, pc.created_at,
            u.id AS user_id, u.username, u.profile_picture,
            COUNT(DISTINCT pcl.id) AS likes_count,
            MAX(CASE WHEN pcl.user_id = ? THEN 1 ELSE 0 END) AS user_liked
        FROM photo_comments pc
        JOIN users u ON u.id = pc.user_id
        LEFT JOIN photo_comment_likes pcl ON pcl.comment_id = pc.id
        WHERE pc.photo_id = ?
        GROUP BY pc.id
        ORDER BY pc.created_at ASC
    ");
    $s->execute([$current_user_id, $photo_id]);
    $rows = $s->fetchAll(\PDO::FETCH_ASSOC);

    // Organizar em árvore: raiz + respostas
    $roots   = [];
    $replies = [];
    foreach ($rows as $r) {
        $r['likes_count'] = (int)$r['likes_count'];
        $r['user_liked']  = (bool)$r['user_liked'];
        $r['replies']     = [];
        if ($r['parent_comment_id']) {
            $replies[$r['parent_comment_id']][] = $r;
        } else {
            $roots[$r['id']] = $r;
        }
    }
    // Anexar respostas aos pais
    foreach ($replies as $parent_id => $reps) {
        if (isset($roots[$parent_id])) {
            $roots[$parent_id]['replies'] = $reps;
        }
    }

    echo json_encode([
        'success'  => true,
        'comments' => array_values($roots),
        'total'    => count($roots),
    ]);
    exit;
}

// ── LIKES DA FOTO ─────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'likes' && $photo_id) {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM photo_likes WHERE photo_id=? AND type='like'");
    $cnt->execute([$photo_id]);
    $usr = $pdo->prepare("SELECT COUNT(*) FROM photo_likes WHERE photo_id=? AND user_id=?");
    $usr->execute([$photo_id, $current_user_id]);
    echo json_encode(['success' => true, 'likes' => (int)$cnt->fetchColumn(), 'user_liked' => (bool)$usr->fetchColumn()]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Accao nao reconhecida: ' . $action]);
