<?php
/**
 * Partial: _video-media.php
 * Renderiza o media de um item do tipo "video".
 *
 * Variáveis esperadas no scope:
 *   $item                 – array completo do feed item
 *   $content_data         – dados do vídeo (video_path, thumbnail_path, caption, is_for_sale, price, duration, views_count)
 *   $should_blur          – bool  (conteúdo sensível detetado pela IA)
 *   $ai_analysis          – array|null
 *   $logged_in_user_data  – array do utilizador autenticado (is_verified_creator)
 *
 * $item['has_access'] já vem calculado pelo FeedController.
 */

if (empty($content_data['video_path'])) {
    return; // sem media para renderizar
}

$hasAccess         = $item['has_access'];
$isVerifiedCreator = !empty($logged_in_user_data['is_verified_creator']);
?>

<!-- Caption / legenda (opcional) -->
<?php if (!empty($content_data['caption'])): ?>
    <div class="post-content">
        <p class="post-text"><?= nl2br(htmlspecialchars($content_data['caption'])) ?></p>
    </div>
<?php endif; ?>

<!-- Badge de conteúdo pago -->
<?php if (!empty($content_data['is_for_sale'])): ?>
    <div class="paid-content-badge"
         style="background: var(--primary-gradient); color: #3b3b3b; padding: 5px 10px;
                border-radius: 5px; font-size: 0.8em; font-weight: bold;
                display: inline-block; margin-bottom: 10px; margin-left: 10px;">
        <i class="fa-regular fa-lock"></i> CONTEÚDO PAGO: <?= number_format($content_data['price'], 2, ',', '.') ?> MT
    </div>
<?php endif; ?>

<?php if ($hasAccess): ?>
    <!-- ── ACESSO LIVRE / DESBLOQUEADO ── -->
    <div class="media-wrapper-<?= htmlspecialchars($item['feed_item_id']) ?> <?= $should_blur ? 'media-blur-container' : '' ?>">
        <div class="post-video lightbox-trigger"
             data-type="video"
             data-id="<?= htmlspecialchars($item['feed_item_id']) ?>"
             data-item-id="<?= htmlspecialchars($item['item_id']) ?>"
             data-item-type="video"
             data-src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
             data-is-for-sale="<?= !empty($content_data['is_for_sale']) ? 'true' : 'false' ?>"
             data-price="<?= $content_data['price'] ?? 0 ?>"
             data-has-access="true"
             data-thumbnail="<?= htmlspecialchars(get_video_thumb_url($content_data['thumbnail_path'] ?? '')) ?>"
             data-ai-status="<?= htmlspecialchars($ai_analysis['status'] ?? '') ?>"
             data-ai-risk="<?= htmlspecialchars($ai_analysis['risk_level'] ?? '') ?>"
             data-ai-score="<?= htmlspecialchars($ai_analysis['explicit_percentage'] ?? 0) ?>"
             onclick="if(typeof sendViewRequest === 'function') sendViewRequest('video', <?= (int)$item['item_id'] ?>, <?= (int)$item['feed_item_id'] ?>)"
             style="position: relative; overflow: hidden; cursor: pointer;">

            <?php if (!empty($content_data['thumbnail_path'])): ?>
                <img src="<?= htmlspecialchars(get_video_thumb_url($content_data['thumbnail_path'])) ?>"
                     class="post-video <?= $should_blur ? 'media-blur' : '' ?>"
                     style="display: block; width: 100%;">
            <?php else: ?>
                <video src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
                       class="post-image <?= $should_blur ? 'media-blur' : '' ?>"
                       style="width: 100%; display: block;"
                       preload="metadata"
                       data-item-type="video"
                       data-item-id="<?= (int)$item['item_id'] ?>"
                       muted
                       playsinline></video>
            <?php endif; ?>

            <?php if ($should_blur): ?>
                <div class="media-overlay-msg">
                    <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <p>Conteúdo Sensível<br><small>Detetado automaticamente pela IA</small></p>
                    <button onclick="event.stopPropagation(); unblurMedia('<?= htmlspecialchars($item['feed_item_id']) ?>')">
                        Ver mesmo assim
                    </button>
                </div>
            <?php endif; ?>

            <!-- Overlay de Play -->
            <div class="video-play-overlay"
                 style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
                        color: white; font-size: 3rem; opacity: 0.9; pointer-events: none;
                        background: rgba(0,0,0,0.3); border-radius: 50%; width: 80px; height: 80px;
                        display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-play" style="margin-left: 8px;"></i>
            </div>

            <!-- Stats de visualizações -->
            <div class="video-stats"
                 style="position: absolute; bottom: 0; left: 0; width: 100%; padding: 12px 8px;
                        color: white; font-size: 0.9rem; pointer-events: none;
                        background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.4) 50%, transparent 100%);
                        z-index: 10; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-eye"></i>
                <span data-views-id="video-<?= (int)$item['item_id'] ?>">
                    <?= number_format($content_data['views_count'] ?? 0) ?> visualizacoes
                </span>
                <?php if (empty($content_data['is_for_sale'])): ?>
                    <span style="margin-left: auto; z-index: 111000; background: rgba(0,255,0,0.3);
                                 padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;">
                        <i class="fa-solid fa-play-circle"></i> Grátis
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- /.media-wrapper -->

<?php else: ?>
    <!-- ── BLOQUEADO (conteúdo pago) ── -->
    <?php $checkoutUrl = BASE_URL . 'checkout.php?type=video&id=' . $item['item_id']; ?>

    <div class="lightbox-trigger video-locked"
         data-type="video"
         data-id="<?= htmlspecialchars($item['feed_item_id']) ?>"
         data-item-id="<?= htmlspecialchars($item['item_id']) ?>"
         data-item-type="video"
         data-src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
         data-is-for-sale="true"
         data-price="<?= $content_data['price'] ?? 0 ?>"
         data-has-access="false"
         data-thumbnail="<?= htmlspecialchars(get_video_thumb_url($content_data['thumbnail_path'] ?? '')) ?>"
         data-duration="<?= $content_data['duration'] ?? 248 ?>"
         data-is-verified="<?= $isVerifiedCreator ? 'true' : 'false' ?>"
         data-checkout-url="<?= htmlspecialchars($checkoutUrl) ?>"
         style="cursor: pointer; position: relative;">

        <img src="<?= htmlspecialchars(get_video_thumb_url($content_data['thumbnail_path'] ?? '')) ?>"
             class="album-cover-image"
             style="filter: blur(10px); width: 100%; display: block;"
             data-is-paid="true">

        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(0,0,0,0.7); display: flex; flex-direction: column;
                    align-items: center; justify-content: center; color: #fff; pointer-events: none;">
            <span>
                <i class="fa-solid fa-play"  style="margin-bottom: 10px; padding: 5px;"></i>
                <i class="fas fa-lock"       style="margin-bottom: 10px; padding: 5px;"></i>
            </span>
            <div style="margin-top: 8px; color: #65676b;">
                <i class="fa-solid fa-eye" style="color: #65676b; font-size: 1rem; margin-left: 10px;"></i>
                <?= number_format($content_data['views_count'] ?? 0) ?> visualizações
            </div>
            <p>Vídeo Pago: <?= number_format($content_data['price'], 2, ',', '.') ?> MT</p>
            <span style="font-size: 0.8em; text-decoration: underline;">
                <?= $isVerifiedCreator ? 'Clique para comprar' : 'Verifique sua conta para comprar' ?>
            </span>
        </div>
    </div>

<?php endif; ?>
