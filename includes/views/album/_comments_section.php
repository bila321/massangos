<?php
/**
 * @var int    $feed_item_id
 * @var int    $comment_count
 * @var int    $current_user_id
 * @var string $me_pic
 * @var array  $comment_tree
 * @var array  $photo_comments_for_album
 * @var bool   $is_owner
 */
?>
<!-- ── Comentários inline ── -->
<div class="comments-area comment-section-full va-album-comments"
    id="vaCommentsSection"
    data-feed-item-id="<?= htmlspecialchars((string)$feed_item_id) ?>">

    <div class="comments-header">
        <i class="fa-regular fa-message"></i>
        Comentários
        <span style="color:var(--c-text-muted);font-weight:400;font-size:0.78rem;" id="vaPageCommentCountLabel">
            (<?= (int)$comment_count ?>)
        </span>
    </div>

    <!-- Form no topo -->
    <?php if ($current_user_id): ?>
        <div class="comment-form-with-avatar">
            <img src="<?= UPLOAD_URL . htmlspecialchars($me_pic) ?>"
                alt="Tu" class="comment-avatar">
            <div class="comment-input-container">
                <textarea
                    id="vaCommentInput"
                    class="comment-input-container__textarea"
                    placeholder="Escreve um comentário no álbum…"
                    rows="1"
                    aria-label="Escreve um comentário"
                    data-feed-item-id="<?= htmlspecialchars((string)$feed_item_id) ?>"></textarea>
                <button class="btn-send-comment"
                    onclick="vaSubmitComment('vaCommentInput')"
                    title="Enviar"
                    aria-label="Enviar comentário">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Lista de comentários -->
    <div class="comments-list" id="vaCommentsListInline">
        <?php
        // Fundir comentários do álbum e de fotos, ordenados por likes → data
        $all_comments_merged = [];

        foreach ($comment_tree as $c) {
            if (!empty($c['source_photo_id'])) continue; // espelho antigo — ignorar
            $all_comments_merged[] = [
                'type'       => 'album',
                'created_at' => $c['created_at'],
                'data'       => $c,
            ];
        }
        foreach ($photo_comments_for_album as $pc) {
            $all_comments_merged[] = [
                'type'       => 'photo',
                'created_at' => $pc['created_at'],
                'data'       => $pc,
            ];
        }

        usort($all_comments_merged, function ($a, $b) {
            $likesA = (int)($a['data']['likes_count'] ?? 0);
            $likesB = (int)($b['data']['likes_count'] ?? 0);
            if ($likesA !== $likesB) return $likesB - $likesA;
            return strcmp($a['created_at'], $b['created_at']);
        });

        if (!empty($all_comments_merged)):
            foreach ($all_comments_merged as $item):
                if ($item['type'] === 'album'):
                    display_comments([$item['data']], $current_user_id, $is_owner, $pdo);
                else:
                    require __DIR__ . '/_photo_comment_item.php';
                endif;
            endforeach;
        else: ?>
            <div class="no-comments">
                <i class="fa-regular fa-comment-dots"></i>
                Sem comentários. Sê o primeiro!
            </div>
        <?php endif; ?>
    </div>

</div>
