<?php

/**
 * View: Perfil de Utilizador
 * Toda a lógica vem de ProfileController::load()
 * Esta página não contém lógica de negócio — apenas apresentação.
 */
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/adult-content-helper.php';

SecurityManager::initSecurity();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Models/User.php';

use Massango\Controllers\ProfileController;

// ── Controller: toda a lógica de negócio ─────────────────────────────────────
$data = (new ProfileController($pdo))->load($_GET['id'] ?? null);

// ── Desempacotar variáveis explicitamente (evitar extract) ───────────────────
$profile_user_id     = $data['profile_user_id'];
$profile_data        = $data['profile_data'];
$current_user_id     = $data['current_user_id'];
$logged_in_user_data = $data['logged_in_user_data'];
$user_data           = $data['user_data'];
$is_admin            = $data['is_admin'];
$is_owner            = $data['is_owner'];
$am_i_blocked        = $data['am_i_blocked'];
$is_blocked_by_me    = $data['is_blocked_by_me'];
$is_following        = $data['is_following'];
$has_pending_request = $data['has_pending_request'];
$can_view_content    = $data['can_view_content'];
$followers_count     = $data['followers_count'];
$following_count     = $data['following_count'];
$total_visits        = $data['total_visits'];
$star_rating         = $data['star_rating'];
$enriched_feed       = $data['enriched_feed'];
$notifications       = $data['notifications'];
$csrf_token          = $data['csrf_token'];
$saved_ids           = $data['saved_ids'];
$redirect_context    = $data['redirect_context'];

// ── Variável calculada uma vez (usada em múltiplos sítios da view) ────────────
$account_type = $profile_data['account_type'] ?? 'standard';

// ── Bloco de acesso restrito ──────────────────────────────────────────────────
if ($am_i_blocked || $is_blocked_by_me) {
    require_once __DIR__ . '/../includes/header.php';
?>
    <div class="main-content-area full-width">
        <div class="card" style="padding:40px;text-align:center;">
            <h2>Utilizador não encontrado ou acesso restrito.</h2>
            <p>Não tem permissão para visualizar este perfil.</p>
            <a href="<?= BASE_URL ?>" class="btn btn-primary">Voltar ao Início</a>
        </div>
    </div>
<?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

// ── Labels e classes de follow (calculados uma vez, fora do HTML) ─────────────
$follow_label = $is_following ? 'Seguindo' : ($has_pending_request ? 'Pedido Enviado' : 'Seguir');
$follow_class = $is_following ? 'following' : ($has_pending_request ? 'following' : '');
$follow_icon  = $is_following ? 'fa-user-check' : 'fa-user-plus';

// ── CSS extra para o header ───────────────────────────────────────────────────
$extra_css = ['components/premium_lightbox.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/profile_layout.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/cards.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components/repost-header.css">
<script src="<?= BASE_URL ?>assets/js/components/description-truncate.js" defer></script>

<div class="main-layout-container profile-page-container">
    <div class="main-content-area">
        <section class="feed-section">
            <div class="posts-list-scrollable">

                <!-- ============================================================
                     Cabeçalho do Perfil
                     ============================================================ -->
                <div class="profile-header card">

                    <!-- Foto de Capa -->
                    <div class="profile-cover-area">
                        <?php if (!empty($profile_data['cover_photo'])): ?>
                            <img src="<?= UPLOAD_URL . htmlspecialchars($profile_data['cover_photo']) ?>"
                                alt="Foto de Capa"
                                class="profile-cover-img">
                        <?php endif; ?>
                        <?php if ($is_owner): ?>
                            <a href="<?= BASE_URL ?>settings.php?tab=cover" class="btn-edit-cover">
                                <i class="fa-solid fa-camera"></i>
                                <span>Editar capa</span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="profile-avatar-row">
                        <!-- Avatar -->
                        <div class="profile-avatar-wrap">
                            <img src="<?= UPLOAD_URL . htmlspecialchars($profile_data['profile_picture'] ?? 'default_profile.png') ?>"
                                alt="Foto de Perfil"
                                class="profile-avatar">

                            <?php if ($account_type === 'professional'): ?>
                                <div class="avatar-badge" title="Profissional">
                                    <i class="fas fa-check-circle" style="color:#4f46e5;"></i>
                                </div>
                            <?php elseif ($account_type === 'premium'): ?>
                                <div class="avatar-badge" title="Premium">
                                    <i class="fas fa-crown" style="color:#ffd700;"></i>
                                </div>
                            <?php endif; ?>

                            <?php if ($is_owner): ?>
                                <a href="<?= BASE_URL ?>settings.php?tab=avatar" class="btn-edit-avatar" title="Alterar foto">
                                    <i class="fa-solid fa-camera"></i>
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Ações -->
                        <div class="profile-header-actions">
                            <?php if ($is_owner): ?>

                                <button class="btn-add-post"
                                    onclick="typeof openPublicationModal === 'function' ? openPublicationModal() : (window.location.href='<?= BASE_URL ?>create.php')"
                                    title="Adicionar publicação">
                                    <i class="fa-solid fa-plus"></i>
                                    <span>Adicionar</span>
                                </button>
                                <a href="<?= BASE_URL ?>settings.php" class="btn-profile-more" title="Configurações">
                                    <i class="fa-solid fa-gear"></i>
                                </a>

                            <?php elseif (is_logged_in()): ?>

                                <?php if (!$is_blocked_by_me): ?>
                                    <button class="btn-follow-profile <?= $follow_class ?> follow-btn-mini"
                                        onclick="App.toggleFollow(<?= (int)$profile_user_id ?>, this)"
                                        data-user-id="<?= (int)$profile_user_id ?>">
                                        <i class="fa-solid <?= $follow_icon ?>"></i>
                                        <span><?= $follow_label ?></span>
                                    </button>
                                <?php endif; ?>

                                <?php
                                $block_confirm = $is_blocked_by_me
                                    ? 'Deseja desbloquear este usuário?'
                                    : 'Tem certeza que deseja bloquear este usuário?';
                                $block_action  = $is_blocked_by_me ? 'unblock' : 'block';
                                $block_title   = $is_blocked_by_me ? 'Desbloquear' : 'Bloquear';
                                $block_icon    = $is_blocked_by_me ? 'fa-user-check' : 'fa-ellipsis';
                                ?>
                                <form action="<?= BASE_URL ?>actions/block.php" method="POST" style="margin:0;"
                                    onsubmit="return confirm('<?= $block_confirm ?>');">
                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($profile_user_id) ?>">
                                    <input type="hidden" name="action" value="<?= $block_action ?>">
                                    <button type="submit" class="btn-profile-more" title="<?= $block_title ?>">
                                        <i class="fa-solid <?= $block_icon ?>"></i>
                                    </button>
                                </form>

                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Info: Nome, Stars, Stats, Bio -->
                    <div class="profile-info-main">
                        <div class="name-and-meta">
                            <h1>
                                <?= htmlspecialchars($profile_data['username']) ?>
                                <?php if ($account_type === 'professional'): ?>
                                    <i class="fas fa-check-circle" style="color:#4f46e5;font-size:0.65em;" title="Profissional"></i>
                                <?php elseif ($account_type === 'premium'): ?>
                                    <i class="fas fa-crown" style="color:#ffd700;font-size:0.65em;" title="Premium"></i>
                                <?php endif; ?>
                            </h1>

                            <?php if ($star_rating > 0): ?>
                                <div class="profile-rating">
                                    <div class="star-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa fa-star" style="color:<?= $i <= $star_rating ? '#fbbf24' : 'inherit' ?>;font-size:0.8rem;"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="profile-stats">
                            <?php if ($is_owner): ?>
                                <div class="stat-item">
                                    <strong><?= number_format($total_visits, 0, ',', '.') ?></strong>
                                    <small>visitas</small>
                                </div>
                                <div class="stat-divider"></div>
                            <?php endif; ?>
                            <div class="stat-item">
                                <a href="<?= BASE_URL ?>followers.php?id=<?= htmlspecialchars($profile_user_id) ?>">
                                    <strong><?= $followers_count ?></strong>
                                    <small>seguidores</small>
                                </a>
                            </div>
                            <div class="stat-divider"></div>
                            <div class="stat-item">
                                <a href="<?= BASE_URL ?>following.php?id=<?= htmlspecialchars($profile_user_id) ?>">
                                    <strong><?= $following_count ?></strong>
                                    <small>seguindo</small>
                                </a>
                            </div>
                        </div>

                        <p class="profile-bio"><?= htmlspecialchars($profile_data['bio'] ?? 'Nenhuma biografia ainda.') ?></p>
                    </div>
                </div>
                <!-- /profile-header -->

                <!-- Filtros de Conteúdo -->
                <div class="profile-tabs card">
                    <div class="filter-buttons">
                        <button class="active" data-filter="all">
                            <i class="fa-solid fa-rss"></i>
                            <span class="filter-btn-text">Feed</span>
                        </button>
                        <button data-filter="post">
                            <i class="fa-solid fa-image"></i>
                            <span class="filter-btn-text">Fotos</span>
                        </button>
                        <button data-filter="video">
                            <i class="fa-solid fa-play"></i>
                            <span class="filter-btn-text">Vídeos</span>
                        </button>
                        <button data-filter="album">
                            <i class="fa-solid fa-images"></i>
                            <span class="filter-btn-text">Álbuns</span>
                        </button>
                    </div>
                </div>

                <!-- ============================================================
                     Conteúdo do Perfil
                     ============================================================ -->
                <div class="profile-feed-col">
                    <div id="profileContentFiltered">

                        <?php if (!$can_view_content): ?>
                            <!-- Perfil privado -->
                            <div class="private-profile-message"
                                style="grid-column:1/-1;padding:60px;text-align:center;background:#f9f9f9;">
                                <i class="fa-solid fa-lock" style="font-size:3rem;color:#ccc;margin-bottom:20px;"></i>
                                <h3>Este perfil é privado</h3>
                                <p style="color:#666;">Siga este utilizador para ver as suas publicações.</p>
                            </div>

                        <?php elseif (!empty($enriched_feed)): ?>

                            <?php foreach ($enriched_feed as $item): ?>
                                <?php
                                // Desempacotar o item para os partials (interface esperada pelos partials)
                                $content_data           = $item['content_data'];
                                $author                 = $item['author'];
                                $like_info              = $item['like_info'];
                                $user_vote              = $item['user_vote'];
                                $comment_count          = $item['comment_count'];
                                $ai_analysis            = $item['ai_analysis'];
                                $is_post_owner          = $item['is_post_owner'];
                                $is_admin               = $item['is_admin'];
                                $should_blur            = $item['should_blur'];
                                $isRepost               = $item['isRepost'];
                                $sharedData             = $item['sharedData'];
                                $sharedType             = $item['sharedType'];
                                $sharedAuthor           = $item['sharedAuthor'];
                                $sharedId               = $item['sharedId'];
                                $can_see_sale_indicator = $item['can_see_sale_indicator'];

                                // Variáveis de grid (calculadas aqui para não repetir lógica no HTML)
                                $display_type      = $item['item_type'];
                                $feed_item_id      = $item['feed_item_id'];
                                $grid_is_paid      = !empty($content_data['is_for_sale']);
                                $grid_is_sensitive = !empty($content_data['is_sensitive'])
                                    || in_array($ai_analysis['risk_level'] ?? '', ['medium', 'high']);
                                ?>

                                <!-- ── Card de Feed ──────────────────────────── -->
                                <article class="post-card card feed-item-wrapper <?= $item['item_type'] === 'album' ? 'album-card-style' : '' ?>"
                                    data-type="all"
                                    data-feed-item-id="<?= (int)$item['feed_item_id'] ?>">

                                    <?php include __DIR__ . '/../includes/feed/_post-header.php'; ?>

                                    <div class="post-content">
                                        <?php if ($isRepost && $sharedData && $sharedAuthor): ?>
                                            <?php include __DIR__ . '/../includes/feed/_repost-content.php'; ?>
                                        <?php else: ?>
                                            <?php include __DIR__ . '/../includes/feed/_' . $item['item_type'] . '-media.php'; ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php include __DIR__ . '/../includes/feed/_post-footer.php'; ?>

                                </article>

                                <!-- ── Item de Grid (filtros Fotos/Vídeos/Álbuns) ── -->
                                <div class="grid-item-wrapper"
                                    data-type="<?= htmlspecialchars($display_type) ?>"
                                    style="display:none;">
                                    <div class="profile-grid-item <?= $display_type === 'video' ? 'lightbox-trigger video-item grid-trigger' : 'grid-trigger' ?>"
                                        data-id="<?= htmlspecialchars($feed_item_id ?? $item['item_id']) ?>"
                                        data-post-modal="<?= htmlspecialchars($feed_item_id ?? $item['item_id']) ?>"
                                        data-type="<?= $display_type ?>"
                                        data-item-type="<?= $display_type ?>"
                                        data-src="<?= $display_type === 'video' ? UPLOAD_URL . htmlspecialchars($content_data['video_path'] ?? '') : '' ?>"
                                        data-thumbnail="<?= !empty($content_data['thumbnail_path']) ? UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path']) : '' ?>"
                                        data-duration="<?= (int)($content_data['duration_seconds'] ?? 0) ?>"
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
                                            <?php $grid_duration_s = (int)($content_data['duration_seconds'] ?? 0); ?>

                                            <?php if (!empty($content_data['thumbnail_path'])): ?>
                                                <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path']) ?>"
                                                    alt="thumbnail"
                                                    class="post-video <?= ($grid_is_sensitive || $grid_is_paid) ? 'media-blur' : '' ?>"
                                                    style="width:100%;height:100%;object-fit:cover;display:block;">
                                            <?php else: ?>
                                                <video src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
                                                    muted playsinline preload="metadata"
                                                    class="post-video <?= ($grid_is_sensitive || $grid_is_paid) ? 'media-blur' : '' ?>">
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
                                            <?php
                                            $grid_album_thumb = !empty($content_data['thumbnail_path'])
                                                ? $content_data['thumbnail_path']
                                                : ($content_data['cover_photo_url'] ?? 'default_album.png');
                                            ?>
                                            <img src="<?= UPLOAD_URL . htmlspecialchars($grid_album_thumb) ?>"
                                                alt="Álbum"
                                                class="grid-image <?= ($grid_is_sensitive || $grid_is_paid) ? 'media-blur' : '' ?>">
                                            <div class="grid-item-overlay"><i class="fas fa-images"></i></div>

                                        <?php else: /* post */ ?>
                                            <?php
                                            $grid_img = !empty($content_data['thumbnail_path'])
                                                ? $content_data['thumbnail_path']
                                                : ($content_data['image_path'] ?? 'default_post.png');
                                            ?>
                                            <img src="<?= UPLOAD_URL . htmlspecialchars($grid_img) ?>"
                                                alt="Post"
                                                class="grid-image <?= ($grid_is_sensitive || $grid_is_paid) ? 'media-blur' : '' ?>">
                                        <?php endif; ?>

                                        <!-- Overlays de acesso -->
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

                            <?php endforeach; ?>

                        <?php else: ?>
                            <div class="no-posts-message"
                                style="grid-column:1/-1;padding:60px;text-align:center;color:#666;">
                                <i class="fa-regular fa-folder-open"
                                    style="font-size:3rem;color:#ccc;margin-bottom:20px;display:block;"></i>
                                <p>Nenhuma publicação encontrada.</p>
                            </div>
                        <?php endif; ?>

                    </div><!-- /#profileContentFiltered -->

                    <div id="loadMoreContainer" style="text-align:center;margin:20px 0;display:none;">
                        <button id="loadMoreBtn" class="btn btn-primary"
                            style="background:var(--primary-gradient);border:none;padding:10px 30px;border-radius:20px;cursor:pointer;">
                            Carregar Mais
                        </button>
                    </div>
                </div><!-- /.profile-feed-col -->

            </div><!-- /.posts-list-scrollable -->
        </section>
    </div>
</div>

<!-- ============================================================
     Lightbox Premium
     ============================================================ -->
<div id="feedLightbox" class="photo-lightbox-modal">
    <div class="close-lightbox" data-action="close-lightbox">
        <i class="fa-solid fa-xmark"></i>
    </div>
    <div class="photo-lightbox-content">
        <div class="reels-scroll-nav">
            <button class="scroll-nav-btn" onclick="scrollToReelByOffset(-1)">
                <i class="fa-solid fa-chevron-up"></i>
            </button>
            <button class="scroll-nav-btn" onclick="scrollToReelByOffset(1)">
                <i class="fa-solid fa-chevron-down"></i>
            </button>
        </div>
        <div class="photo-display-area">
            <div id="lightboxScrollContainer">
                <!-- Reels items injectados via JS -->
            </div>
        </div>
        <div class="photo-sidebar">
            <div class="photo-sidebar-header">
                <h3>Comentários</h3>
                <button class="sidebar-close-btn" data-action="close-sidebar"
                    style="background:none;border:none;color:#fff;cursor:pointer;font-size:20px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="photo-sidebar-body" id="lightboxCommentsArea">
                <!-- Comments injectados via JS -->
            </div>
            <div class="photo-comment-form-area">
                <?php if (is_logged_in()): ?>
                    <form id="lightboxCommentForm" class="photo-comment-form">
                        <div class="comment-input-wrapper">
                            <input type="text" id="lightboxCommentInput"
                                placeholder="Escreva um comentário..." autocomplete="off">
                            <button type="submit">Enviar</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="login-to-comment">
                        Faça <a href="<?= BASE_URL ?>login.php">login</a> para comentar.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de publicação (apenas para o dono) -->
<?php if ($is_owner): ?>
    <?php require_once __DIR__ . '/../includes/publication-modals.php'; ?>
<?php endif; ?>

<!-- Modal de verificação -->
<div id="verificationInviteModal" class="verification-invite-modal">
    <div class="verification-invite-content">
        <span class="invite-close" onclick="closeVerificationInviteModal()">&times;</span>
        <div class="invite-illustration">
            <svg viewBox="0 0 200 200" class="invite-id">
                <rect x="40" y="60" width="120" height="80" rx="12" />
                <circle cx="75" cy="95" r="15" />
                <rect x="100" y="85" width="45" height="6" rx="3" />
                <rect x="100" y="100" width="35" height="6" rx="3" />
            </svg>
            <div class="invite-magnifier"></div>
        </div>
        <h2>Verifique sua conta</h2>
        <p>
            Para aceder a conteúdos pagos, comprar acessos ou vender publicações,
            é necessário verificar sua conta primeiro.<br><br>
            A verificação ajuda a manter a comunidade segura e aumenta
            a confiança entre os utilizadores.
        </p>
        <button class="invite-verify-btn" onclick="proceedToVerification()">
            Fazer verificação
        </button>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/verificationmodal.php'; ?>

<!-- ============================================================
     Scripts
     ============================================================ -->

<!-- 1. Variáveis globais para o JS -->
<script>
    window.BASE_URL = "<?= BASE_URL ?>";
    window.UPLOAD_URL = "<?= UPLOAD_URL ?>";
    window.CURRENT_USER_ID = <?= is_logged_in() ? get_current_user_id() : 'null' ?>;
    window.POST_OWNER_ID = <?= json_encode((int)$profile_user_id) ?>;
    window.IS_POST_OWNER = (window.CURRENT_USER_ID !== null && window.POST_OWNER_ID !== null && window.CURRENT_USER_ID == window.POST_OWNER_ID);
    window.CURRENT_USER_PROFILE_PICTURE = "<?= htmlspecialchars($_SESSION['user_profile_picture'] ?? UPLOAD_URL . 'profiles/default_profile.png') ?>";
    window.IS_VERIFIED_CREATOR = <?= json_encode((bool)($logged_in_user_data['is_verified_creator'] ?? false)) ?>;
</script>

<!-- 2. Dependências -->
<script src="<?= BASE_URL ?>assets/js/core/common_notifications.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/notifications.js"></script>

<!-- 3. Core -->
<script src="<?= BASE_URL ?>assets/js/core/main.js"></script>

<!-- 4. Componentes -->
<script src="<?= BASE_URL ?>assets/js/components/comments.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/track_views.js"></script>
<script src="<?= BASE_URL ?>assets/js/components/premium_lightbox.js"></script>

<!-- 5. Página -->
<script src="<?= BASE_URL ?>assets/js/pages/profile.js"></script>
<script src="<?= BASE_URL ?>assets/js/components/save.js"></script>
<script src="<?= BASE_URL ?>assets/js/pages/media-backdrop.js" defer></script>

<?php require_once __DIR__ . '/../includes/profile-footer.php'; ?>