<?php
/**
 * Partial: um card de resultado de conteúdo.
 * Variável disponível do foreach em _content_results.php:
 *
 * @var array $item
 */
$author = $item['author'];
?>
<article class="post-card card search-result-card">
    <div class="post-header">
        <img src="<?= UPLOAD_URL . htmlspecialchars($author['profile_picture'] ?? 'default_profile.png') ?>"
            alt="Foto de perfil" class="profile-thumb">
        <div class="post-info">
            <a href="<?= BASE_URL ?>profile.php?id=<?= htmlspecialchars((string)$author['id']) ?>" class="post-author">
                <?= htmlspecialchars($author['username']) ?>
            </a>
            <span class="post-date"><?= format_datetime_ago($item['created_at']) ?></span>
        </div>
        <div class="search-result-price-wrap">
            <span class="badge-price <?= $item['is_paid'] ? 'badge-price--paid' : 'badge-price--free' ?>">
                <?= $item['is_paid'] ? 'PRÉMIO' : 'GRÁTIS' ?>
            </span>
        </div>
    </div>

    <div class="post-content search-result-content">
        <?php if ($item['item_type'] === 'post'): ?>

            <p><?= nl2br(htmlspecialchars($item['content'])) ?></p>
            <?php if (!empty($item['image_path'])): ?>
                <img src="<?= UPLOAD_URL . htmlspecialchars($item['image_path']) ?>"
                    class="search-result-image">
            <?php endif; ?>

        <?php elseif ($item['item_type'] === 'video'): ?>

            <p><?= nl2br(htmlspecialchars($item['caption'])) ?></p>
            <div class="video-preview search-result-video-preview">
                <?php if (!empty($item['thumbnail_path'])): ?>
                    <img src="<?= UPLOAD_URL . htmlspecialchars($item['thumbnail_path']) ?>"
                        class="search-result-video-thumb">
                <?php endif; ?>
                <i class="fas fa-play-circle search-result-play-icon"></i>
            </div>

        <?php elseif ($item['item_type'] === 'album'): ?>

            <h4><?= htmlspecialchars($item['album_name']) ?></h4>
            <p><?= nl2br(htmlspecialchars($item['album_description'])) ?></p>
            <div class="album-preview search-result-album-preview">
                <?php if (!empty($item['cover_photo_url'])): ?>
                    <?php $album_thumb = $item['thumbnail_path'] ?? $item['cover_photo_url']; ?>
                    <img src="<?= UPLOAD_URL . htmlspecialchars($album_thumb) ?>"
                        class="search-result-album-thumb">
                <?php endif; ?>
                <div class="search-result-album-placeholder">
                    <i class="fas fa-images"></i>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <div class="post-footer search-result-footer">
        <a href="<?= BASE_URL ?>index.php#feed-item-<?= (int)$item['feed_item_id'] ?>"
            class="btn btn-sm btn-secondary search-result-btn">
            Ver no Feed
        </a>

        <?php if ($item['is_paid']): ?>
            <a href="<?= BASE_URL ?>checkout.php?type=<?= htmlspecialchars($item['item_type']) ?>&id=<?= (int)$item['item_id'] ?>"
                class="btn btn-sm btn-primary search-result-btn">
                Comprar Acesso
            </a>
        <?php elseif ($item['item_type'] === 'album'): ?>
            <a href="<?= BASE_URL ?>view_album.php?id=<?= (int)$item['item_id'] ?>"
                class="btn btn-sm btn-primary search-result-btn">
                Ver Álbum
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>post.php?id=<?= (int)$item['feed_item_id'] ?>"
                class="btn btn-sm btn-primary search-result-btn">
                Ver Detalhes
            </a>
        <?php endif; ?>
    </div>
</article>
