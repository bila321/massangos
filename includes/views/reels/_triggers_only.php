<?php
/**
 * Partial: _triggers_only.php
 *
 * Versão mínima dos triggers de reel — existe SÓ para alimentar
 * ReelsManager.buildItemData() via querySelectorAll('.lightbox-trigger'),
 * sem nenhum recurso real (imagem/vídeo) a ser pedido ao servidor.
 *
 * Diferente de _card.php (usado no grid visual): aqui não há
 * <img src="...">, nem <video poster="...">, nem CSS de card visível.
 * Tudo o que buildItemData() precisa está em atributos data-* — que
 * são só texto, sem custo de rede.
 *
 * Usado por reels.php quando $direct_lightbox é true (ver _scripts.php
 * para o trigger automático de abertura).
 *
 *   @var array $reels  view-model dos reels, já enriquecido pelo ReelsController::load()
 */
?>
<div id="reels-triggers" style="display:none;" aria-hidden="true">
    <?php foreach ($reels as $reel):
        $feed_id    = (int) $reel['feed_item_id'];
        $duration_s = (int) $reel['duration_seconds'];
    ?>
        <!-- feed-item-wrapper ENVOLVE o trigger de propósito: buildItemData()
             faz trigger.closest('article, .post-card, .feed-item-wrapper')
             para encontrar o "card" e ler .post-author/.post-text dele.
             closest() procura ANCESTRAIS, por isso a ordem aqui importa. -->
        <div class="feed-item-wrapper" style="display:none;">
            <a href="<?= BASE_URL ?>profile.php?id=<?= (int) $reel['user_id'] ?>"
                class="post-author"><?= htmlspecialchars($reel['username']) ?></a>
            <div class="post-content">
                <p class="post-text"><?= htmlspecialchars($reel['caption'] ?? '') ?></p>
            </div>

            <div class="lightbox-trigger"
                data-type="video"
                data-id="<?= $feed_id ?>"
                data-item-id="<?= (int) $reel['id'] ?>"
                data-item-type="video"
                data-src="<?= htmlspecialchars($reel['video_url']) ?>"
                data-has-access="<?= $reel['has_access'] ? 'true' : 'false' ?>"
                data-is-for-sale="<?= $reel['is_paid'] ? 'true' : 'false' ?>"
                data-price="<?= $reel['price'] ?? 0 ?>"
                data-thumbnail="<?= htmlspecialchars($reel['thumbnail_url']) ?>"
                data-feed-item-id="<?= $feed_id ?>"
                data-duration="<?= $duration_s ?>"
                data-author-id="<?= (int) $reel['user_id'] ?>"
                data-views-count="<?= (int) $reel['views_count'] ?>"
                data-shares-count="<?= (int) $reel['shares_count'] ?>"
                data-video-width="<?= (int) $reel['video_width'] ?>"
                data-video-height="<?= (int) $reel['video_height'] ?>"
                data-is-post-owner="<?= $reel['is_post_owner'] ? 'true' : 'false' ?>"
                data-checkout-url="<?= htmlspecialchars($reel['checkout_url']) ?>"
                data-is-verified="<?= $reel['is_verified_creator'] ? 'true' : 'false' ?>"
                data-ai-status="<?= htmlspecialchars($reel['ai_status']) ?>"
                data-ai-risk="<?= htmlspecialchars($reel['ai_risk']) ?>"
                data-ai-score="<?= htmlspecialchars((string) $reel['ai_score']) ?>"
                data-adult="<?= $reel['is_sensitive'] ? 'true' : 'false' ?>">
            </div>
        </div>
    <?php endforeach; ?>
</div>
