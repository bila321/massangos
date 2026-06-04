<?php
// public/process_comment.php
header('Content-Type: application/json');

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
SecurityManager::initSecurity();
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Services\CommentService;

$pdo = \Massango\Core\Database::getInstance();

$response = ['success' => false, 'message' => ''];

if (!$pdo) {
    $response['message'] = 'Erro interno: Falha na conexão com o banco de dados.';
    echo json_encode($response);
    exit();
}

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

if (!is_logged_in() && $action !== 'get_comments') {
    $response['message'] = 'É necessário estar logado para realizar esta ação.';
    echo json_encode($response);
    exit();
}

$currentUserId = get_current_user_id();
$commentService = new CommentService($pdo, $currentUserId);

switch ($action) {
    case 'add_comment':
    case 'add_reply':
        $content = sanitize_input(trim(filter_input(INPUT_POST, 'comment_content', FILTER_UNSAFE_RAW) ?? ''));
        $feedItemId = filter_input(INPUT_POST, 'feed_item_id', FILTER_VALIDATE_INT);
        $parentCommentId = filter_input(INPUT_POST, 'parent_comment_id', FILTER_VALIDATE_INT) ?: null;

        if (empty($content) || !$feedItemId) {
            $response['message'] = 'Conteúdo do comentário ou ID do item do feed inválido.';
            break;
        }

        $result = $commentService->addComment($feedItemId, $content, $parentCommentId);
        $response = array_merge($response, $result);
        break;

    case 'vote_comment':
        $commentId = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);
        $voteType = filter_input(INPUT_POST, 'vote_type', FILTER_SANITIZE_STRING);

        if (!$commentId || !in_array($voteType, ['like', 'dislike'])) {
            $response['message'] = 'Dados de voto inválidos.';
            break;
        }

        $result = $commentService->voteComment($commentId, $voteType);
        $response = array_merge($response, $result);
        break;

    case 'delete_comment':
        $commentId = filter_input(INPUT_POST, 'comment_id', FILTER_VALIDATE_INT);
        $isPostOwner = filter_input(INPUT_POST, 'is_post_owner', FILTER_VALIDATE_BOOLEAN) ?? false;

        if (!$commentId) {
            $response['message'] = 'ID do comentário inválido.';
            break;
        }

        $result = $commentService->deleteComment($commentId, $isPostOwner);
        $response = array_merge($response, $result);
        break;

    case 'get_comments':
        // TODO: Implementar via CommentService ou manter lógica existente
        $response['message'] = 'Ação get_comments não implementada no CommentService ainda.';
        break;

    default:
        $response['message'] = 'Ação inválida.';
        break;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

echo json_encode($response);
exit();
