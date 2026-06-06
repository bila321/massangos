<?php
/**
 * render_comment_html
 *
 * Devolve o HTML de UM comentario (raiz ou resposta) compativel com
 * comments_modern.css. Reutilizado pelo CommentService::addComment() para
 * o optimistic UI no view_album.
 *
 * Os parametros sao os mesmos de display_comments() mas para um unico item.
 *
 * @param array $comment  Dados do comentario (com replies opcional)
 * @param int|null $currentUserId
 * @param bool $isPostOwner
 * @param PDO $pdo
 * @param int $level Nivel de aninhamento (0 = raiz)
 * @return string HTML do comentario
 */
if (!function_exists('render_comment_html')) {
    function render_comment_html(array $comment, ?int $currentUserId, bool $isPostOwner, PDO $pdo, int $level = 0): string
    {
        // Usar a classe User do namespace correcto
        $authorClass = class_exists('\Massango\Models\User') ? '\Massango\Models\User' : 'User';

        $author = null;
        if (method_exists($authorClass, 'getUserById')) {
            $author = $authorClass::getUserById($pdo, $comment['user_id']);
        }
        if (!$author) {
            $author = ['username' => 'Utilizador Desconhecido', 'profile_picture' => 'profiles/default_profile.png', 'id' => 0];
        }

        $profile_picture_url = UPLOAD_URL . htmlspecialchars($author['profile_picture'] ?? 'profiles/default_profile.png');

        $is_comment_owner = ($currentUserId && $comment['user_id'] == $currentUserId);
        $can_delete = ($is_comment_owner || $isPostOwner);

        $likes_count    = (int)($comment['likes_count'] ?? 0);
        $dislikes_count = (int)($comment['dislikes_count'] ?? 0);
        $user_vote      = $comment['user_vote'] ?? null;
        $cid            = (int)$comment['id'];

        $authorUsername = htmlspecialchars($author['username']);
        $contentHtml    = nl2br(htmlspecialchars($comment['content']));
        $createdAgo     = function_exists('format_datetime_ago') ? format_datetime_ago($comment['created_at']) : $comment['created_at'];
        $feedItemId     = htmlspecialchars($comment['feed_item_id'] ?? '');

        $likeActive    = $user_vote === 'like' ? 'active' : '';
        $dislikeActive = $user_vote === 'dislike' ? 'active' : '';

        ob_start(); ?>
<li class="comment-item"
    data-comment-id="<?= $cid ?>"
    data-likes="<?= $likes_count ?>"
    data-created-at="<?= htmlspecialchars($comment['created_at']) ?>">
    <img src="<?= $profile_picture_url ?>"
         alt="Foto de perfil de <?= $authorUsername ?>"
         class="comment-avatar"
         onerror="this.src='<?= UPLOAD_URL ?>profiles/default_profile.png'">
    <div class="comment-body">
        <div class="comment-text-wrapper">
            <div class="comment-header">
                <a href="<?= BASE_URL ?>profile.php?id=<?= htmlspecialchars($author['id']) ?>" class="comment-author"><?= $authorUsername ?></a>
                <?php if ($is_comment_owner || $can_delete): ?>
                <div class="comment-actions-dropdown">
                    <button class="dropdown-toggle" aria-label="Opcoes">&#x22EE;</button>
                    <div class="dropdown-menu" style="display:none;">
                        <?php if ($is_comment_owner): ?>
                            <button class="edit-comment-btn"
                                    data-comment-id="<?= $cid ?>"
                                    data-content="<?= htmlspecialchars($comment['content']) ?>">Editar</button>
                        <?php endif; ?>
                        <?php if ($can_delete): ?>
                            <button class="delete-comment-btn"
                                    data-comment-id="<?= $cid ?>"
                                    data-feed-item-id="<?= $feedItemId ?>">Apagar</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="comment-text">
                <p><?= $contentHtml ?></p>
            </div>
        </div>
        <div class="comment-actions">
            <span class="comment-time"><?= $createdAgo ?></span>
            <button class="btn-comment-like <?= $likeActive ?>"
                    data-comment-id="<?= $cid ?>"
                    data-vote-type="like">
                Gosto <span class="comment-likes-count"><?= $likes_count ?></span>
            </button>
            <button class="btn-comment-dislike <?= $dislikeActive ?>"
                    data-comment-id="<?= $cid ?>"
                    data-vote-type="dislike">
                Nao gosto
            </button>
            <?php if ($currentUserId): ?>
                <button class="btn-reply-comment"
                        data-comment-id="<?= $cid ?>"
                        data-comment-author-username="<?= $authorUsername ?>">Responder</button>
            <?php endif; ?>
        </div>
    </div>
</li>
<?php
        return ob_get_clean();
    }
}
