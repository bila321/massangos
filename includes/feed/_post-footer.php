<?php
/**
 * Partial: _post-footer.php
 *
 * Variáveis esperadas no scope:
 *   $item          – array completo do feed item
 *   $like_info     – ['likes' => int, 'dislikes' => int]
 *   $user_vote     – 'like' | 'dislike' | null
 *   $comment_count – int
 *   $content_data  – array do conteúdo (para allow_share_*)
 *   $csrf_token    – string
 *   $saved_ids     – array de chaves "tipo_id" já guardadas
 */
?>
<div class="post-footer">
    <div class="post-actions">

        <!-- Like / Dislike pill (estilo YouTube) -->
        <div class="yt-like-pill">
            <button class="yt-action-btn btn-like <?= ($user_vote === 'like'    ? 'active' : '') ?>"
                    data-feed-item-id="<?= htmlspecialchars($item['feed_item_id']) ?>"
                    data-action="like"
                    onclick="handleVote(this)"
                    title="Gosto">
                <i class="fa-regular fa-star"></i>
                <span class="likes-count"><?= $like_info['likes'] ?></span>
            </button>
        </div>

        <!-- Comentar -->
        <a href="<?= BASE_URL ?>post.php?id=<?= htmlspecialchars($item['feed_item_id']) ?>"
           class="yt-action-btn yt-pill"
           title="Comentar">
            <i class="fa-regular fa-message"></i>
            <span class="comment-count-display"><?= htmlspecialchars($comment_count) ?></span>
        </a>

        <?php
        $share_count = $item['share_count'] ?? 0;
        $can_link    = $content_data['allow_share_link']   ?? 1;
        $can_repost  = $content_data['allow_share_repost'] ?? 1;
        ?>

        <!-- Partilhar -->
        <div class="share-container" style="position: relative; display: inline-flex;">
            <button type="button"
                    class="yt-action-btn yt-pill"
                    onclick="event.stopPropagation(); toggleShareMenu(<?= (int)$item['feed_item_id'] ?>)"
                    title="Partilhar">
                <i class="fa-regular fa-paper-plane"></i>
                <span id="share-count-<?= (int)$item['feed_item_id'] ?>"><?= (int)$share_count ?></span>
            </button>

            <div id="share-menu-<?= (int)$item['feed_item_id'] ?>"
                 class="share-dropdown"
                 style="display:none; position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%);
                        background:var(--bg-surface,#1a1a1a); border:1px solid var(--border,#333);
                        border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,.5);
                        z-index:9999; min-width:180px; padding:8px 0;">

                <?php if ($can_link): ?>
                    <button type="button"
                            onclick="event.stopPropagation(); copyToClipboard('<?= BASE_URL ?>post.php?id=<?= (int)$item['feed_item_id'] ?>', <?= (int)$item['feed_item_id'] ?>)"
                            class="share-option-btn"
                            style="width:100%; text-align:left; padding:10px 16px; background:none; border:none;
                                   cursor:pointer; color:var(--text-main,#fff); font-size:.9rem;
                                   display:flex; align-items:center; gap:10px; transition:background .2s;">
                        <i class="fa-regular fa-link" style="width:16px;"></i> Copiar Link
                    </button>
                <?php endif; ?>

                <?php if ($can_repost): ?>
                    <button type="button"
                            onclick="event.stopPropagation(); handleRepost(<?= (int)$item['feed_item_id'] ?>)"
                            class="share-option-btn"
                            style="width:100%; text-align:left; padding:10px 16px; background:none; border:none;
                                   cursor:pointer; color:var(--text-main,#fff); font-size:.9rem;
                                   display:flex; align-items:center; gap:10px; transition:background .2s;">
                        <i class="fa-solid fa-retweet" style="width:16px;"></i> Repostar
                    </button>
                <?php endif; ?>

            </div><!-- /#share-menu-* -->
        </div><!-- /.share-container -->

    </div><!-- /.post-actions -->

    <?php
    $save_key   = $item['item_type'] . '_' . $item['item_id'];
    $is_saved   = isset($saved_ids[$save_key]);
    $save_label = $is_saved ? 'Guardado' : 'Guardar';
    $save_icon  = $is_saved ? 'fa-solid fa-bookmark' : 'fa-regular fa-bookmark';
    $save_class = $is_saved ? 'btn-save active' : 'btn-save';
    ?>

    <!-- Guardar (isolado à direita) -->
    <button class="yt-action-btn yt-pill <?= $save_class ?>"
            data-item-type="<?= htmlspecialchars($item['item_type']) ?>"
            data-item-id="<?= (int)$item['item_id'] ?>"
            data-csrf="<?= htmlspecialchars($csrf_token) ?>"
            onclick="toggleSave(this)"
            title="<?= $save_label ?>">
        <i class="<?= $save_icon ?>"></i>
        <span><?= $save_label ?></span>
    </button>

</div><!-- /.post-footer -->

<!-- Secção de comentários (oculta no feed, usada apenas no Lightbox) -->
<div class="comment-section-full"
     data-feed-item-id="<?= htmlspecialchars($item['feed_item_id']) ?>"
     style="display: none;">
</div>
