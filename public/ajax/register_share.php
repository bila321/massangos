<?php

/**
 * ajax/register_share.php
 * Registra partilhas (Link e Repost) no sistema Massangos
 */

ini_set('display_errors', 0);
error_reporting(0);

if (ob_get_level()) ob_end_clean();
ob_start();

session_start();

header('Content-Type: application/json');

try {
    // ============================================================
    // 1. INCLUDES
    // ============================================================
    $paths = [
        __DIR__ . '/../../includes/config.php',
        __DIR__ . '/../../includes/db.php',
        __DIR__ . '/../../includes/functions.php',
        __DIR__ . '/../../core/Post.php',
        __DIR__ . '/../../core/FeedItem.php',
        __DIR__ . '/../../core/User.php',
        __DIR__ . '/../../core/Notification.php'
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
        }
    }

    if (!defined('ENVIRONMENT')) {
        define('ENVIRONMENT', 'production');
    }

    // ============================================================
    // 2. VALIDACAO
    // ============================================================
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new \Exception("Sessao expirada ou utilizador nao autenticado.");
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new \Exception("Metodo de requisicao invalido.");
    }

    $feedItemId = isset($_POST['feed_item_id']) ? (int)$_POST['feed_item_id'] : 0;
    $action     = isset($_POST['action']) ? trim($_POST['action']) : '';
    $userId     = (int)$_SESSION['user_id'];

    if ($feedItemId <= 0) {
        http_response_code(400);
        throw new \Exception("ID da publicacao invalido.");
    }

    if (!isset($pdo)) {
        throw new \Exception("Erro de conexao com a base de dados.");
    }

    // ============================================================
    // 3. BUSCA FEED ITEM
    // ============================================================
    $feedItem = \Massango\Models\FeedItem::getFeedItemById($pdo, $feedItemId);

    if (!$feedItem) {
        http_response_code(400);
        throw new \Exception("Item do feed nao encontrado.");
    }

    $originalItemId = (int)$feedItem['item_id'];
    $itemType       = $feedItem['item_type'];

    // ============================================================
    // 4. RESOLVE ID FINAL (reposts encadeados)
    // ============================================================
    $finalPostId   = $originalItemId;
    $finalItemType = $itemType;

    if ($itemType === 'post') {
        $postData = \Massango\Models\Post::getPostById($pdo, $originalItemId);
        if (!$postData) {
            throw new \Exception("Publicacao original nao encontrada.");
        }
        if (!empty($postData['is_repost']) && !empty($postData['shared_post_id'])) {
            $finalPostId   = (int)$postData['shared_post_id'];
            $finalItemType = $postData['shared_item_type'] ?? 'post';
        }
    }

    // ============================================================
    // 5. VERIFICA DUPLICADO (repost)
    // ============================================================
    if ($action === 'repost') {
        $check = $pdo->prepare("
            SELECT id FROM posts 
            WHERE user_id = ? 
            AND shared_post_id = ? 
            AND shared_item_type = ? 
            AND is_repost = 1
        ");
        $check->execute([$userId, $finalPostId, $finalItemType]);
        if ($check->fetch()) {
            throw new \Exception("Voce ja repostou esta publicacao.");
        }
    }

    // ============================================================
    // 6. BUSCA AUTOR ORIGINAL
    // ============================================================
    $originalAuthorId = 0;

    if ($finalItemType === 'post') {
        $orig = \Massango\Models\Post::getPostById($pdo, $finalPostId);
        $originalAuthorId = $orig ? (int)$orig['user_id'] : 0;
    } elseif ($finalItemType === 'video') {
        $orig = \Massango\Models\Video::getVideoById($pdo, $finalPostId);
        $originalAuthorId = $orig ? (int)$orig['user_id'] : 0;
    } elseif ($finalItemType === 'album') {
        $orig = \Massango\Models\Album::getAlbumById($pdo, $finalPostId);
        $originalAuthorId = $orig ? (int)$orig['user_id'] : 0;
    }

    if ($originalAuthorId === $userId) {
        throw new \Exception("Voce nao pode repostar sua propria publicacao.");
    }

    // ============================================================
    // 7. REGISTRA NA TABELA post_shares (SEMPRE!)
    // ============================================================
    $statType = ($action === 'repost') ? 'repost' : 'link';

    $stmtShare = $pdo->prepare("
        INSERT INTO post_shares (post_id, user_id, type, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmtShare->execute([$finalPostId, $userId, $statType]);

    // ============================================================
    // 8. SE FOR REPOST, CRIA POST E FEED ITEM
    // ============================================================
    $message = "Partilha registada!";

    if ($action === 'repost') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO posts 
                (user_id, content, post_type, is_approved, show_in_feed, 
                 is_repost, shared_post_id, shared_item_type, created_at) 
                VALUES (?, '', 'text', 1, 1, 1, ?, ?, NOW())
            ");

            if (!$stmt->execute([$userId, $finalPostId, $finalItemType])) {
                throw new \Exception("Falha ao inserir repost no banco.");
            }

            $newPostId = $pdo->lastInsertId();

            \Massango\Models\FeedItem::createFeedItem(
                $pdo,
                $userId,
                'post',
                $newPostId,
                1
            );

            // Notifica autor original
            if ($originalAuthorId > 0 && $originalAuthorId != $userId) {
                $repostUser = \Massango\Models\User::getUserById($pdo, $userId);
                $repostUsername = $repostUser ? $repostUser['username'] : 'Alguem';
                $notificationMessage = "@$repostUsername repostou sua publicacao!";

                $newFeedItem = \Massango\Models\FeedItem::getFeedItemByContentId(
                    $pdo,
                    $newPostId,
                    'post'
                );
                $newFeedItemId = $newFeedItem ? $newFeedItem['id'] : $newPostId;
                $notificationLink = BASE_URL . 'post.php?id=' . $newFeedItemId;

                \Massango\Models\Notification::createNotification(
                    $pdo,
                    $originalAuthorId,
                    $notificationMessage,
                    $notificationLink,
                    $userId,
                    'post_reposted',
                    $newPostId
                );
            }

            $pdo->commit();
            $message = "Repost realizado com sucesso!";
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // ============================================================
    // 9. RETORNA CONTADOR ATUALIZADO
    // ============================================================
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM post_shares WHERE post_id = ?
    ");
    $countStmt->execute([$finalPostId]);
    $newCount = (int)$countStmt->fetchColumn();

    if (ob_get_level()) {
        ob_end_clean();
    }

    echo json_encode([
        'success'         => true,
        'new_count'       => $newCount,
        'message'         => $message,
        'final_post_id'   => $finalPostId,
        'final_item_type' => $finalItemType
    ]);
} catch (\Throwable $e) {
    if (ob_get_level()) {
        ob_end_clean();
    }

    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
exit;