<?php
/** @var mixed $user_vote @var int $feed_item_id @var array $like_info @var int $comment_count @var bool $is_owner @var int $album_id */
?>
<!-- ── Acções do álbum ── -->
<div class="va-actions" id="vaPageActions">

    <!-- Like -->
    <button class="va-action-btn va-btn-like <?= ($user_vote === 'like') ? 'active' : '' ?>"
        data-action="like"
        data-feed-item-id="<?= htmlspecialchars((string)$feed_item_id) ?>"
        title="Curtir">
        <i class="fa-regular fa-star"></i>
        <span id="vaPageLikeCount"><?= (int)$like_info['likes'] ?></span>
    </button>

    <!-- Comentários -->
    <button class="va-action-btn" data-action="scroll-comments" title="Comentários">
        <i class="fa-regular fa-message"></i>
        <span><?= (int)$comment_count ?> comentário<?= $comment_count !== 1 ? 's' : '' ?></span>
    </button>

    <div class="va-action-sep"></div>

    <!-- Guardar -->
    <button class="va-action-btn" data-action="save" title="Guardar">
        <i class="fa-regular fa-bookmark"></i>
        <span>Guardar</span>
    </button>

    <!-- Partilhar -->
    <button class="va-action-btn" data-action="share" title="Partilhar">
        <i class="fa-regular fa-paper-plane"></i>
        <span>Partilhar</span>
    </button>

    <!-- Mais opções (dono / admin) -->
    <?php if ($is_owner || isset($_SESSION['admin_id'])): ?>
        <div style="position:relative;">
            <button class="va-action-btn" data-action="more-page" title="Mais opções">
                <i class="fa-solid fa-ellipsis-vertical"></i>
            </button>
            <div class="va-more-menu" id="vaPageMoreMenu" style="display:none;">
                <a href="<?= BASE_URL ?>edit_album.php?id=<?= (int)$album_id ?>" class="va-menu-item">
                    <i class="fa-solid fa-pen"></i> Editar álbum
                </a>
                <button class="va-menu-item va-menu-danger"
                    data-action="delete-album"
                    data-album-id="<?= (int)$album_id ?>">
                    <i class="fa-solid fa-trash"></i> Apagar álbum
                </button>
            </div>
        </div>
    <?php endif; ?>
</div>
