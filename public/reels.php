<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/adult-content-helper.php';
SecurityManager::initSecurity();
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Controllers\ReelsController;
use Massango\Models\User;

// ← ADICIONAR ESTAS DUAS LINHAS ANTES DO HEADER
$hide_feed_container = true;   // reels usa layout próprio, sem .feed-container
$hide_sidebar        = false;  // sidebar mantém-se

$data = (new ReelsController($pdo))->load($_GET);
extract($data);

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/premium_lightbox.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/reels.css">

<script src="<?= BASE_URL ?>assets/js/components/description-truncate.js" defer></script>

<div class="reels-page">

    <div class="reels-filter-bar">

        <form id="filterForm" method="get" action="reels.php">

            <!-- Campos ocultos para chips (sale + sensitive) -->
            <input type="hidden" name="sale" id="input_sale" value="<?= htmlspecialchars($filter_sale) ?>">
            <input type="hidden" name="sensitive" id="input_sensitive" value="<?= htmlspecialchars($filter_sensitive) ?>">

            <!-- ── Linha sempre visível: pesquisa + botão filtros (mobile) ── -->
            <div class="filters-top-row">

                <div class="filter-search">
                    <i class="fa fa-search"></i>
                    <input type="text" name="q"
                        placeholder="Pesquisar reels…"
                        value="<?= htmlspecialchars($filter_search) ?>">
                </div>

                <!-- Botão toggle — só aparece em mobile via CSS -->
                <button type="button" class="btn-filters-toggle" id="btnFiltersToggle"
                    aria-expanded="false" aria-controls="filtersPanel">
                    <i class="fa-solid fa-sliders icon-sliders"></i>
                    Filtros
                    <span class="filters-badge" id="filtersBadge"></span>
                </button>

            </div><!-- /.filters-top-row -->

            <!-- ── Painel colapsável (desktop: sempre visível; mobile: toggle) ── -->
            <div class="filters-panel" id="filtersPanel">
                <div class="filters-panel-inner">

                    <!-- Chips -->
                    <div class="filter-chips">
                        <span class="chip <?= $active_chip === '' ? 'active' : '' ?>"
                            data-chip="all" onclick="setChip('')">
                            <i class="fa fa-th"></i> Todos
                        </span>
                        <span class="chip <?= $active_chip === 'free' ? 'active' : '' ?>"
                            data-chip="free" onclick="setChip('free')">
                            <i class="fa fa-unlock"></i> Gratuitos
                        </span>
                        <span class="chip <?= $active_chip === 'paid' ? 'active' : '' ?>"
                            data-chip="paid" onclick="setChip('paid')">
                            <i class="fa fa-lock"></i> Pagos
                        </span>
                        <span class="chip adult <?= $active_chip === 'adult' ? 'active' : '' ?>"
                            data-chip="adult" onclick="setChip('adult')">
                            <i class="fa fa-fire"></i> +18
                        </span>
                    </div>

                    <!-- Selects + Preço -->
                    <div class="filters-row-secondary">

                        <div class="filter-group">
                            <label><i class="fa fa-sort"></i> Ordenar</label>
                            <div class="filter-select-wrapper">
                                <select name="sort">
                                    <option value="recent" <?= $filter_sort === 'recent'     ? 'selected' : '' ?>>Recentes</option>
                                    <option value="popular" <?= $filter_sort === 'popular'    ? 'selected' : '' ?>>Populares</option>
                                    <option value="price_asc" <?= $filter_sort === 'price_asc'  ? 'selected' : '' ?>>Preço ↑</option>
                                    <option value="price_desc" <?= $filter_sort === 'price_desc' ? 'selected' : '' ?>>Preço ↓</option>
                                </select>
                            </div>
                        </div>

                        <div class="filter-group">
                            <label><i class="fa fa-film"></i> Qualidade</label>
                            <div class="filter-select-wrapper">
                                <select name="quality">
                                    <option value="">Todas</option>
                                    <option value="sd" <?= $filter_quality === 'sd'  ? 'selected' : '' ?>>SD</option>
                                    <option value="hd" <?= $filter_quality === 'hd'  ? 'selected' : '' ?>>HD</option>
                                    <option value="fhd" <?= $filter_quality === 'fhd' ? 'selected' : '' ?>>Full HD</option>
                                </select>
                            </div>
                        </div>

                        <div class="filter-group">
                            <label><i class="fa fa-tag"></i> Preço</label>
                            <div class="price-range-inputs">
                                <input type="number" name="price_min" placeholder="Min"
                                    value="<?= htmlspecialchars((string)$filter_price_min) ?>" min="0">
                                <span class="price-sep">–</span>
                                <input type="number" name="price_max" placeholder="Max"
                                    value="<?= htmlspecialchars((string)$filter_price_max) ?>" min="0">
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn-apply">
                                <i class="fa fa-check"></i> Aplicar
                            </button>
                            <a href="reels.php" class="btn-reset" title="Limpar filtros">
                                <i class="fa fa-rotate-left"></i>
                            </a>
                        </div>

                    </div><!-- /.filters-row-secondary -->

                    <span class="results-count">
                        <?= number_format(count($reels)) ?> reels encontrados
                    </span>

                </div><!-- /.filters-panel-inner -->
            </div><!-- /.filters-panel -->

        </form>

    </div><!-- /.reels-filter-bar -->

    <!-- ── GRID DE REELS ───────────────────────────────────────────────── -->
    <div class="reels-grid">
        <?php if (empty($reels)): ?>
            <div class="reels-empty">
                <i class="fa-solid fa-film"></i>
                <p>Nenhum vídeo encontrado com os filtros selecionados.</p>
                <a href="reels.php" class="btn-reset-full">Limpar filtros</a>
            </div>
        <?php else: ?>
            <?php foreach ($reels as $reel): ?>
                <?php
                $hasAccess = false;
                if ($is_admin || ($current_user_id && $reel['user_id'] == $current_user_id)) {
                    $hasAccess = true;
                } else {
                    $hasAccess = $paymentService->hasAccess($current_user_id ?? 0, 'video', $reel['id']);
                }

                $is_paid       = isset($reel['is_for_sale']) && $reel['is_for_sale'];
                $is_sensitive  = !empty($reel['is_sensitive']);
                $profile_pic   = UPLOAD_URL . htmlspecialchars($reel['profile_picture'] ?? 'profiles/default_profile.png');
                $author        = htmlspecialchars($reel['username']);
                $feed_id       = $reel['feed_item_id'] ?? $reel['id'];
                $duration_s    = (int)($reel['duration_seconds'] ?? 0);
                $thumbnail_url = UPLOAD_URL . htmlspecialchars($reel['thumbnail_path'] ?? '');
                $video_url     = UPLOAD_URL . htmlspecialchars($reel['video_path']);
                $caption       = htmlspecialchars($reel['caption'] ?? '');

                // ── Blur de conteúdo explícito ──
                $ai_risk_level  = $reel['risk_level'] ?? null;
                $is_high_risk   = ($ai_risk_level === 'high');
                $is_medium_risk = ($ai_risk_level === 'medium');
                $should_blur    = ($is_high_risk || $is_medium_risk) && !$is_admin;
                $isVerifiedCreator = (bool)($logged_in_user_data['is_verified_creator'] ?? false);
                ?>

                <div class="reel-card post-card <?= $is_sensitive ? 'is-sensitive' : '' ?>"
                    data-feed-item-id="<?= (int)$feed_id ?>">

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
                                    data-id="<?= (int)$feed_id ?>"
                                    data-item-id="<?= (int)$reel['id'] ?>"
                                    data-item-type="video"
                                    data-src="<?= $video_url ?>"
                                    data-has-access="true"
                                    data-is-for-sale="<?= $is_paid ? 'true' : 'false' ?>"
                                    data-price="<?= $reel['price'] ?? 0 ?>"
                                    data-thumbnail="<?= $thumbnail_url ?>"
                                    data-action="view-request"
                                    data-feed-item-id="<?= (int)$feed_id ?>"
                                    data-duration="<?= (int)($reel['duration_seconds'] ?? 0) ?>"
                                    data-author-id="<?= (int)$reel['user_id'] ?>"
                                    data-views-count="<?= (int)($reel['views_count'] ?? 0) ?>"
                                    data-shares-count="<?= (int)($reel['shares_count'] ?? 0) ?>"
                                    data-video-width="<?= (int)($reel['video_width'] ?? 0) ?>"
                                    data-video-height="<?= (int)($reel['video_height'] ?? 0) ?>"
                                    data-is-post-owner="<?= ($current_user_id && $reel['user_id'] == $current_user_id) ? 'true' : 'false' ?>"
                                    data-checkout-url="<?= htmlspecialchars(BASE_URL . 'checkout.php?type=video&id=' . (int)$reel['id']) ?>"
                                    data-is-verified="<?= $isVerifiedCreator ? 'true' : 'false' ?>"
                                    data-ai-status="<?= !empty($reel['risk_level']) ? 'done' : '' ?>"
                                    data-ai-risk="<?= htmlspecialchars($reel['risk_level'] ?? '') ?>"
                                    data-ai-score="<?= htmlspecialchars($reel['ai_score'] ?? 0) ?>">

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
                                data-id="<?= (int)$feed_id ?>"
                                data-item-id="<?= (int)$reel['id'] ?>"
                                data-is-for-sale="true"
                                data-price="<?= $reel['price'] ?? 0 ?>"
                                data-has-access="false"
                                data-is-post-owner="false"
                                data-thumbnail="<?= $thumbnail_url ?>"
                                data-src="<?= $video_url ?>"
                                data-feed-item-id="<?= (int)$feed_id ?>"
                                data-duration="<?= (int)($reel['duration_seconds'] ?? 0) ?>"
                                data-author-id="<?= (int)$reel['user_id'] ?>"
                                data-views-count="<?= (int)($reel['views_count'] ?? 0) ?>"
                                data-shares-count="<?= (int)($reel['shares_count'] ?? 0) ?>"
                                data-video-width="<?= (int)($reel['video_width'] ?? 0) ?>"
                                data-video-height="<?= (int)($reel['video_height'] ?? 0) ?>"
                                data-is-verified="<?= $isVerifiedCreator ? 'true' : 'false' ?>"
                                data-checkout-url="<?= htmlspecialchars(BASE_URL . 'checkout.php?type=video&id=' . (int)$reel['id']) ?>"
                                data-ai-status="<?= !empty($reel['risk_level']) ? 'done' : '' ?>"
                                data-ai-risk="<?= htmlspecialchars($reel['risk_level'] ?? '') ?>"
                                data-ai-score="<?= htmlspecialchars($reel['ai_score'] ?? 0) ?>">

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
            <?php endforeach; ?>
        <?php endif; ?>
    </div><!-- /.reels-grid -->
</div><!-- /.reels-page -->

<!-- ── SCRIPTS ──────────────────────────────────────────────────────────────── -->

<!-- 1. Variáveis globais PRIMEIRO -->
<script>
    window.BASE_URL = "<?= BASE_URL ?>";
    window.UPLOAD_URL = "<?= UPLOAD_URL ?>";
    window.CURRENT_USER_ID = <?= is_logged_in() ? (int)get_current_user_id() : 'null' ?>;
    window.POST_OWNER_ID = null;
    window.IS_POST_OWNER = false;
    window.CURRENT_USER_PROFILE_PICTURE = "<?= htmlspecialchars($_SESSION['user_profile_picture'] ?? UPLOAD_URL . 'profiles/default_profile.png') ?>";
    window.IS_VERIFIED_CREATOR = <?= json_encode((bool)($logged_in_user_data['is_verified_creator'] ?? false)) ?>;
    window.CSRF_TOKEN = "<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>";
</script>


<script src="<?= BASE_URL ?>assets/js/components/premium_lightbox.js"></script>
<script src="<?= BASE_URL ?>assets/js/pages/reels.js?v=202606161014"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php require_once __DIR__ . '/../includes/reels_lightbox.php'; ?>