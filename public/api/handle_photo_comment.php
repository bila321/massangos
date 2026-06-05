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

// Helper function to render comments HTML — classes comments_modern.css
function display_comments_html($comments, $current_user_id, $level = 0) {
    foreach ($comments as $comment) {
        $is_owner = ($comment['user_id'] == $current_user_id);
        $pic = UPLOAD_URL . htmlspecialchars($comment['profile_picture'] ?? 'profiles/default_profile.png');
        $uname = htmlspecialchars($comment['username'] ?? 'Utilizador');
        $content = nl2br(htmlspecialchars($comment['content'] ?? ''));
        $date = date('d/m/Y H:i', strtotime($comment['created_at']));
        $indent = $level > 0 ? 'comment-replies' : '';
        ?>
        <div class="comment-item <?= $indent ?>"
            data-comment-id="<?= (int)$comment['id'] ?>">

            <img src="<?= $pic ?>"
                alt="<?= $uname ?>"
                class="comment-avatar">

            <div class="comment-body">
                <div class="comment-text-wrapper">
                    <div class="comment-header">
                        <span class="comment-author"><?= $uname ?></span>
                    </div>
                    <div class="comment-text">
                        <p><?= $content ?></p>
                    </div>
                </div>

                <div class="comment-actions">
                    <span class="comment-time"><?= $date ?></span>
                    <?php if (is_logged_in()): ?>
                        <button class="btn-reply-comment"
                            data-comment-id="<?= (int)$comment['id'] ?>">Responder</button>
                        <?php if ($is_owner): ?>
                            <button class="btn-edit-comment btn-comment-edit"
                                data-comment-id="<?= (int)$comment['id'] ?>"
                                data-content="<?= htmlspecialchars($comment['content']) ?>">Editar</button>
                            <button class="btn-delete-comment btn-comment-delete"
                                data-comment-id="<?= (int)$comment['id'] ?>">Apagar</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if (is_logged_in()): ?>
                    <div class="comment-reply-form" id="reply-form-<?= (int)$comment['id'] ?>" style="display:none;">
                        <form class="reply-form-container reply-form">
                            <input type="hidden" name="photo_id" value="<?= (int)($comment['photo_id'] ?? 0) ?>">
                            <input type="hidden" name="parent_comment_id" value="<?= (int)$comment['id'] ?>">
                            <div class="comment-input-container">
                                <textarea name="comment_content"
                                    placeholder="Responder a <?= $uname ?>…"
                                    rows="1"></textarea>
                                <button type="submit" class="btn-send-comment">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if (!empty($comment['replies'])): ?>
                    <ul class="comment-list comment-replies">
                        <?php display_comments_html($comment['replies'], $current_user_id, $level + 1); ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
?>

