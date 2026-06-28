<?php
use Massango\Services\SavedService;

/**
 * @var array  $items
 * @var array  $ai_map
 * @var bool   $is_admin
 */

/**
 * FIX v2: Helper defensivo para encontrar a URL do vídeo no $item.
 *
 * O SavedService pode retornar a URL do vídeo sob diferentes nomes de
 * campo dependendo do item_type (video/reel) e do schema do DB.
 * Este helper tenta todos os nomes comuns antes de desistir.
 *
 * Se encontrar, retorna a string. Se não, retorna ''.
 */
$saved_findVideoUrl = function (array $item): string {
    $candidates = [
        // Padrão mais comum
        'video_url',
        'video_file',
        'video_path',
        'video_source',
        'video',
        // Genéricos
        'url',
        'file_url',
        'file_path',
        'file',
        'filename',
        'media_url',
        'media_path',
        'media',
        'source',
        'path',
        // Reel-specific
        'reel_url',
        'reel_file',
        'reel_video',
        // Outros
        'src',
        'video_src',
        'video_link',
        'content_url',
        'media_file',
    ];
    foreach ($candidates as $key) {
        if (!empty($item[$key]) && is_string($item[$key])) {
            return $item[$key];
        }
    }
    return '';
};

/**
 * FIX v3: Helper para normalizar URL de vídeo → absoluta.
 *
 * Lógica:
 *   - URLs já absolutas (http://, https://, //, data:) → devolve tal como está.
 *   - Caminhos que começam com "videos/", "uploads/videos/", "reels/" →
 *     prefixa com UPLOAD_URL (ex: /uploads/) em vez de BASE_URL.
 *     Isto resolve o bug onde o DB retorna "videos/video_xxx.mp4" e
 *     o código gerava "/videos/video_xxx.mp4" em vez de "/uploads/videos/video_xxx.mp4".
 *   - Outros caminhos relativos → prefixa com BASE_URL.
 */
$saved_normalizeVideoUrl = function (string $raw): string {
    if ($raw === '') return '';
    if (preg_match('#^https?://#i', $raw) || strpos($raw, '//') === 0 || strpos($raw, 'data:') === 0) {
        return $raw;
    }

    $raw = ltrim($raw, '/');

    // Detectar caminhos de vídeo e aplicar UPLOAD_URL
    $uploadPrefixes = ['videos/', 'reels/', 'uploads/videos/', 'uploads/reels/'];
    foreach ($uploadPrefixes as $prefix) {
        if (strpos($raw, $prefix) === 0) {
            // Se já tem "uploads/" no início, não duplicar
            if (strpos($raw, 'uploads/') === 0) {
                return BASE_URL . $raw;
            }
            // Caso comum: DB retorna "videos/video_xxx.mp4"
            // → prefixar com UPLOAD_URL (geralmente "/uploads/")
            $uploadUrl = defined('UPLOAD_URL') ? UPLOAD_URL : '/uploads/';
            return $uploadUrl . $raw;
        }
    }

    // Fallback: prefixar com BASE_URL
    return BASE_URL . $raw;
};

/**
 * FIX v2: Helper genérico para normalizar URL → absoluta.
 * - URLs já absolutas (http://, https://, //, data:) → devolve tal como está.
 * - Caminhos relativos → prefixa com BASE_URL.
 */
$saved_normalizeUrl = function (string $raw): string {
    if ($raw === '') return '';
    if (preg_match('#^https?://#i', $raw) || strpos($raw, '//') === 0 || strpos($raw, 'data:') === 0) {
        return $raw;
    }
    return BASE_URL . ltrim($raw, '/');
};

// ============================================================
// FIX v2: DEBUG — imprimir as chaves do primeiro item como
// comentário HTML, para conseguirmos ver que campos o
// SavedService está a retornar. Visível em "Ver código-fonte"
// (Ctrl+U) — não aparece na UI.
// ============================================================
if (!empty($items)) {
    $firstItem = $items[0];
    echo "\n<!-- [SAVED DEBUG] chaves disponiveis no \$item: "
        . htmlspecialchars(implode(', ', array_keys($firstItem)))
        . " -->\n";
    // Mostrar valores não-vazios relevantes para URL de vídeo
    $debugFields = ['video_url', 'video_file', 'video_path', 'url', 'file_url', 'file_path', 'media_url', 'source', 'item_type', 'item_id', 'video_caption'];
    $debugValues = [];
    foreach ($debugFields as $f) {
        if (isset($firstItem[$f]) && $firstItem[$f] !== '') {
            $val = is_string($firstItem[$f]) ? $firstItem[$f] : json_encode($firstItem[$f]);
            $debugValues[] = "$f=" . substr($val, 0, 80);
        }
    }
    echo "<!-- [SAVED DEBUG] valores: " . htmlspecialchars(implode(' | ', $debugValues)) . " -->\n";
}
?>

<!-- ── Grid de guardados ── -->
<div class="saved-grid" id="savedGrid">
    <?php foreach ($items as $item):
        $thumb = SavedService::itemThumb($item);
        $url = SavedService::itemUrl($item);
        $icon = SavedService::typeIcon($item['item_type']);
        $is_paid = SavedService::itemIsPaid($item);
        $price = SavedService::itemPrice($item);
        $avatar = !empty($item['profile_picture'])
            ? UPLOAD_URL . htmlspecialchars($item['profile_picture'])
            : BASE_URL . 'assets/images/default_profile.png';

        // Blur por análise AI
        $analysis_type = (new SavedService($pdo))->analysisType($item);
        $ai_key = $analysis_type . '_' . $item['item_id'];
        $ai_analysis = $ai_map[$ai_key] ?? null;
        $should_blur = $ai_analysis
            && $ai_analysis['status'] === 'done'
            && in_array($ai_analysis['risk_level'], ['medium', 'high'], true)
            && !$is_admin;
        $blur_id = 'saved-' . (int) $item['save_id'];

        $is_video = in_array($item['item_type'], ['video', 'reel']);

        // ============================================================
        // FIX v3: Usar helper específico para vídeos que detecta
        // caminhos como "videos/x.mp4" e prefixa com UPLOAD_URL.
        // ============================================================
        $raw_video_url = SavedService::itemVideoUrl($item);
        $video_url_abs = $saved_normalizeVideoUrl($raw_video_url);
        $video_duration = SavedService::itemDuration($item);
        $video_views    = SavedService::itemViews($item);
        [$video_width, $video_height] = SavedService::itemVideoDimensions($item);

        // Thumbnail absoluta para o poster do vídeo no lightbox
        $thumb_abs = $thumb ? UPLOAD_URL . htmlspecialchars($thumb) : '';

        // Atributos de venda/acesso (lidos pelo premium_lightbox.js)
        $is_for_sale_attr = $is_paid ? 'true' : 'false';
        $has_access_attr  = $is_paid ? 'false' : 'true'; // se é pago e ainda não comprou, bloqueado
        $price_attr       = (float) $price;
        $checkout_url_attr = BASE_URL . 'checkout.php?type=video&id=' . (int) $item['item_id'];

        // ============================================================
        // FIX v3: Atributos de análise AI (NudeNet) para o blur de
        // conteúdos sensíveis funcionar no lightbox.
        //
        // O premium_lightbox.js lê:
        //   - trigger.dataset.aiStatus  ('done' se análise concluída)
        //   - trigger.dataset.aiRisk    ('low' | 'medium' | 'high')
        //   - trigger.dataset.aiScore   (0-100)
        //   - trigger.dataset.aiUnlocked ('true' se já desbloqueado no feed)
        //
        // Sem estes atributos, isSensitive é sempre false → o blur
        // nunca aparece no lightbox, mesmo para conteúdos marcados.
        // ============================================================
        $ai_status_attr    = $ai_analysis['status']     ?? '';
        $ai_risk_attr      = $ai_analysis['risk_level'] ?? 'low';
        $ai_score_attr     = (float)($ai_analysis['explicit_percentage'] ?? 0);
        // aiUnlocked: se $should_blur for true, significa que ainda está bloqueado no feed
        $ai_unlocked_attr  = $should_blur ? 'false' : 'true';

        // FIX v2: comentário de debug por item (visível no código-fonte)
        if ($is_video && $video_url_abs === '') {
            echo "<!-- [SAVED DEBUG] item_id={$item['item_id']} tipo={$item['item_type']} SEM URL de vídeo. Chaves: "
                . htmlspecialchars(implode(',', array_keys($item)))
                . " -->\n";
        }
        ?>
        <div class="saved-grid-item" id="<?= $blur_id ?>" data-save-id="<?= (int) $item['save_id'] ?>"
            data-item-type="<?= htmlspecialchars($item['item_type']) ?>" data-item-id="<?= (int) $item['item_id'] ?>">

            <?php if ($thumb): ?>
                <?php if ($is_video): ?>
                    <?php // ============================================================
                          // FIX: Atributos data-* completos para o premium_lightbox.js
                          // conseguir construir o reel sem depender de fallbacks.
                          // - data-src: URL absoluta do vídeo (validada por isVideoFile)
                          // - data-video-url: mesma URL, para fallback do buildItemData
                          // - data-thumbnail: poster do vídeo
                          // - data-is-for-sale / data-has-access: controlo de bloqueio
                          // - data-price / data-checkout-url: botão de compra
                          // - data-item-type / data-type: ambos "video"
                          // - data-feed-item-id / data-id: ID do item
                          // ============================================================ ?>
                    <a href="javascript:void(0)" class="premium-lightbox-trigger lightbox-trigger"
                        data-id="<?= (int) $item['item_id'] ?>"
                        data-feed-item-id="<?= (int) $item['item_id'] ?>"
                        data-item-id="<?= (int) $item['item_id'] ?>"
                        data-item-type="video"
                        data-type="video"
                        data-src="<?= htmlspecialchars($video_url_abs) ?>"
                        data-video-url="<?= htmlspecialchars($video_url_abs) ?>"
                        data-thumbnail="<?= htmlspecialchars($thumb_abs) ?>"
                        data-is-for-sale="<?= $is_for_sale_attr ?>"
                        data-has-access="<?= $has_access_attr ?>"
                        data-price="<?= $price_attr ?>"
                        data-checkout-url="<?= htmlspecialchars($checkout_url_attr) ?>"
                        data-author-id="<?= (int) ($item['author_id'] ?? 0) ?>"
                        data-author-name="<?= htmlspecialchars($item['username'] ?? '') ?>"
                        data-views-count="<?= (int) $video_views ?>"
                        data-duration="<?= (int) $video_duration ?>"
                        data-video-width="<?= (int) $video_width ?>"
                        data-video-height="<?= (int) $video_height ?>"
                        data-caption="<?= htmlspecialchars($item['video_caption'] ?? $item['post_content'] ?? '') ?>"
                        data-ai-status="<?= htmlspecialchars($ai_status_attr) ?>"
                        data-ai-risk="<?= htmlspecialchars($ai_risk_attr) ?>"
                        data-ai-score="<?= $ai_score_attr ?>"
                        data-ai-unlocked="<?= $ai_unlocked_attr ?>"
                        data-is-post-owner="<?= ($item['author_id'] ?? 0) === ($_SESSION['user_id'] ?? 0) ? 'true' : 'false' ?>"
                        style="display:block; width:100%; height:100%;">

                        <img class="saved-grid-thumb <?= $should_blur ? 'media-blur' : '' ?>"
                            src="<?= htmlspecialchars($thumb_abs) ?>" alt="" loading="lazy">
                        <!-- blur mantido igual -->
                    </a>
                <?php else: ?>
                    <!-- Outros conteúdos -->
                    <a href="<?= htmlspecialchars($url) ?>" class="<?= $should_blur ? 'media-blur-container' : '' ?>"
                        style="display:block;width:100%;height:100%;">
                        <img class="saved-grid-thumb <?= $should_blur ? 'media-blur' : '' ?>"
                            src="<?= htmlspecialchars($thumb_abs) ?>" alt="" loading="lazy"
                            onerror="this.style.display='none'">
                        <?php if ($should_blur): ?>
                            <div class="media-overlay-msg">... (igual) ...</div>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <!-- Placeholder -->
                <a href="<?= htmlspecialchars($url) ?>">
                    <div class="saved-grid-placeholder">
                        <i class="fa-solid <?= $icon ?>"></i>
                        <span><?= htmlspecialchars(mb_substr($item['album_name'] ?: $item['video_caption'] ?: $item['post_content'] ?: '', 0, 60)) ?></span>
                    </div>
                </a>
            <?php endif; ?>

            <!-- resto do arquivo (badges, overlay, unsave) permanece igual -->
            <div class="saved-type-badge">
                <i class="fa-solid <?= $icon ?>"></i>
            </div>

            <?php if ($is_paid && $price > 0): ?>
                <div class="saved-price-badge"><?= number_format($price, 0) ?> MT</div>
            <?php endif; ?>

            <div class="saved-item-overlay">
                <div class="saved-item-meta">
                    <img src="<?= $avatar ?>" alt="" loading="lazy">
                    <span>@<?= htmlspecialchars($item['username'] ?? '') ?></span>
                </div>
            </div>

            <button class="saved-unsave-btn" title="Remover dos guardados"
                onclick="unsaveItem(this, <?= (int) $item['item_id'] ?>, '<?= htmlspecialchars($item['item_type']) ?>')">
                <i class="fa-solid fa-bookmark-slash"></i>
            </button>
        </div>
    <?php endforeach; ?>
</div>
