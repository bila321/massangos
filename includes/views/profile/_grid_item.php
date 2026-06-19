<?php
/**
 * Partial: um item de grid (view filtrada Fotos / Vídeos / Álbuns).
 * Variáveis disponíveis do foreach em _feed_grid.php:
 *
 * @var array  $item
 * @var array  $content_data
 * @var array  $author
 * @var array  $ai_analysis
 * @var string $display_type      post | video | album
 * @var mixed  $feed_item_id
 * @var bool   $grid_is_paid
 * @var bool   $grid_is_sensitive
 */
$grid_duration_s  = (int)($content_data['duration_seconds'] ?? 0);
$grid_album_thumb = !empty($content_data['thumbnail_path'])
    ? $content_data['thumbnail_path']
    : ($content_data['cover_photo_url'] ?? 'default_album.png');
$grid_img         = !empty($content_data['thumbnail_path'])
    ? $content_data['thumbnail_path']
    : ($content_data['image_path'] ?? 'default_post.png');
$blur_class       = ($grid_is_sensitive || $grid_is_paid) ? 'media-blur' : '';
?>
<div class="grid-item-wrapper"
    data-type="<?= htmlspecialchars($display_type) ?>"
    style="display:none;">

    <div class="profile-grid-item <?= $display_type === 'video' ? 'lightbox-trigger video-item grid-trigger' : 'grid-trigger' ?>"
        data-id="<?= htmlspecialchars((string)($feed_item_id ?? $item['item_id'])) ?>"
        data-post-modal="<?= htmlspecialchars((string)($feed_item_id ?? $item['item_id'])) ?>"
        data-type="<?= $display_type ?>"
        data-item-type="<?= $display_type ?>"
        data-src="<?= $display_type === 'video' ? UPLOAD_URL . htmlspecialchars($content_data['video_path'] ?? '') : '' ?>"
        data-thumbnail="<?= !empty($content_data['thumbnail_path']) ? UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path']) : '' ?>"
        data-duration="<?= $grid_duration_s ?>"
        data-is-for-sale="<?= !empty($content_data['is_for_sale']) ? 'true' : 'false' ?>"
        data-has-access="<?= !empty($item['has_access']) ? 'true' : 'false' ?>"
        data-is-post-owner="<?= !empty($item['is_post_owner']) ? 'true' : 'false' ?>"
        data-price="<?= (float)($content_data['price'] ?? 0) ?>"
        data-author-id="<?= (int)($author['id'] ?? 0) ?>"
        data-views-count="<?= (int)($content_data['views_count'] ?? 0) ?>"
        data-ai-status="<?= htmlspecialchars($ai_analysis['status'] ?? '') ?>"
        data-ai-risk="<?= htmlspecialchars($ai_analysis['risk_level'] ?? 'low') ?>"
        style="position:relative;overflow:hidden;">

        <?php if ($display_type === 'video'): ?>

            <?php if (!empty($content_data['thumbnail_path'])): ?>
                <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path']) ?>"
                    alt="thumbnail"
                    class="post-video <?= $blur_class ?>"
                    style="width:100%;height:100%;object-fit:cover;display:block;">
            <?php else: ?>
                <video src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
                    muted playsinline preload="metadata"
                    class="post-video <?= $blur_class ?>">
                </video>
            <?php endif; ?>

            <div class="reel-badges">
                <?php if ($grid_is_sensitive): ?>
                    <span class="badge badge-adult">18+</span>
                <?php endif; ?>
                <?php if ($grid_is_paid): ?>
                    <span class="badge badge-paid">
                        <i class="fa-solid fa-lock"></i>
                        <?= number_format($content_data['price'] ?? 0, 0, ',', '.') ?> MT
                    </span>
                <?php endif; ?>
                <?= get_quality_badge($grid_duration_s ?: null) ?>
            </div>

            <?php if ($grid_duration_s > 0): ?>
                <div class="reel-duration"><?= format_duration($grid_duration_s) ?></div>
            <?php endif; ?>

            <div class="grid-item-overlay"><i class="fas fa-play"></i></div>

        <?php elseif ($display_type === 'album'): ?>

            <img src="<?= UPLOAD_URL . htmlspecialchars($grid_album_thumb) ?>"
                alt="Álbum"
                class="grid-image <?= $blur_class ?>">
            <div class="grid-item-overlay"><i class="fas fa-images"></i></div>

        <?php else: /* post */ ?>

            <img src="<?= UPLOAD_URL . htmlspecialchars($grid_img) ?>"
                alt="Post"
                class="grid-image <?= $blur_class ?>">

        <?php endif; ?>

        <!-- ── Overlays de acesso ──────────────────────────────── -->
        <?php if ($grid_is_paid && !$item['has_access']): ?>
            <div class="grid-explicit-blur-overlay"
                onclick="event.stopPropagation(); pageModalLoader.open('checkout.php?type=<?= $display_type ?>&id=<?= $item['item_id'] ?>')">
                <i class="fas fa-lock" style="font-size:1.4rem;margin-bottom:6px;"></i>
                <span style="font-size:0.7rem;"><?= number_format($content_data['price'] ?? 0, 0, ',', '.') ?> MT</span>
            </div>
        <?php elseif ($grid_is_sensitive): ?>
            <div class="grid-blur-overlay"
                onclick="event.stopPropagation(); unblurGridItem(this)"
                style="position:absolute;inset:0;background:rgba(0,0,0,0.45);
                        display:flex;flex-direction:column;align-items:center;
                        justify-content:center;color:#fff;font-size:0.7rem;gap:4px;
                        cursor:pointer;z-index:10;">
                <i class="fas fa-eye-slash" style="font-size:1.2rem;"></i>
                <span>Ver mesmo assim</span>
            </div>
        <?php elseif (!$item['has_access']): ?>
            <div class="grid-explicit-blur-overlay">
                <i class="fas fa-lock"></i>
            </div>
        <?php endif; ?>

    </div>
</div>
