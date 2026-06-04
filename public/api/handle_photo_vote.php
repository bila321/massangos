<?php
// public/api/handle_photo_vote.php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (!is_logged_in()) {
    $response['message'] = 'Você precisa estar logado para votar.';
    $response['redirect_to_login'] = true;
    echo json_encode($response);
    exit();
}

$user_id = get_current_user_id();
$input = json_decode(file_get_contents('php://input'), true);

$photo_id = $input['photoId'] ?? null;
$action = $input['action'] ?? null;

if ($photo_id && ($action === 'like' || $action === 'dislike' || $action === 'get_status')) { // Adicione 'get_status'
    try {
        if ($action === 'get_status') { // Novo caso para obter apenas o status
            $counts = PhotoLike::getPhotoLikesDislikesCount($pdo, $photo_id);
            $user_vote = PhotoLike::getUserPhotoVote($pdo, $photo_id, $user_id);
        } else {
            PhotoLike::addOrUpdatePhotoLike($pdo, $photo_id, $user_id, $action); // <--- MUDANÇA AQUI!
            $counts = PhotoLike::getPhotoLikesDislikesCount($pdo, $photo_id); // <--- MUDANÇA AQUI!
            $user_vote = PhotoLike::getUserPhotoVote($pdo, $photo_id, $user_id); // <--- MUDANÇA AQUI!
        }

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

echo json_encode($response);
?>