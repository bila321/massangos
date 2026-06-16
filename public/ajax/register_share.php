<?php
// ajax/register_share.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

// --- Apenas POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.']);
    exit;
}

// --- Utilizador tem de estar autenticado ---
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Precisas de estar autenticado.']);
    exit;
}

$current_user_id = (int) get_current_user_id();
$feed_item_id    = (int) ($_POST['feed_item_id'] ?? 0);
$action          = trim($_POST['action'] ?? '');   // 'repost' | 'link'

if ($feed_item_id <= 0 || !in_array($action, ['repost', 'link'], true)) {
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
    exit;
}

try {
    // --- Resolver feed_item → item real ---
    $stmtFeed = $pdo->prepare("
        SELECT fi.id, fi.item_type, fi.item_id, fi.user_id
        FROM feed_items fi
        WHERE fi.id = ?
        LIMIT 1
    ");
    $stmtFeed->execute([$feed_item_id]);
    $feedItem = $stmtFeed->fetch(PDO::FETCH_ASSOC);

    if (!$feedItem) {
        echo json_encode(['success' => false, 'error' => 'Publicação não encontrada.']);
        exit;
    }

    $item_type   = $feedItem['item_type'];   // post | video | album
    $item_id     = (int) $feedItem['item_id'];
    $owner_id    = (int) $feedItem['user_id'];

    // --- Verificar permissão allow_share_repost (só aplica a posts) ---
    if ($action === 'repost' && $item_type === 'post') {
        $stmtAllow = $pdo->prepare("SELECT allow_share_repost FROM posts WHERE id = ? LIMIT 1");
        $stmtAllow->execute([$item_id]);
        $allow = $stmtAllow->fetchColumn();
        if ((int)$allow === 0) {
            echo json_encode(['success' => false, 'error' => 'O autor não permite repost desta publicação.']);
            exit;
        }
    }

    // --- Não pode repostar o próprio conteúdo ---
    if ($action === 'repost' && $owner_id === $current_user_id) {
        echo json_encode(['success' => false, 'error' => 'Não podes repostar a tua própria publicação.']);
        exit;
    }

    // --- Registar em post_shares (evitar duplicado na mesma sessão: 1 por dia por user) ---
    $stmtCheck = $pdo->prepare("
        SELECT id FROM post_shares
        WHERE post_id = ? AND user_id = ? AND type = ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
        LIMIT 1
    ");
    $stmtCheck->execute([$item_id, $current_user_id, $action]);

    if (!$stmtCheck->fetch()) {
        $stmtInsert = $pdo->prepare("
            INSERT INTO post_shares (post_id, user_id, type, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmtInsert->execute([$item_id, $current_user_id, $action]);
    }

    // --- Se for repost: criar post de repost + feed_item ---
    if ($action === 'repost') {
        // Verificar se já repostou este item (para não criar duplicados no feed)
        $stmtDup = $pdo->prepare("
            SELECT id FROM posts
            WHERE user_id = ? AND is_repost = 1
              AND shared_post_id = ? AND shared_item_type = ?
            LIMIT 1
        ");
        $stmtDup->execute([$current_user_id, $item_id, $item_type]);

        if (!$stmtDup->fetch()) {
            // Criar o post de repost
            $stmtPost = $pdo->prepare("
                INSERT INTO posts
                    (user_id, content, post_type, is_repost, shared_post_id, shared_item_type,
                     is_approved, show_in_feed, created_at)
                VALUES
                    (?, '', 'text', 1, ?, ?, 1, 1, NOW())
            ");
            $stmtPost->execute([$current_user_id, $item_id, $item_type]);
            $new_post_id = (int) $pdo->lastInsertId();

            // Criar feed_item para o repost
            $stmtFeedIns = $pdo->prepare("
                INSERT INTO feed_items (user_id, item_type, item_id, created_at, show_in_feed)
                VALUES (?, 'post', ?, NOW(), 1)
            ");
            $stmtFeedIns->execute([$current_user_id, $new_post_id]);
        }
    }

    // --- Contagem actualizada de partilhas ---
    $stmtCount = $pdo->prepare("
        SELECT COUNT(*) FROM post_shares WHERE post_id = ?
    ");
    $stmtCount->execute([$item_id]);
    $new_count = (int) $stmtCount->fetchColumn();

    echo json_encode([
        'success'   => true,
        'message'   => $action === 'repost' ? 'Repost realizado com sucesso!' : 'Link copiado!',
        'new_count' => $new_count,
    ]);
} catch (PDOException $e) {
    error_log('[register_share] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno. Tenta novamente.']);
}
