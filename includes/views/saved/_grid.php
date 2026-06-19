<?php
use Massango\Services\SavedService;

/**
 * @var array  $items
 * @var array  $ai_map
 * @var bool   $is_admin
 */
?>
<!-- ── Grid de guardados ── -->
<div class="saved-grid" id="savedGrid">
    <?php foreach ($items as $item):
        $thumb   = SavedService::itemThumb($item);
        $url     = SavedService::itemUrl($item);
        $icon    = SavedService::typeIcon($item['item_type']);
        $is_paid = SavedService::itemIsPaid($item);
        $price   = SavedService::itemPrice($item);
        $avatar  = !empty($item['profile_picture'])
            ? UPLOAD_URL . htmlspecialchars($item['profile_picture'])
            : BASE_URL . 'assets/images/default_profile.png';

        // Blur por análise AI
        $analysis_type = (new SavedService($pdo))->analysisType($item);
        $ai_key        = $analysis_type . '_' . $item['item_id'];
        $ai_analysis   = $ai_map[$ai_key] ?? null;
        $should_blur   = $ai_analysis
            && $ai_analysis['status'] === 'done'
            && in_array($ai_analysis['risk_level'], ['medium', 'high'], true)
            && !$is_admin;
        $blur_id = 'saved-' . (int)$item['save_id'];
    ?>
        <div class="saved-grid-item"
            id="<?= $blur_id ?>"
            data-save-id="<?= (int)$item['save_id'] ?>"
            data-item-type="<?= htmlspecialchars($item['item_type']) ?>"
            data-item-id="<?= (int)$item['item_id'] ?>">

            <?php if ($thumb): ?>
                <a href="<?= htmlspecialchars($url) ?>"
                    class="<?= $should_blur ? 'media-blur-container' : '' ?>"
                    style="display:block;width:100%;height:100%;">
                    <img class="saved-grid-thumb <?= $should_blur ? 'media-blur' : '' ?>"
                        src="<?= UPLOAD_URL . htmlspecialchars($thumb) ?>"
                        alt=""
                        loading="lazy"
                        onerror="this.style.display='none'">
                    <?php if ($should_blur): ?>
                        <div class="media-overlay-msg">
                            <i class="fas fa-eye-slash"></i>
                            <p>Conteúdo Sensível<br><small>Detetado pela IA</small></p>
                            <button onclick="event.preventDefault();event.stopPropagation();
                                unblurSaved('<?= $blur_id ?>')">
                                Ver mesmo assim
                            </button>
                        </div>
                    <?php endif; ?>
                </a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($url) ?>">
                    <div class="saved-grid-placeholder">
                        <i class="fa-solid <?= $icon ?>"></i>
                        <span><?= htmlspecialchars(mb_substr(
                            $item['album_name']
                                ?: $item['video_caption']
                                ?: $item['post_content']
                                ?: '',
                            0, 60
                        )) ?></span>
                    </div>
                </a>
            <?php endif; ?>

            <div class="saved-type-badge">
                <i class="fa-solid <?= $icon ?>"></i>
            </div>

            <?php if ($is_paid && $price > 0): ?>
                <div class="saved-price-badge">
                    <?= number_format($price, 0) ?> MT
                </div>
            <?php endif; ?>

            <div class="saved-item-overlay">
                <div class="saved-item-meta">
                    <img src="<?= $avatar ?>" alt="" loading="lazy">
                    <span>@<?= htmlspecialchars($item['username'] ?? '') ?></span>
                </div>
            </div>

            <button class="saved-unsave-btn"
                title="Remover dos guardados"
                onclick="unsaveItem(this, <?= (int)$item['item_id'] ?>, '<?= htmlspecialchars($item['item_type']) ?>')">
                <i class="fa-solid fa-bookmark-slash"></i>
            </button>
        </div>
    <?php endforeach; ?>
</div>
