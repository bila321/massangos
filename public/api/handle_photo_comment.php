<?php
// public/api/handle_photo_comment.php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php'; // Adicionado para ter is_logged_in() e outras funções

header('Content-Type: application/json');
$response = ['success' => false, 'message' => '', 'commentsHtml' => '', 'commentId' => null];

if (!is_logged_in()) {
    $response['message'] = 'Você precisa estar logado para comentar.';
    $response['redirect_to_login'] = true;
    echo json_encode($response);
    exit();
}

$user_id = get_current_user_id();
$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? '';
$photo_id = $input['photo_id'] ?? null;
$comment_id = $input['comment_id'] ?? null;
$content = $input['content'] ?? '';
$parent_comment_id = $input['parent_comment_id'] ?? null;

if (!$photo_id) {
    $response['message'] = 'ID da foto não fornecido.';
    echo json_encode($response);
    exit();
}

try {
    switch ($action) {
        case 'add':
            if (empty($content)) {
                $response['message'] = 'O comentário não pode estar vazio.';
                break;
            }
            // Use a nova classe PhotoComment
            if (PhotoComment::addPhotoComment($pdo, $photo_id, $user_id, $content, $parent_comment_id)) { // <--- MUDANÇA AQUI!
                $response['success'] = true;
                $response['message'] = 'Comentário adicionado com sucesso!';
                $comments = PhotoComment::getCommentsForPhoto($pdo, $photo_id); // <--- MUDANÇA AQUI!
                $comment_tree = PhotoComment::buildCommentTree($comments); // <--- MUDANÇA AQUI!
                ob_start();
                display_comments_html($comment_tree, $user_id);
                $response['commentsHtml'] = ob_get_clean();
            } else {
                $response['message'] = 'Erro ao adicionar comentário.';
            }
            break;

        case 'edit':
            if (!$comment_id || empty($content)) {
                $response['message'] = 'Dados incompletos para edição.';
                break;
            }
            $comment = PhotoComment::getPhotoCommentById($pdo, $comment_id); // <--- MUDANÇA AQUI!
            if (!$comment || $comment['user_id'] != $user_id) {
                $response['message'] = 'Você não tem permissão para editar este comentário.';
                break;
            }
            if (PhotoComment::editPhotoComment($pdo, $comment_id, $user_id, $content)) { // <--- MUDANÇA AQUI!
                $response['success'] = true;
                $response['message'] = 'Comentário editado com sucesso!';
                $response['commentId'] = $comment_id;
                $response['newContent'] = nl2br(htmlspecialchars($content));
            } else {
                $response['message'] = 'Erro ao editar comentário.';
            }
            break;

        case 'delete':
            if (!$comment_id) {
                $response['message'] = 'ID do comentário não fornecido.';
                break;
            }
            $comment = PhotoComment::getPhotoCommentById($pdo, $comment_id); // <--- MUDANÇA AQUI!
            if (!$comment || $comment['user_id'] != $user_id) {
                $response['message'] = 'Você não tem permissão para apagar este comentário.';
                break;
            }
            if (PhotoComment::deletePhotoComment($pdo, $comment_id, $user_id)) { // <--- MUDANÇA AQUI!
                $response['success'] = true;
                $response['message'] = 'Comentário apagado com sucesso!';
                $response['commentId'] = $comment_id;
            } else {
                $response['message'] = 'Erro ao apagar comentário.';
            }
            break;

        case 'get_comments': // Novo case para carregar comentários separadamente
            $comments = PhotoComment::getCommentsForPhoto($pdo, $photo_id);
            $comment_tree = PhotoComment::buildCommentTree($comments);
            ob_start();
            display_comments_html($comment_tree, $user_id);
            $response['commentsHtml'] = ob_get_clean();
            $response['success'] = true;
            break;

        default:
            $response['message'] = 'Ação inválida.';
            break;
    }
} catch (PDOException $e) {
    $response['message'] = 'Erro no banco de dados: ' . $e->getMessage();
}

echo json_encode($response);

// Helper function to render comments HTML (mantém-se igual)
function display_comments_html($comments, $current_user_id, $level = 0) {
    // ... código da função display_comments_html (no arquivo handle_photo_comment.php)
    // Pequena correção aqui para usar 'photo_id' no formulário de resposta se for o caso
    // Antes: value="<?= $comment['post_id'] "
    // Mude para: value="<?= $comment['photo_id'] "
    foreach ($comments as $comment) {
        $margin_left = $level * 20;
        $is_owner = ($comment['user_id'] == $current_user_id);
        ?>
        <div class="comment-item" data-comment-id="<?= $comment['id'] ?>" style="margin-left: <?= $margin_left ?>px;">
            <div class="comment-header">
                <img src="<?= UPLOAD_URL . htmlspecialchars($comment['profile_picture'] ?? 'default_profile.png') ?>" alt="Foto de perfil" class="profile-thumb-small">
                <strong><?= htmlspecialchars($comment['username'] ?? 'Usuário Desconhecido') ?>:</strong>
                <span class="comment-date"><?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?></span>
            </div>
            <p class="comment-content-text"><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
            <?php if (is_logged_in()): ?>
                <div class="comment-actions">
                    <button class="btn-reply-comment" data-comment-id="<?= $comment['id'] ?>">Responder</button>
                    <?php if ($is_owner): ?>
                        <button class="btn-edit-comment" data-comment-id="<?= $comment['id'] ?>" data-comment-content="<?= htmlspecialchars($comment['content']) ?>">Editar</button>
                        <button class="btn-delete-comment" data-comment-id="<?= $comment['id'] ?>">Apagar</button>
                    <?php endif; ?>
                </div>
                <form action="" method="POST" class="reply-form" id="reply-form-<?= $comment['id'] ?>" style="display: none;">
                    <input type="hidden" name="photo_id" value="<?= $comment['photo_id'] ?>"> <input type="hidden" name="parent_comment_id" value="<?= $comment['id'] ?>">
                    <textarea name="comment_content" placeholder="Responder a <?= htmlspecialchars($comment['username']) ?>..." rows="2" required></textarea>
                    <button type="submit" class="btn btn-secondary">Responder</button>
                </form>
            <?php endif; ?>
            <?php
            if (isset($comment['replies']) && !empty($comment['replies'])) {
                display_comments_html($comment['replies'], $current_user_id, $level + 1);
            }
            ?>
        </div>
        <?php
    }
}
?>