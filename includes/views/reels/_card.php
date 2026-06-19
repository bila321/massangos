<?php
/**
 * Partial: um único card de Reel.
 *
 * Requerido de dentro do foreach em _grid.php.
 * Não contém nenhuma decisão de negócio: lê apenas os campos que o
 * Controller já calculou (has_access, is_paid, should_blur, *_url, etc.)
 * e aplica htmlspecialchars() no ponto de saída.
 *
 *   @var array $reel  view-model de um reel, já enriquecido pelo ReelsController::load()
 */

$hasAccess         = $reel['has_access'];
$is_paid           = $reel['is_paid'];
$is_sensitive      = $reel['is_sensitive'];
$should_blur       = $reel['should_blur'];
$isVerifiedCreator = $reel['is_verified_creator'];

$feed_id    = (int)$reel['feed_item_id'];
$duration_s = (int)$reel['duration_seconds'];

$profile_pic   = htmlspecialchars($reel['profile_pic_url']);
$author        = htmlspecialchars($reel['username']);
$thumbnail_url = htmlspecialchars($reel['thumbnail_url']);
$video_url     = htmlspecialchars($reel['video_url']);
$caption       = htmlspecialchars($reel['caption'] ?? '');
$checkout_url  = htmlspecialchars($reel['checkout_url']);
?>

<div class="reel-card post-card <?= $is_sensitive ? 'is-sensitive' : '' ?>"
    data-feed-item-id="<?= $feed_id ?>">

    <div class="reel-video-wrapper">

        <!-- Badges topo -->
        <div class="reel-badges">
            <?php if ($is_sensitive): ?>
                <span class="badge badge-adult">18+</span>
            <?php endif; ?>
            <?php if ($is_paid): ?>
                <span class="badge badge-paid">
                    <i class="fa-solid fa-lock"></i>
                    <?= number_format($reel['price'] ?? 0, 0, ',', '.') ?> MT
                </span>
            <?php endif; ?>
            <?= get_quality_badge($duration_s ?: null) ?>
        </div>

        <!-- Badge duração -->
        <?php if ($duration_s > 0): ?>
            <div class="reel-duration">
                <?= format_duration($duration_s) ?>
            </div>
        <?php endif; ?>

        <?php if ($hasAccess): ?>
            <div class="reel-trigger-wrapper"
                style="width:100%;height:100%;position:absolute;inset:0;">

                <div class="lightbox-trigger"
                    data-type="video"
                    data-id="<?= $feed_id ?>"
                    data-item-id="<?= (int)$reel['id'] ?>"
                    data-item-type="video"
                    data-src="<?= $video_url ?>"
                    data-has-access="true"
                    data-is-for-sale="<?= $is_paid ? 'true' : 'false' ?>"
                    data-price="<?= $reel['price'] ?? 0 ?>"
                    data-thumbnail="<?= $thumbnail_url ?>"
                    data-action="view-request"
                    data-feed-item-id="<?= $feed_id ?>"
                    data-duration="<?= $duration_s ?>"
                    data-author-id="<?= (int)$reel['user_id'] ?>"
                    data-views-count="<?= (int)$reel['views_count'] ?>"
                    data-shares-count="<?= (int)$reel['shares_count'] ?>"
                    data-video-width="<?= (int)$reel['video_width'] ?>"
                    data-video-height="<?= (int)$reel['video_height'] ?>"
                    data-is-post-owner="<?= $reel['is_post_owner'] ? 'true' : 'false' ?>"
                    data-checkout-url="<?= $checkout_url ?>"
                    data-is-verified="<?= $isVerifiedCreator ? 'true' : 'false' ?>"
                    data-ai-status="<?= htmlspecialchars($reel['ai_status']) ?>"
                    data-ai-risk="<?= htmlspecialchars($reel['ai_risk']) ?>"
                    data-ai-score="<?= htmlspecialchars((string)$reel['ai_score']) ?>">

                    <!-- Dados ocultos para o lightbox -->
                    <img src="<?= $profile_pic ?>" class="profile-thumb" style="display:none;">
                    <a href="<?= BASE_URL ?>profile.php?id=<?= (int)$reel['user_id'] ?>"
                        class="post-author" style="display:none;"><?= $author ?></a>
                    <div class="post-content" style="display:none;">
                        <p class="post-text"><?= $caption ?></p>
                    </div>

                    <?php if ($should_blur): ?>
                        <div class="video-blur-wrapper" data-blur-active="true">
                            <video data-src="<?= $video_url ?>"
                                poster="<?= $thumbnail_url ?>"
                                loop muted preload="none"
                                class="reel-video media-blur lazy-video"></video>
                            <div class="media-overlay-msg">
                                <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <p>Conteúdo Sensível<br><small>Detetado automaticamente pela IA</small></p>
                                <button onclick="event.stopPropagation(); unblurReel(this)">Ver mesmo assim</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <video data-src="<?= $video_url ?>"
                            poster="<?= $thumbnail_url ?>"
                            loop muted preload="none"
                            class="reel-video lazy-video"></video>
                    <?php endif; ?>

                    <div class="play-overlay">
                        <i class="fa-solid fa-play"></i>
                    </div>
                </div>

            </div>

        <?php else: ?>
            <div class="video-locked lightbox-trigger"
                data-type="video"
                data-item-type="video"
                data-id="<?= $feed_id ?>"
                data-item-id="<?= (int)$reel['id'] ?>"
                data-is-for-sale="true"
                data-price="<?= $reel['price'] ?? 0 ?>"
                data-has-access="false"
                data-is-post-owner="false"
                data-thumbnail="<?= $thumbnail_url ?>"
                data-src="<?= $video_url ?>"
                data-feed-item-id="<?= $feed_id ?>"
                data-duration="<?= $duration_s ?>"
                data-author-id="<?= (int)$reel['user_id'] ?>"
                data-views-count="<?= (int)$reel['views_count'] ?>"
                data-shares-count="<?= (int)$reel['shares_count'] ?>"
                data-video-width="<?= (int)$reel['video_width'] ?>"
                data-video-height="<?= (int)$reel['video_height'] ?>"
                data-is-verified="<?= $isVerifiedCreator ? 'true' : 'false' ?>"
                data-checkout-url="<?= $checkout_url ?>"
                data-ai-status="<?= htmlspecialchars($reel['ai_status']) ?>"
                data-ai-risk="<?= htmlspecialchars($reel['ai_risk']) ?>"
                data-ai-score="<?= htmlspecialchars((string)$reel['ai_score']) ?>">

                <!-- Dados ocultos para o lightbox -->
                <img src="<?= $profile_pic ?>" class="profile-thumb" style="display:none;">
                <a href="<?= BASE_URL ?>profile.php?id=<?= (int)$reel['user_id'] ?>"
                    class="post-author" style="display:none;"><?= $author ?></a>
                <div class="post-content" style="display:none;">
                    <p class="post-text"><?= $caption ?></p>
                </div>

                <video data-src="<?= $video_url ?>"
                    poster="<?= $thumbnail_url ?>"
                    loop muted preload="none"
                    class="reel-video lazy-video"
                    style="filter: blur(12px);"></video>

                <div class="locked-overlay">
                    <i class="fa-solid fa-lock"></i>
                    <span class="locked-price">
                        <?= number_format($reel['price'] ?? 0, 2, ',', '.') ?> MT
                    </span>
                    <span class="locked-cta">Toque para comprar</span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Info overlay (fundo do card) -->
        <div class="reel-overlay">
            <div class="reel-user">
                <img src="<?= $profile_pic ?>" alt="<?= $author ?>">
                <span>@<?= $author ?></span>
            </div>
            <?php if ($caption): ?>
                <div class="reel-caption"><?= $caption ?></div>
            <?php endif; ?>
        </div>

    </div><!-- /.reel-video-wrapper -->
</div><!-- /.reel-card -->
