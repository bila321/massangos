<?php

/**
 * photo_interactions.php — Endpoint unificado de interacções de fotos
 * Acções: like, comments, comment, like_comment, vote_comment,
 *         edit_comment, delete_comment
 */

// ══════════════════════════════════════════════════════════════════
// 0. PROTECÇÃO ANTECIPADA — captura fatais antes de qualquer include
// ══════════════════════════════════════════════════════════════════
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'error'   => '[Fatal] ' . $err['message'],
            'file'    => basename($err['file']),
            'line'    => $err['line'],
        ]);
    }
});

set_exception_handler(function (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'error'   => '[Exception] ' . $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ]);
    exit;
});

// ══════════════════════════════════════════════════════════════════
// 1. BOOTSTRAP
// ══════════════════════════════════════════════════════════════════
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';

// Iniciar sessão ANTES de qualquer output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

SecurityManager::initSecurity();

// Header JSON — enviado depois da sessão para evitar conflitos
header('Content-Type: application/json; charset=utf-8');

// ── Autenticação ───────────────────────────────────────────────
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Não autenticado']);
    exit;
}

// ── Ligação à base de dados ────────────────────────────────────
$pdo = $GLOBALS['pdo']
    ?? (isset($pdo) ? $pdo : null)
    ?? (class_exists('Database') ? Database::getInstance() : null);

if (!$pdo instanceof PDO) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Base de dados indisponível']);
    exit;
}

$current_user_id = (int) get_current_user_id();

// ══════════════════════════════════════════════════════════════════
// 2. PARSE DO INPUT
// ══════════════════════════════════════════════════════════════════
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $raw   = file_get_contents('php://input');
    $input = !empty($raw) ? (json_decode($raw, true) ?? $_POST) : $_POST;
} else {
    $input = $_GET;
}

$action = trim($input['action'] ?? 'like');

// ══════════════════════════════════════════════════════════════════
// 3. LIKE DE FOTO (toggle)
// ══════════════════════════════════════════════════════════════════
if ($action === 'like') {

    $photo_id = (int)($input['photo_id'] ?? $input['item_id'] ?? 0);

    // Compatibilidade: feed_item_id → photo/album id
    if (!$photo_id && !empty($input['feed_item_id'])) {
        $feed_item_id = (int)$input['feed_item_id'];
        $stmt = $pdo->prepare(
            "SELECT item_id FROM feed_items WHERE id = ? AND item_type = 'album' LIMIT 1"
        );
        $stmt->execute([$feed_item_id]);
        $photo_id = (int)$stmt->fetchColumn();
    }

    if (!$photo_id) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit;
    }

    try {
        $check = $pdo->prepare(
            "SELECT id FROM photo_likes WHERE photo_id = ? AND user_id = ?"
        );
        $check->execute([$photo_id, $current_user_id]);

        if ($check->fetch()) {
            $pdo->prepare(
                "DELETE FROM photo_likes WHERE photo_id = ? AND user_id = ?"
            )->execute([$photo_id, $current_user_id]);
            $liked     = false;
            $user_vote = null;
        } else {
            $pdo->prepare(
                "INSERT INTO photo_likes (photo_id, user_id, created_at) VALUES (?, ?, NOW())"
            )->execute([$photo_id, $current_user_id]);
            $liked     = true;
            $user_vote = 'like';
        }

        $count = $pdo->prepare("SELECT COUNT(*) FROM photo_likes WHERE photo_id = ?");
        $count->execute([$photo_id]);

        echo json_encode([
            'success'   => true,
            'liked'     => $liked,
            'user_vote' => $user_vote,
            'likes'     => (int)$count->fetchColumn(),
        ]);
    } catch (Exception $e) {
        error_log('[photo_interactions] like error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao processar like']);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════
// 4. LISTAR COMENTÁRIOS DE FOTO
// ══════════════════════════════════════════════════════════════════
if ($action === 'comments') {

    $photo_id = (int)($input['photo_id'] ?? 0);
    if (!$photo_id) {
        echo json_encode(['success' => false, 'error' => 'photo_id obrigatório']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                pc.id,
                pc.user_id,
                pc.content,
                pc.created_at,
                pc.parent_comment_id,
                u.username,
                u.profile_picture,
                (SELECT COUNT(*) FROM photo_comment_likes WHERE comment_id = pc.id)        AS likes_count,
                (SELECT COUNT(*) FROM photo_comment_likes
                 WHERE comment_id = pc.id AND user_id = ?)                                 AS user_liked
            FROM photo_comments pc
            JOIN users u ON u.id = pc.user_id
            WHERE pc.photo_id = ?
            ORDER BY pc.created_at ASC
        ");
        $stmt->execute([$current_user_id, $photo_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Converter tipos para JSON
        foreach ($rows as &$r) {
            $r['likes_count'] = (int)$r['likes_count'];
            $r['user_liked']  = (bool)$r['user_liked'];
        }
        unset($r);

        $root_count = count(array_filter($rows, fn($r) => $r['parent_comment_id'] === null));

        echo json_encode([
            'success'  => true,
            'comments' => $rows,
            'total'    => $root_count,
        ]);
    } catch (Exception $e) {
        error_log('[photo_interactions] comments error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao carregar comentários']);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════
// 5. INSERIR COMENTÁRIO DE FOTO
// ══════════════════════════════════════════════════════════════════
if ($action === 'comment') {

    $photo_id          = (int)($input['photo_id'] ?? 0);
    $content           = trim($input['content'] ?? '');
    $parent_comment_id = isset($input['parent_comment_id']) && $input['parent_comment_id'] !== ''
        ? (int)$input['parent_comment_id']
        : null;

    if (!$photo_id || $content === '') {
        echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
        exit;
    }

    // Validar que a foto existe
    $chk = $pdo->prepare("SELECT id FROM album_photos WHERE id = ? LIMIT 1");
    $chk->execute([$photo_id]);
    if (!$chk->fetchColumn()) {
        echo json_encode(['success' => false, 'error' => 'Foto não encontrada']);
        exit;
    }

    // Validar parent_comment_id se fornecido
    if ($parent_comment_id !== null) {
        $chk2 = $pdo->prepare("SELECT id FROM photo_comments WHERE id = ? AND photo_id = ? LIMIT 1");
        $chk2->execute([$parent_comment_id, $photo_id]);
        if (!$chk2->fetchColumn()) {
            $parent_comment_id = null; // ignora parent inválido
        }
    }

    try {
        $ins = $pdo->prepare("
            INSERT INTO photo_comments (photo_id, user_id, content, parent_comment_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $ins->execute([$photo_id, $current_user_id, $content, $parent_comment_id]);
        $new_id = (int)$pdo->lastInsertId();

        // Buscar o comentário criado com dados do utilizador para renderização imediata
        $new = $pdo->prepare("
            SELECT pc.id, pc.user_id, pc.content, pc.created_at, pc.parent_comment_id,
                   u.username, u.profile_picture
            FROM photo_comments pc
            JOIN users u ON u.id = pc.user_id
            WHERE pc.id = ?
        ");
        $new->execute([$new_id]);
        $comment = $new->fetch(PDO::FETCH_ASSOC);

        $totalQ = $pdo->prepare(
            "SELECT COUNT(*) FROM photo_comments WHERE photo_id = ? AND parent_comment_id IS NULL"
        );
        $totalQ->execute([$photo_id]);

        echo json_encode([
            'success'    => true,
            'comment_id' => $new_id,
            'comment'    => $comment,
            'total'      => (int)$totalQ->fetchColumn(),
        ]);
    } catch (Exception $e) {
        error_log('[photo_interactions] comment insert error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao guardar comentário']);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════
// 6. LIKE DE COMENTÁRIO DE FOTO (toggle)
// ══════════════════════════════════════════════════════════════════
if ($action === 'like_comment') {

    $comment_id = (int)($input['comment_id'] ?? 0);
    if (!$comment_id) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit;
    }

    // Verificar que o comentário existe
    $chk = $pdo->prepare("SELECT id FROM photo_comments WHERE id = ? LIMIT 1");
    $chk->execute([$comment_id]);
    if (!$chk->fetchColumn()) {
        echo json_encode(['success' => false, 'error' => 'Comentário não encontrado']);
        exit;
    }

    try {
        $check = $pdo->prepare(
            "SELECT id FROM photo_comment_likes WHERE comment_id = ? AND user_id = ?"
        );
        $check->execute([$comment_id, $current_user_id]);

        if ($check->fetch()) {
            $pdo->prepare(
                "DELETE FROM photo_comment_likes WHERE comment_id = ? AND user_id = ?"
            )->execute([$comment_id, $current_user_id]);
            $liked = false;
        } else {
            $pdo->prepare(
                "INSERT INTO photo_comment_likes (comment_id, user_id, created_at) VALUES (?, ?, NOW())"
            )->execute([$comment_id, $current_user_id]);
            $liked = true;
        }

        $count = $pdo->prepare(
            "SELECT COUNT(*) FROM photo_comment_likes WHERE comment_id = ?"
        );
        $count->execute([$comment_id]);

        echo json_encode([
            'success' => true,
            'liked'   => $liked,
            'likes'   => (int)$count->fetchColumn(),
        ]);
    } catch (Exception $e) {
        error_log('[photo_interactions] like_comment error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao processar like']);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════
// 7. VOTAR EM COMENTÁRIO (like/dislike genérico)
// ══════════════════════════════════════════════════════════════════
if ($action === 'vote_comment') {

    $comment_id = (int)($input['comment_id'] ?? 0);
    $vote_type  = $input['vote_type'] ?? 'like';

    if (!$comment_id || !in_array($vote_type, ['like', 'dislike'], true)) {
        echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
        exit;
    }

    try {
        $check = $pdo->prepare(
            "SELECT vote_type FROM comment_votes WHERE comment_id = ? AND user_id = ?"
        );
        $check->execute([$comment_id, $current_user_id]);
        $existing = $check->fetchColumn();

        if ($existing === $vote_type) {
            // Mesmo voto → remover (toggle off)
            $pdo->prepare(
                "DELETE FROM comment_votes WHERE comment_id = ? AND user_id = ?"
            )->execute([$comment_id, $current_user_id]);
            $user_vote = null;
        } else {
            // Novo voto ou mudança de voto
            $pdo->prepare("
                INSERT INTO comment_votes (comment_id, user_id, vote_type, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE vote_type = VALUES(vote_type), created_at = NOW()
            ")->execute([$comment_id, $current_user_id, $vote_type]);
            $user_vote = $vote_type;
        }

        $likes = $pdo->prepare(
            "SELECT COUNT(*) FROM comment_votes WHERE comment_id = ? AND vote_type = 'like'"
        );
        $likes->execute([$comment_id]);

        $dislikes = $pdo->prepare(
            "SELECT COUNT(*) FROM comment_votes WHERE comment_id = ? AND vote_type = 'dislike'"
        );
        $dislikes->execute([$comment_id]);

        echo json_encode([
            'success'   => true,
            'likes'     => (int)$likes->fetchColumn(),
            'dislikes'  => (int)$dislikes->fetchColumn(),
            'user_vote' => $user_vote,
        ]);
    } catch (Exception $e) {
        error_log('[photo_interactions] vote_comment error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao votar']);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════
// 8. EDITAR COMENTÁRIO DE FOTO
// ══════════════════════════════════════════════════════════════════
if ($action === 'edit_comment') {

    $comment_id = (int)($input['comment_id'] ?? 0);
    $content    = trim($input['content'] ?? '');

    if (!$comment_id || $content === '') {
        echo json_encode(['success' => false, 'error' => 'Dados inválidos']);
        exit;
    }

    try {
        $check = $pdo->prepare("SELECT user_id FROM photo_comments WHERE id = ?");
        $check->execute([$comment_id]);
        $owner_id = $check->fetchColumn();

        if ($owner_id === false) {
            echo json_encode(['success' => false, 'error' => 'Comentário não encontrado']);
            exit;
        }

        if ((int)$owner_id !== $current_user_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sem permissão']);
            exit;
        }

        $pdo->prepare(
            "UPDATE photo_comments SET content = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$content, $comment_id]);

        echo json_encode(['success' => true, 'content' => $content]);
    } catch (Exception $e) {
        error_log('[photo_interactions] edit_comment error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao editar']);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════
// 9. APAGAR COMENTÁRIO DE FOTO
// ══════════════════════════════════════════════════════════════════
if ($action === 'delete_comment') {

    $comment_id = (int)($input['comment_id'] ?? 0);
    if (!$comment_id) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit;
    }

    try {
        // Buscar dono do comentário e álbum associado
        $check = $pdo->prepare("
            SELECT pc.user_id AS comment_owner, ap.album_id
            FROM photo_comments pc
            JOIN album_photos ap ON ap.id = pc.photo_id
            WHERE pc.id = ?
        ");
        $check->execute([$comment_id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Comentário não encontrado']);
            exit;
        }

        $is_comment_owner = ((int)$row['comment_owner'] === $current_user_id);
        $is_album_owner   = false;

        if (!$is_comment_owner) {
            $al = $pdo->prepare("SELECT user_id FROM albums WHERE id = ?");
            $al->execute([$row['album_id']]);
            $is_album_owner = ((int)$al->fetchColumn() === $current_user_id);
        }

        // Admins também podem apagar
        $is_admin = isset($_SESSION['admin_id']);

        if (!$is_comment_owner && !$is_album_owner && !$is_admin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sem permissão']);
            exit;
        }

        // Apagar likes das respostas, depois as respostas, depois likes e o comentário
        $pdo->prepare("
            DELETE FROM photo_comment_likes
            WHERE comment_id IN (
                SELECT id FROM photo_comments WHERE parent_comment_id = ?
            )
        ")->execute([$comment_id]);

        $pdo->prepare(
            "DELETE FROM photo_comments WHERE parent_comment_id = ?"
        )->execute([$comment_id]);

        $pdo->prepare(
            "DELETE FROM photo_comment_likes WHERE comment_id = ?"
        )->execute([$comment_id]);

        $pdo->prepare(
            "DELETE FROM photo_comments WHERE id = ?"
        )->execute([$comment_id]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log('[photo_interactions] delete_comment error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao apagar']);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════
// 10. ACÇÃO DESCONHECIDA
// ══════════════════════════════════════════════════════════════════
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Acção inválida: ' . htmlspecialchars($action)]);
