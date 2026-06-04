<?php
// public/api/handle_vote.php
require_once __DIR__ . '/../../includes/db.php'; // Inclui conexão e sessão

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!is_logged_in()) {
    $response['message'] = 'Você precisa estar logado para votar.';
    $response['redirect_to_login'] = true;
    echo json_encode($response);
    exit();
}

$user_id = get_current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $postId = $input['postId'] ?? null;
    $action = $input['action'] ?? null; // 'like' ou 'dislike'

    if ($postId && ($action === 'like' || $action === 'dislike')) {
        try {
            Like::addOrUpdatePostLike($pdo, $postId, $user_id, $action);

            $counts = Like::getPostLikesDislikesCount($pdo, $postId);
            $user_vote = Like::getUserPostVote($pdo, $postId, $user_id);

            $response['success'] = true;
            $response['likes'] = $counts['likes'];
            $response['dislikes'] = $counts['dislikes'];
            $response['user_vote'] = $user_vote;

        } catch (PDOException $e) {
            $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Dados inválidos.';
    }
} else {
    $response['message'] = 'Método de requisição não permitido.';
}

echo json_encode($response);
?>