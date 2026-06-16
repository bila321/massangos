<?php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/adult-content-helper.php';
SecurityManager::initSecurity();
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Controllers\ProfileController;
use Massango\Models\User;
use Massango\Models\Post;
use Massango\Models\Comment;
use Massango\Models\Like;
use Massango\Models\Video;
use Massango\Models\Album;
use Massango\Models\FeedItem;
use Massango\Models\Notification;

$data = (new ProfileController($pdo))->load($_GET['id'] ?? null);
extract($data);
$paymentService = new \Massango\Services\PaymentService($pdo);

// Bloco bloqueado
if ($am_i_blocked || $is_blocked_by_me) {
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="main-content-area full-width"><div class="card" style="padding: 40px; text-align: center;">';
    echo '<h2>Utilizador n�o encontrado ou acesso restrito.</h2>';
    echo '<p>N�o tem permiss�o para visualizar este perfil.</p>';
    echo '<a href="' . BASE_URL . '" class="btn btn-primary">Voltar ao In�cio</a>';
    echo '</div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$extra_css = ['premium_lightbox.css'];
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

                            <?php
                            $account_type = $profile_data['account_type'] ?? 'standard';
                            if ($account_type === 'professional'): ?>
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

                        <!-- AÃ§Ãµes -->
                        <div class="profile-header-actions">
                            <?php if ($is_owner): ?>
                                <!-- Dono: botÃ£o + Adicionar -->
                                <button class="btn-add-post"
                                    onclick="typeof openPublicationModal === 'function' ? openPublicationModal() : (window.location.href='<?= BASE_URL ?>create.php')"
                                    title="Adicionar publicaçãO">
                                    <i class="fa-solid fa-plus"></i>
                                    <span>Adicionar</span>
                                </button>
                                <a href="<?= BASE_URL ?>settings.php" class="btn-profile-more" title="Configurações">
                                    <i class="fa-solid fa-gear"></i>
                                </a>

                            <?php elseif (is_logged_in()): ?>
                                <!-- Visitante: Seguir + Bloquear -->
                                <?php if (!$is_blocked_by_me): ?>
                                    <?php
                                    $follow_label = $is_following ? 'Seguindo' : ($has_pending_request ? 'Pedido Enviado' : 'Seguir');
                                    $follow_extra = $is_following ? 'following' : ($has_pending_request ? 'following' : '');
                                    ?>
                                    <button class="btn-follow-profile <?= $follow_extra ?> follow-btn-mini"
                                        onclick="App.toggleFollow(<?= (int)$profile_user_id ?>, this)"
                                        data-user-id="<?= (int)$profile_user_id ?>">
                                        <i class="fa-solid <?= $is_following ? 'fa-user-check' : 'fa-user-plus' ?>"></i>
                                        <span><?= $follow_label ?></span>
                                    </button>
                                <?php endif; ?>

                                <!-- Bloquear / Desbloquear como Ã­cone discreto -->
                                <form action="<?= BASE_URL ?>actions/block.php" method="POST" style="margin:0;"
                                    onsubmit="return confirm('<?= $is_blocked_by_me ? 'Deseja desbloquear este usuário?' : 'Tem certeza que deseja bloquear este usuário?' ?>');">
                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars($profile_user_id) ?>">
                                    <input type="hidden" name="action" value="<?= $is_blocked_by_me ? 'unblock' : 'block' ?>">
                                    <button type="submit"
                                        class="btn-profile-more"
                                        title="<?= $is_blocked_by_me ? 'Desbloquear' : 'Bloquear' ?>">
                                        <i class="fa-solid <?= $is_blocked_by_me ? 'fa-user-check' : 'fa-ellipsis' ?>"></i>
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
                                    <i class="fas fa-check-circle" style="color:#4f46e5; font-size:0.65em;" title="Profissional"></i>
                                <?php elseif ($account_type === 'premium'): ?>
                                    <i class="fas fa-crown" style="color:#ffd700; font-size:0.65em;" title="Premium"></i>
                                <?php endif; ?>
                            </h1>

                            <?php if ($star_rating > 0): ?>
                                <div class="profile-rating">
                                    <div class="star-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa fa-star" style="color:<?= $i <= $star_rating ? '#fbbf24' : 'inherit' ?>; font-size:0.8rem;"></i>
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
                            <span class="filter-btn-text">Ví­deos</span>
                        </button>
                        <button data-filter="album">
                            <i class="fa-solid fa-images"></i>
                            <span class="filter-btn-text">álbuns</span>
                        </button>
                    </div>
                </div>
                <!-- Coluna Direita do Feed -->
                <div class="profile-feed-col">
                    <div id="profileContentFiltered">
                        <?php if (!$can_view_content): ?>
                            <div class="private-profile-message" style="grid-column: 1 / -1; padding: 60px; text-align: center; background: #f9f9f9;">
                                <i class="fa-solid fa-lock" style="font-size: 3rem; color: #ccc; margin-bottom: 20px;"></i>
                                <h3>Este perfil é privado</h3>
                                <p style="color: #666;">Siga este usuário para ver suas publicações e fotos.</p>
                            </div>
                        <?php elseif (!empty($all_user_content)): ?>
                            <?php
                            // =====================================================
                            // BATCH PRE-FETCH — evita N+1 queries no loop abaixo
                            // =====================================================
                            $is_admin = isset($_SESSION['admin_id']);

                            $feed_item_ids  = array_filter(array_column($all_user_content, 'feed_item_id'));
                            $all_user_ids   = array_unique(array_filter(array_column($all_user_content, 'user_id')));
                            $all_post_ids   = array_filter(array_map(fn($i) => $i['item_type'] === 'post' ? (int)$i['item_id'] : null, $all_user_content));

                            // Batch: autores
                            $authors_map = [];
                            if (!empty($all_user_ids)) {
                                $ph = implode(',', array_fill(0, count($all_user_ids), '?'));
                                $s = $pdo->prepare("SELECT * FROM users WHERE id IN ($ph)");
                                $s->execute(array_values($all_user_ids));
                                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) $authors_map[(int)$row['id']] = $row;
                            }

                            // Batch: likes/dislikes
                            $likes_map = [];
                            if (!empty($feed_item_ids)) {
                                $ph = implode(',', array_fill(0, count($feed_item_ids), '?'));
                                $s = $pdo->prepare("SELECT feed_item_id, SUM(type='like') AS likes, SUM(type='dislike') AS dislikes FROM feed_item_likes WHERE feed_item_id IN ($ph) GROUP BY feed_item_id");
                                $s->execute(array_values($feed_item_ids));
                                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) $likes_map[(int)$row['feed_item_id']] = ['likes' => (int)$row['likes'], 'dislikes' => (int)$row['dislikes']];
                            }

                            // Batch: votos do utilizador
                            $votes_map = [];
                            if (!empty($feed_item_ids) && is_logged_in()) {
                                $ph = implode(',', array_fill(0, count($feed_item_ids), '?'));
                                $params = array_values($feed_item_ids);
                                $params[] = $current_user_id;
                                $s = $pdo->prepare("SELECT feed_item_id, type FROM feed_item_likes WHERE feed_item_id IN ($ph) AND user_id = ?");
                                $s->execute($params);
                                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) $votes_map[(int)$row['feed_item_id']] = $row['type'];
                            }

                            // Batch: contagem de comentários
                            $comment_counts_map = [];
                            if (!empty($feed_item_ids)) {
                                $ph = implode(',', array_fill(0, count($feed_item_ids), '?'));
                                $s = $pdo->prepare("SELECT feed_item_id, COUNT(*) AS total FROM comments WHERE feed_item_id IN ($ph) GROUP BY feed_item_id");
                                $s->execute(array_values($feed_item_ids));
                                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) $comment_counts_map[(int)$row['feed_item_id']] = (int)$row['total'];
                            }

                            // Batch: share counts
                            $share_counts_map = [];
                            if (!empty($all_post_ids)) {
                                $ph = implode(',', array_fill(0, count($all_post_ids), '?'));
                                $s = $pdo->prepare("SELECT post_id, COUNT(*) AS total FROM post_shares WHERE post_id IN ($ph) GROUP BY post_id");
                                $s->execute(array_values($all_post_ids));
                                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) $share_counts_map[(int)$row['post_id']] = (int)$row['total'];
                            }

                            // Batch: follow state (utilizador logado → autores)
                            $following_set = [];
                            $pending_set   = [];
                            if (is_logged_in() && !empty($all_user_ids)) {
                                $ph = implode(',', array_fill(0, count($all_user_ids), '?'));
                                $params = array_values($all_user_ids);
                                $s = $pdo->prepare("SELECT followed_id FROM follows WHERE follower_id = ? AND followed_id IN ($ph)");
                                $s->execute(array_merge([$current_user_id], $params));
                                foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) $following_set[(int)$id] = true;

                                $s = $pdo->prepare("SELECT followed_id FROM follow_requests WHERE follower_id = ? AND followed_id IN ($ph)");
                                $s->execute(array_merge([$current_user_id], $params));
                                foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) $pending_set[(int)$id] = true;
                            }

                            // Batch: AI analysis
                            $ai_analysis_map = [];
                            $all_analysis_ids = array_unique(array_filter(array_map(function ($i) {
                                if ($i['item_type'] === 'post' && !empty($i['is_repost']) && !empty($i['shared_post_id'])) return (int)$i['shared_post_id'];
                                return (int)$i['item_id'];
                            }, $all_user_content)));
                            if (!empty($all_analysis_ids)) {
                                $ph = implode(',', array_fill(0, count($all_analysis_ids), '?'));
                                $s = $pdo->prepare("SELECT post_id, risk_level, status, explicit_percentage FROM media_analysis WHERE post_id IN ($ph)");
                                $s->execute(array_values($all_analysis_ids));
                                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) $ai_analysis_map[(int)$row['post_id']] = $row;
                            }

                            // Batch: repost shared content
                            $repost_post_ids   = [];
                            $repost_video_ids  = [];
                            $repost_album_ids  = [];
                            foreach ($all_user_content as $i) {
                                if ($i['item_type'] === 'post' && !empty($i['is_repost']) && !empty($i['shared_post_id'])) {
                                    $st = $i['shared_item_type'] ?? 'post';
                                    $sid = (int)$i['shared_post_id'];
                                    if ($st === 'post')  $repost_post_ids[]  = $sid;
                                    if ($st === 'video') $repost_video_ids[] = $sid;
                                    if ($st === 'album') $repost_album_ids[] = $sid;
                                }
                            }
                            $shared_posts_map = [];
                            if (!empty($repost_post_ids)) {
                                $ph = implode(',', array_fill(0, count($repost_post_ids), '?'));
                                $s = $pdo->prepare("SELECT * FROM posts WHERE id IN ($ph)");
                                $s->execute(array_values($repost_post_ids));
                                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) $shared_posts_map[(int)$row['id']] = $row;
                            }
                            $shared_videos_map = [];
                            if (!empty($repost_video_ids)) {
                                $ph = implode(',', array_fill(0, count($repost_video_ids), '?'));
                                $s = $pdo->prepare("SELECT * FROM videos WHERE id IN ($ph)");
                                $s->execute(array_values($repost_video_ids));
                                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) $shared_videos_map[(int)$row['id']] = $row;
                            }
                            $shared_albums_map = [];
                            if (!empty($repost_album_ids)) {
                                $ph = implode(',', array_fill(0, count($repost_album_ids), '?'));
                                $s = $pdo->prepare("SELECT * FROM albums WHERE id IN ($ph)");
                                $s->execute(array_values($repost_album_ids));
                                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) $shared_albums_map[(int)$row['id']] = $row;
                            }
                            // Batch: autores de conteúdo partilhado
                            $shared_user_ids = array_unique(array_filter(array_merge(
                                array_column($shared_posts_map,  'user_id'),
                                array_column($shared_videos_map, 'user_id'),
                                array_column($shared_albums_map, 'user_id')
                            )));
                            $shared_authors_map = [];
                            if (!empty($shared_user_ids)) {
                                $ph = implode(',', array_fill(0, count($shared_user_ids), '?'));
                                $s = $pdo->prepare("SELECT * FROM users WHERE id IN ($ph)");
                                $s->execute(array_values($shared_user_ids));
                                foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) $shared_authors_map[(int)$row['id']] = $row;
                            }
                            ?>

                            <?php foreach ($all_user_content as $item): ?>
                                <?php
                                $display_type  = $item['item_type'];
                                $content_data  = $item;
                                $feed_item_id  = $item['feed_item_id'] ?? null;
                                $author        = $authors_map[(int)($item['user_id'] ?? 0)] ?? null;
                                if (!$author) {
                                    error_log("profile.php: sem autor para feed_item_id " . ($feed_item_id ?? 'N/A'));
                                    continue;
                                }

                                $like_info     = $feed_item_id ? ($likes_map[(int)$feed_item_id]       ?? ['likes' => 0, 'dislikes' => 0]) : ['likes' => 0, 'dislikes' => 0];
                                $user_vote     = $feed_item_id ? ($votes_map[(int)$feed_item_id]        ?? null) : null;
                                $comment_count = $feed_item_id ? ($comment_counts_map[(int)$feed_item_id] ?? 0) : 0;
                                $is_post_owner = ($current_user_id && $item['user_id'] == $current_user_id);

                                // Follow state
                                $item_author_is_following = isset($following_set[(int)$author['id']]);
                                $has_request              = isset($pending_set[(int)$author['id']]);
                                $follow_label             = $item_author_is_following ? 'Seguindo' : ($has_request ? 'Pendente' : 'Seguir');
                                $follow_class             = $item_author_is_following ? 'following' : ($has_request ? 'pending' : '');

                                // Repost
                                $isRepost    = false;
                                $sharedData  = null;
                                $sharedType  = null;
                                $sharedAuthor = null;
                                $sharedId    = null;
                                if ($item['item_type'] === 'post' && !empty($content_data['is_repost']) && !empty($content_data['shared_post_id']) && !empty($content_data['shared_item_type'])) {
                                    $sharedType = $content_data['shared_item_type'];
                                    $sharedId   = (int)$content_data['shared_post_id'];
                                    $sharedData = match ($sharedType) {
                                        'post'  => $shared_posts_map[$sharedId]  ?? null,
                                        'video' => $shared_videos_map[$sharedId] ?? null,
                                        'album' => $shared_albums_map[$sharedId] ?? null,
                                        default => null,
                                    };
                                    if ($sharedData) {
                                        $isRepost     = true;
                                        $sharedAuthor = $shared_authors_map[(int)$sharedData['user_id']] ?? null;
                                    }
                                }

                                // AI analysis
                                $analysis_id = ($item['item_type'] === 'post' && !empty($content_data['is_repost']) && !empty($content_data['shared_post_id']))
                                    ? (int)$content_data['shared_post_id']
                                    : (int)$item['item_id'];
                                $ai_analysis    = $ai_analysis_map[$analysis_id] ?? null;
                                $is_high_risk   = ($ai_analysis && $ai_analysis['status'] === 'done' && $ai_analysis['risk_level'] === 'high');
                                $is_medium_risk = ($ai_analysis && $ai_analysis['status'] === 'done' && $ai_analysis['risk_level'] === 'medium');
                                $should_blur    = ($is_high_risk || $is_medium_risk) && !$is_admin;

                                // Share count
                                $share_count = $share_counts_map[(int)($feed_item_id ?? $item['item_id'])] ?? 0;
                                ?>
                                <!-- Estrutura para Visualização  em FEED (Opção  "Todas") -->
                                <article class="post-card card feed-item-wrapper <?= ($item['item_type'] === 'album' ? 'album-card-style' : '') ?>"
                                    data-type="all"
                                    data-feed-item-id="<?= htmlspecialchars($item['feed_item_id']) ?>">
                                    <div class="post-header">
                                        <div class="header-author-wrapper">
                                            <div class="avatar-container">
                                                <img src="<?= UPLOAD_URL . htmlspecialchars($author['profile_picture'] ?? 'default_profile.png') ?>" alt="Foto de perfil" class="profile-thumb">
                                                <?php if ($isRepost && $sharedAuthor): ?>
                                                    <img src="<?= UPLOAD_URL . htmlspecialchars($sharedAuthor['profile_picture'] ?? 'default_profile.png') ?>" alt="Original" class="profile-thumb-secondary">
                                                <?php endif; ?>
                                            </div>
                                            <div class="post-info">
                                                <div class="author-line">
                                                    <a href="<?= BASE_URL ?>profile.php?id=<?= htmlspecialchars($author['id']) ?>" class="post-author"><?= htmlspecialchars($author['username']) ?></a>
                                                    <?php if (is_logged_in() && !$is_post_owner): ?>
                                                        <?php // follow_label e follow_class já calculados no batch acima 
                                                        ?>
                                                        <button class="follow-btn-mini <?= $follow_class ?>"
                                                            onclick="App.toggleFollow(<?= (int)$author['id'] ?>, this)"
                                                            data-user-id="<?= (int)$author['id'] ?>">
                                                            <?= $follow_label ?>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($isRepost && $sharedAuthor): ?>
                                                        <i class="fa-solid fa-retweet repost-icon-small"></i>
                                                        <a href="<?= BASE_URL ?>profile.php?id=<?= $sharedAuthor['id'] ?>" class="shared-username-link">
                                                            <?= htmlspecialchars($sharedAuthor['username']) ?>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($is_owner && isset($item['is_for_sale']) && $item['is_for_sale']): ?>
                                                        <div class="sale-indicator" style="background: var(--premium-gradient); color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                            <i class="fas fa-tag"></i> Á VENDA
                                                            <?php if (isset($item['is_approved']) && !$item['is_approved']): ?>
                                                                <span style="background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 10px; margin-left: 5px;"><i class="fas fa-clock"></i> Pendente</span>
                                                            <?php else: ?>
                                                                <span style="background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 10px; margin-left: 5px;"><i class="fas fa-check"></i> Ativo</span>
                                                            <?php endif; ?>
                                                            <a href="sales_performance.php?type=<?= $item['item_type'] ?>&id=<?= $item['id'] ?>" title="Ver Desempenho de Vendas" style="color: white; margin-left: 10px; background: rgba(255,255,255,0.2); width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; border-radius: 50%; text-decoration: none;">
                                                                <i class="fas fa-chart-bar" style="font-size: 0.7rem;"></i>
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="post-date"><?= format_datetime_ago($item['created_at']) ?></span>
                                                <?php if (!empty($ai_analysis)): ?>
                                                    <?php if ($ai_analysis['status'] === 'processing' || $ai_analysis['status'] === 'pending'): ?>
                                                        <span class="ai-badge ai-badge-analyzing"><i class="fas fa-search"></i> Em anÃ¡lise</span>
                                                    <?php elseif ($ai_analysis['status'] === 'done'): ?>
                                                        <?php if ($ai_analysis['risk_level'] === 'medium'): ?>
                                                            <span class="ai-badge ai-badge-sensitive"><i class="far fa-circle"></i></span>
                                                        <?php elseif ($ai_analysis['risk_level'] === 'high'): ?>
                                                            <span class="ai-badge ai-badge-high"><i class="far fa-circle"></i></span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="header-right-actions">
                                            <?php if (is_logged_in() && !$is_post_owner): ?>
                                                <button class="hide-post-btn" onclick="hidePost(<?= (int)($feed_item_id ?? 0) ?>, this)" title="Ocultar Publicações ">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($is_owner || $is_admin): ?>
                                                <div class="post-actions-dropdown">
                                                    <button class="dropdown-toggle" aria-label="Opções do post">&#x22EE;</button>
                                                    <div class="dropdown-menu">
                                                        <?php if ($item['item_type'] === 'post'): ?>
                                                            <?php if ($is_owner): ?>
                                                                <a href="<?= BASE_URL ?>edit_post.php?id=<?= htmlspecialchars($item['item_id']) ?>&redirect_to=profile.php">Editar Publicação</a>
                                                            <?php endif; ?>
                                                            <form action="<?= BASE_URL ?>actions/post.php" method="POST" onsubmit="return confirm('Tem certeza que deseja apagar esta Publicações ?');">
                                                                <input type="hidden" name="action" value="delete_post">
                                                                <input type="hidden" name="post_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                                                                <input type="hidden" name="redirect_to" value="profile.php">
                                                                <button type="submit"><?= $is_admin && !$is_owner ? 'Bloquear/Apagar' : 'Apagar Publicações ' ?></button>
                                                            </form>
                                                        <?php elseif ($item['item_type'] === 'video'): ?>
                                                            <?php if ($is_owner): ?>
                                                                <a href="<?= BASE_URL ?>edit_video.php?id=<?= htmlspecialchars($item['item_id']) ?>&redirect_to=profile.php">Editar Vídeo </a>
                                                            <?php endif; ?>
                                                            <form action="<?= BASE_URL ?>actions/video.php" method="POST" onsubmit="return confirm('Tem certeza que deseja apagar este Vídeo ?');">
                                                                <input type="hidden" name="action" value="delete_video">
                                                                <input type="hidden" name="video_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                                                                <input type="hidden" name="redirect_to" value="profile.php">
                                                                <button type="submit"><?= $is_admin && !$is_owner ? 'Bloquear/Apagar' : 'Apagar Vídeo ' ?></button>
                                                            </form>
                                                        <?php elseif ($item['item_type'] === 'album'): ?>
                                                            <?php if ($is_owner): ?>
                                                                <a href="<?= BASE_URL ?>edit_album.php?id=<?= htmlspecialchars($item['item_id']) ?>&redirect_to=profile.php">Editar Álbum </a>
                                                            <?php endif; ?>
                                                            <form action="<?= BASE_URL ?>actions/album.php" method="POST" onsubmit="return confirm('Tem certeza que deseja apagar este Ã¡lbum?');">
                                                                <input type="hidden" name="action" value="delete_album">
                                                                <input type="hidden" name="album_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                                                                <input type="hidden" name="redirect_to" value="profile.php">
                                                                <button type="submit"><?= $is_admin && !$is_owner ? 'Bloquear/Apagar' : 'Apagar Álbum ' ?></button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div><!-- /.header-right-actions -->
                                    </div>
                                    <div class="post-content">
                                        <?php if ($isRepost && $sharedData && $sharedAuthor): ?>
                                            <div class="original-content-container">
                                                <?php
                                                // $paymentService já  instanciado antes do loop
                                                $hasAccessShared = $paymentService->hasAccess($current_user_id ?? 0, $sharedType, $sharedData['id'] ?? $sharedData['item_id'] ?? 0);
                                                if ($is_admin) $hasAccessShared = true;
                                                ?>
                                                <?php if (isset($sharedData['is_for_sale']) && $sharedData['is_for_sale']): ?>
                                                    <div class="paid-content-badge" style="background: var(--primary-gradient); color: #3b3b3b; padding: 5px 10px; border-radius: 5px; font-size: 0.8em; font-weight: bold; margin-bottom: 10px; display: inline-block;">
                                                        <i class="fas fa-lock"></i> CONTEÃšDO PAGO: <?= number_format($sharedData['price'], 2, ',', '.') ?> MT
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($sharedType === 'post'): ?>
                                                    <?php if (!empty($sharedData['content'])): ?>
                                                        <div class="post-content">
                                                            <p class="post-text"><?= nl2br(htmlspecialchars($sharedData['content'])) ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($sharedData['image_path'])): ?>
                                                        <?php if ($hasAccessShared): ?>
                                                            <div class="media-wrapper-<?= htmlspecialchars($item['feed_item_id']) ?> <?= $should_blur ? 'media-blur-container' : '' ?>">
                                                                <div class="post-image-container" data-post-modal="<?= htmlspecialchars($item['feed_item_id']) ?>" style="cursor: pointer;">
                                                                    <img src="<?= UPLOAD_URL . htmlspecialchars($sharedData['thumbnail_path'] ?: $sharedData['image_path']) ?>" class="post-image <?= $should_blur ? 'media-blur' : '' ?>" alt="Imagem do Post" data-is-paid="<?= (isset($sharedData['is_for_sale']) && $sharedData['is_for_sale']) ? 'true' : 'false' ?>">
                                                                </div>
                                                                <?php if ($should_blur): ?>
                                                                    <div class="media-overlay-msg">
                                                                        <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                                                        <p>Conteúdo Sensível <br><small>Detetado automaticamente pela IA</small></p>
                                                                        <button onclick="event.stopPropagation(); unblurMedia('<?= htmlspecialchars($item['feed_item_id']) ?>')">Ver mesmo assim</button>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <?php if (isset($logged_in_user_data['is_verified_creator']) && $logged_in_user_data['is_verified_creator']): ?>
                                                                <div class="post-locked" onclick="window.location.href='checkout.php?type=post&id=<?= $sharedId ?>'">
                                                                    <img src="<?= UPLOAD_URL . htmlspecialchars($sharedData['thumbnail_path'] ?: $sharedData['image_path']) ?>" alt="Imagem do Post" style="filter: blur(20px);" data-is-paid="<?= (isset($sharedData['is_for_sale']) && $sharedData['is_for_sale']) ? 'true' : 'false' ?>">
                                                                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff;">
                                                                        <i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                                                                        <p>Conteúdo Pago: <?= number_format($sharedData['price'], 2, ',', '.') ?> MT</p>
                                                                        <span style="font-size: 0.8em; text-decoration: underline;">Clique para comprar</span>
                                                                    </div>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="post-locked" onclick="openVerificationInviteModal()">
                                                                    <img src="<?= UPLOAD_URL . htmlspecialchars($sharedData['thumbnail_path'] ?: $sharedData['image_path']) ?>" alt="Imagem do Post" style="filter: blur(20px);" data-is-paid="<?= (isset($sharedData['is_for_sale']) && $sharedData['is_for_sale']) ? 'true' : 'false' ?>">
                                                                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff;">
                                                                        <i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                                                                        <p>Conteúdo Pago: <?= number_format($sharedData['price'], 2, ',', '.') ?> MT</p>
                                                                        <span style="font-size: 0.8em; text-decoration: underline;">Clique para comprar</span>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php elseif ($sharedType === 'video'): ?>
                                                    <?php if (!empty($sharedData['caption'])): ?>
                                                        <div class="post-content">
                                                            <p class="post-text"><?= nl2br(htmlspecialchars($sharedData['caption'] ?? '')) ?></p>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php
                                                    $isForSaleShared = isset($sharedData['is_for_sale']) && $sharedData['is_for_sale'];
                                                    $hasAccessShared = $paymentService->hasAccess($current_user_id ?? 0, 'video', $sharedId);
                                                    ?>

                                                    <?php if ($hasAccessShared || !$isForSaleShared): ?>
                                                        <div class="lightbox-trigger" style="position: relative; overflow: hidden; cursor: pointer;"
                                                            data-type="video"
                                                            data-id="<?= htmlspecialchars($item['feed_item_id']) ?>"
                                                            data-item-id="<?= htmlspecialchars($sharedId) ?>"
                                                            data-item-type="video"
                                                            data-src="<?= UPLOAD_URL . htmlspecialchars($sharedData['video_path']) ?>"
                                                            data-is-for-sale="<?= $isForSaleShared ? 'true' : 'false' ?>"
                                                            data-price="<?= $sharedData['price'] ?? 0 ?>"
                                                            data-has-access="true"
                                                            data-thumbnail="<?= UPLOAD_URL . htmlspecialchars($sharedData['thumbnail_path'] ?? '') ?>"
                                                            data-ai-status="<?= htmlspecialchars($ai_analysis['status'] ?? '') ?>"
                                                            data-ai-risk="<?= htmlspecialchars($ai_analysis['risk_level'] ?? '') ?>"
                                                            data-ai-score="<?= htmlspecialchars($ai_analysis['explicit_percentage'] ?? 0) ?>"
                                                            onclick="if(typeof sendViewRequest === 'function') sendViewRequest('video', <?= (int)$sharedId ?>, <?= (int)$item['feed_item_id'] ?>)">

                                                            <div class="media-wrapper-<?= htmlspecialchars($item["feed_item_id"]) ?> <?= $should_blur ? 'media-blur-container' : '' ?>">
                                                                <?php if (!empty($sharedData['thumbnail_path'])): ?>
                                                                    <?= render_adult_content('<img src="' . UPLOAD_URL . htmlspecialchars($sharedData['thumbnail_path']) . '" class="post-image ' . ($should_blur ? 'media-blur' : '') . '" style="display: block; width: 100%;">', $sharedData) ?>
                                                                <?php else: ?>
                                                                    <video src="<?= UPLOAD_URL . htmlspecialchars($sharedData['video_path']) ?>"
                                                                        class="post-image <?= $should_blur ? 'media-blur' : '' ?>"
                                                                        style="width: 100%; display: block;"
                                                                        preload="metadata"
                                                                        muted></video>
                                                                <?php endif; ?>

                                                                <?php if ($should_blur): ?>
                                                                    <div class="media-overlay-msg">
                                                                        <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                                                        <p>Conteúdo Sensível <br><small>Detetado automaticamente pela IA</small></p>
                                                                        <button onclick="event.stopPropagation(); unblurMedia('<?= htmlspecialchars($item["feed_item_id"]) ?>')">Ver mesmo assim</button>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>

                                                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; font-size: 2rem; pointer-events: none;"><i class="fa-solid fa-play"></i></div>

                                                            <div class="video-stats" style="position: absolute; bottom: 0; left: 0; width: 100%; padding: 12px 8px; color: white; font-size: 0.9rem; opacity: 1; pointer-events: none; background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0.4) 50%, transparent 100%); z-index: 10;">
                                                                <i class="fa-solid fa-eye"></i> <?= number_format($sharedData['views_count'] ?? 0) ?> visualizações
                                                                <?php if (!$isForSaleShared): ?>
                                                                    <span style="margin-left: auto; background: rgba(0,255,0,0.3); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;">
                                                                        <i class="fa-solid fa-play-circle"></i> GrÃ¡tis
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <?php
                                                        $isVerifiedCreator = isset($logged_in_user_data['is_verified_creator']) ? $logged_in_user_data['is_verified_creator'] : 0;
                                                        $checkoutUrl = BASE_URL . 'checkout.php?type=video&id=' . $sharedId;
                                                        ?>
                                                        <div class="lightbox-trigger video-locked"
                                                            data-type="video"
                                                            data-id="<?= htmlspecialchars($item['feed_item_id']) ?>"
                                                            data-item-id="<?= htmlspecialchars($sharedId) ?>"
                                                            data-item-type="video"
                                                            data-src="<?= UPLOAD_URL . htmlspecialchars($sharedData['video_path']) ?>"
                                                            data-is-for-sale="true"
                                                            data-price="<?= $sharedData['price'] ?? 0 ?>"
                                                            data-has-access="false"
                                                            data-thumbnail="<?= UPLOAD_URL . htmlspecialchars($sharedData['thumbnail_path']) ?>"
                                                            data-duration="<?= $sharedData['duration'] ?? 0 ?>"
                                                            data-is-verified="<?= $isVerifiedCreator ? 'true' : 'false' ?>"
                                                            data-checkout-url="<?= htmlspecialchars($checkoutUrl) ?>"
                                                            style="cursor: pointer; position: relative;">

                                                            <img src="<?= UPLOAD_URL . htmlspecialchars($sharedData['thumbnail_path']) ?>"
                                                                class="album-cover-image"
                                                                style="filter: blur(10px); width: 100%; display: block;"
                                                                data-is-paid="true">

                                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; pointer-events: none;">
                                                                <span>
                                                                    <i class="fa-solid fa-play" style="margin-bottom: 10px; padding: 5px;"></i>
                                                                    <i class="fas fa-lock" style="margin-bottom: 10px; padding: 5px;"></i>
                                                                </span>
                                                                <div style="margin-top: 8px; color: #65676b;">
                                                                    <i class="fa-solid fa-eye" style="color: #65676b; font-size: 1rem; margin-left: 10px;"></i>
                                                                    <?= number_format($sharedData['views_count'] ?? 0) ?> visualizações
                                                                </div>
                                                                <p>Vídeo Pago: <?= number_format($sharedData['price'], 2, ',', '.') ?> MT</p>
                                                                <span style="font-size: 0.8em; text-decoration: underline;">
                                                                    <?= $isVerifiedCreator ? 'Clique para comprar' : 'Verifique sua conta para comprar' ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php elseif ($sharedType === 'album'): ?>
                                                    <div class="post-content">
                                                        <h2 class="album-title"><?= htmlspecialchars($sharedData['album_name'] ?? 'Álbum  sem Nome') ?></h2>
                                                        <?php if (!empty($sharedData['album_description'])): ?>
                                                            <p class="post-text"><?= nl2br(htmlspecialchars($sharedData['album_description'] ?? '')) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php
                                                    // $paymentService já  instanciado antes do loop
                                                    $hasAccessShared = $paymentService->hasAccess($current_user_id ?? 0, $sharedType, $sharedData['id'] ?? $sharedData['item_id'] ?? 0);

                                                    if (!empty($sharedData['cover_photo_url'])) {
                                                        if ($hasAccessShared) {
                                                            $album_thumb = !empty($sharedData['thumbnail_path']) ? $sharedData['thumbnail_path'] : $sharedData['cover_photo_url'];
                                                            $album_blur_class = $should_blur ? 'album-blur-container' : '';
                                                            echo '<div class="' . $album_blur_class . '" style="position: relative; display: block;">';
                                                            echo '<a href="' . BASE_URL . 'view_album.php?id=' . htmlspecialchars($sharedId) . '" class="album-placeholder-link album-cover-link" data-item-id="' . (int)$sharedId . '" data-item-type="album">';
                                                            echo '<img src="' . get_protected_media_url($album_thumb) . '" alt="Capa do Álbum " class="album-cover ' . ($should_blur ? 'album-blur' : '') . '" style="max-height: 360px;">';
                                                            echo '</a>';
                                                            if ($should_blur) {
                                                                echo '<div class="album-overlay-msg">';
                                                                echo '<i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>';
                                                                echo '<p>Conteúdo  Sensível <br><small>Detetado automaticamente pela IA</small></p>';
                                                                echo '<button onclick="event.stopPropagation(); unblurAlbum(this)">Ver mesmo assim</button>';
                                                                echo '</div>';
                                                            }
                                                            echo '</div>';
                                                        } elseif (isset($logged_in_user_data['is_verified_creator']) && $logged_in_user_data['is_verified_creator']) {
                                                            echo '<div class="album-locked" style="position: relative; cursor: pointer;" onclick="window.location.href=\'checkout.php?type=album&id=' . $sharedId . '\'">';
                                                            $album_thumb = !empty($sharedData['thumbnail_path']) ? $sharedData['thumbnail_path'] : $sharedData['cover_photo_url'];
                                                            echo '<img src="' . UPLOAD_URL . htmlspecialchars($album_thumb) . '" alt="Capa do Álbum " class="album-cover-image" style="filter: blur(8px); max-height: 360px;">';
                                                            echo '<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; background: rgba(0,0,0,0.4); border-radius: 8px;">';
                                                            echo '<i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>';
                                                            echo '<p>Álbum  Pago: ' . number_format($sharedData['price'], 2, ',', '.') . ' MT</p>';
                                                            echo '</div>';
                                                            echo '</div>';
                                                        } else {
                                                            echo '<div class="album-locked" style="position: relative; cursor: pointer;" onclick="openVerificationInviteModal()">';
                                                            $album_thumb = !empty($sharedData['thumbnail_path']) ? $sharedData['thumbnail_path'] : $sharedData['cover_photo_url'];
                                                            echo '<img src="' . UPLOAD_URL . htmlspecialchars($album_thumb) . '" alt="Capa do Álbum " class="album-cover-image" style="filter: blur(8px); max-height: 360px;">';
                                                            echo '<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; background: rgba(0,0,0,0.4); border-radius: 8px;">';
                                                            echo '<i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>';
                                                            echo '<p>Álbum  Pago: ' . number_format($sharedData['price'], 2, ',', '.') . ' MT</p>';
                                                            echo '</div>';
                                                            echo '</div>';
                                                        }
                                                    } else {
                                                        echo '<a href="' . BASE_URL . 'view_album.php?id=' . htmlspecialchars($sharedId) . '" class="album-placeholder-link">';
                                                        echo '<span class="overlay-text"></span>';
                                                        echo '</a>';
                                                    }
                                                    ?>

                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <?php if ($display_type === 'post'): ?>
                                                <?php if (!empty($content_data['content'])): ?>
                                                    <div class="post-content">
                                                        <p class="post-text"><?= nl2br(htmlspecialchars($content_data['content'])) ?></p>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($item['has_access']): ?>
                                                    <?php if (isset($content_data['post_type']) && $content_data['post_type'] === 'text'): ?>
                                                        <div class="post-text ql-editor"><?= $content_data['content'] ?></div>
                                                    <?php else: ?>
                                                        <?php if (!empty($content_data['image_path'])): ?>
                                                            <div class="media-wrapper-<?= htmlspecialchars($item['feed_item_id']) ?> <?= $should_blur ? 'media-blur-container' : '' ?>">
                                                                <div class="post-image-container" data-post-modal="<?= htmlspecialchars($item['feed_item_id']) ?>" style="cursor: pointer;">
                                                                    <?php $display_image = !empty($content_data['thumbnail_path']) ? $content_data['thumbnail_path'] : $content_data['image_path']; ?>
                                                                    <img src="<?= UPLOAD_URL . htmlspecialchars($display_image) ?>" alt="Imagem do Post" class="post-image <?= $should_blur ? 'media-blur' : '' ?>" data-is-paid="<?= (isset($content_data['is_for_sale']) && $content_data['is_for_sale']) ? 'true' : 'false' ?>">
                                                                </div>
                                                                <?php if ($should_blur): ?>
                                                                    <div class="media-overlay-msg">
                                                                        <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                                                        <p>Conteúdo Sensível <br><small>Detetado automaticamente pela IA</small></p>
                                                                        <button onclick="event.stopPropagation(); unblurMedia('<?= htmlspecialchars($item['feed_item_id']) ?>')">Ver mesmo assim</button>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php if (isset($logged_in_user_data['is_verified_creator']) && $logged_in_user_data['is_verified_creator']): ?>
                                                        <div class="post-locked" onclick="pageModalLoader.open('checkout.php?type=post&id=<?= $item['item_id'] ?>')">
                                                            <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path'] ?: $content_data['image_path']) ?>" alt="Imagem do Post" style="filter: blur(20px);" data-is-paid="<?= (isset($content_data['is_for_sale']) && $content_data['is_for_sale']) ? 'true' : 'false' ?>">
                                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff;">
                                                                <i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                                                                <p>Conteúdo Pago: <?= number_format($content_data['price'], 2, ',', '.') ?> MT</p>
                                                                <span style="font-size: 0.8em; text-decoration: underline;">Clique para comprar</span>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="post-locked" onclick="openVerificationInviteModal()">
                                                            <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path'] ?: $content_data['image_path']) ?>" alt="Imagem do Post" style="filter: blur(20px);" data-is-paid="<?= (isset($content_data['is_for_sale']) && $content_data['is_for_sale']) ? 'true' : 'false' ?>">
                                                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff;">
                                                                <i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>
                                                                <p>Conteúdo Pago: <?= number_format($content_data['price'], 2, ',', '.') ?> MT</p>
                                                                <span style="font-size: 0.8em; text-decoration: underline;">Clique para comprar</span>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php elseif ($display_type === 'video'): ?>
                                                <?php if (!empty($content_data['caption'])): ?>
                                                    <div class="post-content">
                                                        <p class="post-text"><?= nl2br(htmlspecialchars($content_data['caption'] ?? '')) ?></p>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($item['has_access']): ?>
                                                    <!-- VÍDEO COM ACESSO - Estrutura completa para lightbox -->
                                                    <div class="media-wrapper-<?= htmlspecialchars($item['feed_item_id']) ?> <?= $should_blur ? 'media-blur-container' : '' ?>">
                                                        <div class="video-locked lightbox-trigger"
                                                            data-type="video"
                                                            data-id="<?= htmlspecialchars($item['feed_item_id']) ?>"
                                                            data-item-id="<?= htmlspecialchars($item['item_id']) ?>"
                                                            data-item-type="video"
                                                            data-src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
                                                            data-is-for-sale="<?= (isset($content_data['is_for_sale']) && $content_data['is_for_sale']) ? 'true' : 'false' ?>"
                                                            data-price="<?= $content_data['price'] ?? 0 ?>"
                                                            data-has-access="true"
                                                            data-thumbnail="<?= UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path'] ?? '') ?>"
                                                            data-ai-status="<?= htmlspecialchars($ai_analysis['status'] ?? '') ?>"
                                                            data-ai-risk="<?= htmlspecialchars($ai_analysis['risk_level'] ?? '') ?>"
                                                            data-ai-score="<?= htmlspecialchars($ai_analysis['explicit_percentage'] ?? 0) ?>"
                                                            onclick="if(typeof sendViewRequest === 'function') sendViewRequest('video', <?= (int)$item['item_id'] ?>, <?= (int)$item['feed_item_id'] ?>)"
                                                            style="position: relative; overflow: hidden; cursor: pointer;">
                                                            <?php if (!empty($content_data['thumbnail_path'])): ?>
                                                                <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path']) ?>" class="post-image <?= $should_blur ? 'media-blur' : '' ?>" style="display: block; width: 100%;">
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
                                                                    <p>Conteúdo Sensível <br><small>Detetado automaticamente pela IA</small></p>
                                                                    <button onclick="event.stopPropagation(); unblurMedia('<?= htmlspecialchars($item['feed_item_id']) ?>')">Ver mesmo assim</button>
                                                                </div>
                                                            <?php endif; ?>
                                                            <!-- Overlay de Play -->
                                                            <div class="video-play-overlay" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 3rem; opacity: 0.9; pointer-events: none; background: rgba(0,0,0,0.3); border-radius: 50%; width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                                                                <i class="fa-solid fa-play" style="margin-left: 8px;"></i>
                                                            </div>
                                                            <!-- Stats -->
                                                            <div class="video-stats" style="position: absolute; bottom: 0; left: 0; width: 100%; padding: 12px 8px; color: white; font-size: 0.9rem; pointer-events: none; background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.4) 50%, transparent 100%); z-index: 10; display: flex; align-items: center; gap: 8px;">
                                                                <i class="fa-solid fa-eye"></i>
                                                                <span data-views-id="video-<?= (int)$item['item_id'] ?>"><?= number_format($content_data['views_count'] ?? 0) ?> visualizações </span>
                                                                <?php if (!(isset($content_data['is_for_sale']) && $content_data['is_for_sale'])): ?>
                                                                    <span style="margin-left: auto; z-index: 111000; background: rgba(0,255,0,0.3); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem;"><i class="fa-solid fa-play-circle"></i> GrÃ¡tis</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- VÍDEO BLOQUEADO Á VENDA -->
                                                    <?php
                                                    $isVerifiedCreator = isset($logged_in_user_data['is_verified_creator']) ? $logged_in_user_data['is_verified_creator'] : 0;
                                                    $checkoutUrl = BASE_URL . 'checkout.php?type=video&id=' . $item['item_id'];
                                                    ?>
                                                    <div class="lightbox-trigger video-locked"
                                                        data-type="video"
                                                        data-id="<?= htmlspecialchars($item['feed_item_id']) ?>"
                                                        data-item-id="<?= htmlspecialchars($item['item_id']) ?>"
                                                        data-item-type="video"
                                                        data-src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
                                                        data-is-for-sale="true"
                                                        data-price="<?= $content_data['price'] ?? 0 ?>"
                                                        data-has-access="false"
                                                        data-thumbnail="<?= UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path']) ?>"
                                                        data-duration="<?= $content_data['duration'] ?? 248 ?>"
                                                        data-is-verified="<?= $isVerifiedCreator ? 'true' : 'false' ?>"
                                                        data-checkout-url="<?= htmlspecialchars($checkoutUrl) ?>"
                                                        style="cursor: pointer; position: relative;">
                                                        <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path']) ?>"
                                                            class="album-cover-image"
                                                            style="filter: blur(10px); width: 100%; display: block;"
                                                            data-is-paid="true">
                                                        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; pointer-events: none;">
                                                            <span>
                                                                <i class="fa-solid fa-play" style="margin-bottom: 10px; padding: 5px;"></i>
                                                                <i class="fas fa-lock" style="margin-bottom: 10px; padding: 5px;"></i>
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
                                            <?php elseif ($display_type === 'album'): ?>
                                                <div class="post-content">
                                                    <h2 class="album-title"><?= htmlspecialchars($content_data['album_name'] ?? 'Álbum  sem Nome') ?></h2>
                                                    <?php if (!empty($content_data['album_description'])): ?>
                                                        <p class="post-text"><?= nl2br(htmlspecialchars($content_data['album_description'] ?? '')) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <?php
                                                // $paymentService já  instanciado antes do loop
                                                $hasAccess = $paymentService->hasAccess($current_user_id ?? 0, 'album', $item['item_id']);

                                                if (!empty($content_data['cover_photo_url'])) {
                                                    $album_thumb = !empty($content_data['thumbnail_path']) ? $content_data['thumbnail_path'] : $content_data['cover_photo_url'];

                                                    if ($hasAccess) {
                                                        $album_blur_class = $should_blur ? 'album-blur-container' : '';
                                                        echo '<div class="' . $album_blur_class . '" style="position: relative; display: block;">';
                                                        echo '<a href="' . BASE_URL . 'view_album.php?id=' . htmlspecialchars($item['item_id']) . '" class="album-placeholder-link album-cover-link" data-item-id="' . (int)$item['item_id'] . '" data-item-type="album">';
                                                        echo render_adult_content('<img src="' . get_protected_media_url($album_thumb) . '" alt="Capa do Álbum " class="album-cover-image ' . ($should_blur ? 'album-blur' : '') . '" style="height: 520px; object-fit: contain; width: 100%; display: block;">', $content_data);
                                                        echo '</a>';
                                                        if ($should_blur): ?>
                                                            <div class="album-overlay-msg">
                                                                <i class="fas fa-eye-slash" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                                                <p>Conteúdo Sensível <br><small>Detetado automaticamente pela IA</small></p>
                                                                <button onclick="event.stopPropagation(); unblurAlbum(this)">Ver mesmo assim</button>
                                                            </div>
                                                <?php endif;
                                                        echo '</div>';
                                                    } elseif (isset($logged_in_user_data['is_verified_creator']) && $logged_in_user_data['is_verified_creator']) {
                                                        echo '<div class="album-locked" data-track-type="album" data-track-id="' . (int)$item['item_id'] . '" onclick="pageModalLoader.open(\'checkout.php?type=album&id=' . $item['item_id'] . '\')">';
                                                        echo '<img src="' . UPLOAD_URL . htmlspecialchars($album_thumb) . '" alt="Capa do Álbum " class="album-cover-image" style="filter: blur(8px); max-height: 500px; object-fit: contain; width: 100%; display: block;">';
                                                        echo '<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; background: rgba(0,0,0,0.4); border-radius: 8px;">';
                                                        echo '<i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>';
                                                        echo '<p>Álbum  Pago: ' . number_format($content_data['price'], 2, ',', '.') . ' MT</p>';
                                                        echo '</div>';
                                                        echo '</div>';
                                                    } else {
                                                        echo '<div class="album-locked" data-track-type="album" data-track-id="' . (int)$item['item_id'] . '" onclick="openVerificationInviteModal()">';
                                                        echo '<img src="' . UPLOAD_URL . htmlspecialchars($album_thumb) . '" alt="Capa do Álbum " class="album-cover-image" style="filter: blur(8px); max-height: 350px; object-fit: contain; width: 100%; display: block;">';
                                                        echo '<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; background: rgba(0,0,0,0.4); border-radius: 8px;">';
                                                        echo '<i class="fas fa-lock" style="font-size: 2em; margin-bottom: 10px;"></i>';
                                                        echo '<p>Álbum  Pago: ' . number_format($content_data['price'], 2, ',', '.') . ' MT</p>';
                                                        echo '</div>';
                                                        echo '</div>';
                                                    }
                                                } else {
                                                    echo '<a href="' . BASE_URL . 'view_album.php?id=' . htmlspecialchars($item['item_id']) . '" class="album-placeholder-link album-cover-link" data-item-id="' . (int)$item['item_id'] . '" data-item-type="album">';
                                                    echo '<span class="overlay-text">Ver Álbum </span>';
                                                    echo '</a>';
                                                }
                                                ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="post-footer">
                                        <div class="post-actions">
                                            <?php if ($feed_item_id): ?>
                                                <!-- Like pill estilo YouTube -->
                                                <div class="yt-like-pill">
                                                    <button class="yt-action-btn btn-like <?= ($user_vote === 'like' ? 'active' : '') ?>"
                                                        data-feed-item-id="<?= htmlspecialchars($feed_item_id) ?>"
                                                        data-action="like"
                                                        title="Gosto">
                                                        <i class="fa-regular fa-star"></i>
                                                        <span class="likes-count"><?= $like_info['likes'] ?></span>
                                                    </button>
                                                </div>

                                                <!-- Comentar -->
                                                <a href="<?= BASE_URL ?>post.php?id=<?= htmlspecialchars($feed_item_id) ?>"
                                                    class="yt-action-btn yt-pill"
                                                    title="Comentar">
                                                    <i class="fa-regular fa-message"></i>
                                                    <span class="comment-count-display"><?= htmlspecialchars($comment_count) ?></span>
                                                </a>
                                            <?php else: ?>
                                                <!-- Álbum  de parceria - sem interaÃ§Ãµes de feed -->
                                                <div class="yt-like-pill">
                                                    <button class="yt-action-btn" disabled title="Parceria - interaÃ§Ãµes no Ã¡lbum original">
                                                        <i class="fa-regular fa-star"></i> <span class="likes-count">0</span>
                                                    </button>
                                                </div>
                                                <a href="<?= BASE_URL ?>view_album.php?id=<?= htmlspecialchars($item['item_id']) ?>" class="yt-action-btn yt-pill">
                                                    <i class="fa-regular fa-images"></i> Ver Álbum
                                                </a>
                                            <?php endif; ?>

                                            <?php
                                            // share_count já calculado no batch acima
                                            $current_post_id = $feed_item_id ?? $item['item_id'];
                                            $can_link   = $item['allow_share_link']   ?? 1;
                                            $can_repost = $item['allow_share_repost'] ?? 1;
                                            ?>

                                            <!-- Partilhar -->
                                            <div class="share-container" style="position: relative; display: inline-flex;">
                                                <button type="button"
                                                    class="yt-action-btn yt-pill"
                                                    onclick="event.stopPropagation(); toggleShareMenu(<?= (int)$current_post_id ?>)"
                                                    title="Partilhar">
                                                    <i class="fa-regular fa-paper-plane"></i>
                                                    <span id="share-count-<?= (int)$current_post_id ?>"><?= (int)$share_count ?></span>
                                                </button>

                                                <div id="share-menu-<?= (int)$current_post_id ?>"
                                                    class="share-dropdown"
                                                    style="display:none; position:absolute; bottom:calc(100% + 8px); left:50%; transform:translateX(-50%);
                                                                background:var(--bg-surface,#1a1a1a); border:1px solid var(--border,#333);
                                                                border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,.5);
                                                                z-index:9999; min-width:180px; padding:8px 0;">
                                                    <?php if ($can_link): ?>
                                                        <button type="button"
                                                            onclick="event.stopPropagation(); copyToClipboard('<?= BASE_URL ?>post.php?id=<?= (int)$current_post_id ?>', <?= (int)$current_post_id ?>)"
                                                            class="share-option-btn"
                                                            style="width:100%; text-align:left; padding:10px 16px; background:none; border:none; cursor:pointer;
                                                                           color:var(--text-main,#fff); font-size:.9rem; display:flex; align-items:center; gap:10px; transition:background .2s;">
                                                            <i class="fa-regular fa-link" style="width:16px;"></i> Copiar Link
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($can_repost && $feed_item_id): ?>
                                                        <button type="button"
                                                            onclick="event.stopPropagation(); handleRepost(<?= (int)$feed_item_id ?>)"
                                                            class="share-option-btn"
                                                            style="width:100%; text-align:left; padding:10px 16px; background:none; border:none; cursor:pointer;
                                                                           color:var(--text-main,#fff); font-size:.9rem; display:flex; align-items:center; gap:10px; transition:background .2s;">
                                                            <i class="fa-solid fa-retweet" style="width:16px;"></i> Repostar
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                        </div><!-- /.post-actions -->

                                        <?php
                                        $save_key   = $item['item_type'] . '_' . $item['item_id'];
                                        $is_saved   = isset($saved_ids[$save_key]);
                                        $save_label = $is_saved ? 'Guardado' : 'Guardar';
                                        $save_icon  = $is_saved ? 'fa-solid fa-bookmark' : 'fa-regular fa-bookmark';
                                        $save_class = $is_saved ? 'btn-save active' : 'btn-save';
                                        ?>
                                        <!-- Guardar (isolado Ã  direita) -->
                                        <button class="yt-action-btn yt-pill <?= $save_class ?>"
                                            data-item-type="<?= htmlspecialchars($item['item_type']) ?>"
                                            data-item-id="<?= (int)$item['item_id'] ?>"
                                            data-csrf="<?= htmlspecialchars($csrf_token) ?>"
                                            onclick="toggleSave(this)"
                                            title="<?= $save_label ?>">
                                            <i class="<?= $save_icon ?>"></i>
                                            <span><?= $save_label ?></span>
                                        </button>
                                        <div class="comment-section-full" data-feed-item-id="<?= htmlspecialchars($feed_item_id ?? '') ?>" style="display: none;">
                                            <!-- comentários ocultos no feed, exibidos apenas no Lightbox -->
                                        </div>
                                    </div>
                                </article>
                                <!-- Estrutura para Visualização  em GRID (Filtros EspecÃ­ficos) -->
                                <div class="grid-item-wrapper" data-type="<?= htmlspecialchars($display_type) ?>" style="display: none;">
                                    <div class="profile-grid-item 
                      <?= ($display_type === 'video' ? 'lightbox-trigger video-item grid-trigger' : 'grid-trigger') ?>"
                                        data-post-modal="<?= htmlspecialchars($feed_item_id ?? $item['item_id']) ?>"
                                        data-type="<?= $display_type ?>"
                                        style="position: relative; overflow: hidden;">
                                        <?php
                                        $grid_is_paid      = !empty($content_data['is_for_sale']);
                                        $grid_is_sensitive = !empty($content_data['is_sensitive'])
                                            || in_array($ai_analysis['risk_level'] ?? '', ['medium', 'high']);
                                        ?>
                                        <?php if ($display_type === 'video'): ?>
                                            <?php
                                            $grid_duration_s   = (int)($content_data['duration_seconds'] ?? 0);
                                            $grid_is_paid      = !empty($content_data['is_for_sale']);
                                            $grid_is_sensitive = !empty($content_data['is_sensitive'])
                                                || in_array($ai_analysis['risk_level'] ?? '', ['medium', 'high']);
                                            ?>

                                            <!-- Thumbnail preferida; fallback para tag <video> -->
                                            <?php if (!empty($content_data['thumbnail_path'])): ?>
                                                <img src="<?= UPLOAD_URL . htmlspecialchars($content_data['thumbnail_path']) ?>"
                                                    alt="thumbnail"
                                                    class="post-video <?= $should_blur ? 'media-blur' : '' ?>"
                                                    style="width:100%;height:100%;object-fit:cover;display:block;">
                                            <?php else: ?>
                                                <video
                                                    src="<?= UPLOAD_URL . htmlspecialchars($content_data['video_path']) ?>"
                                                    muted playsinline preload="metadata"
                                                    class="post-video  <?= $should_blur ? 'media-blur' : '' ?>">
                                                </video>
                                            <?php endif; ?>

                                            <!-- Badges topo-esquerdo: 18+, pago, qualidade -->
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

                                            <!-- Badge duração (baixo-direito) -->
                                            <?php if ($grid_duration_s > 0): ?>
                                                <div class="reel-duration">
                                                    <?= format_duration($grid_duration_s) ?>
                                                </div>
                                            <?php endif; ?>

                                            <div class="grid-item-overlay"><i class="fas fa-play"></i></div>
                                        <?php elseif ($display_type === 'album'): ?>
                                            <?php $grid_album_thumb = !empty($content_data['thumbnail_path']) ? $content_data['thumbnail_path'] : ($content_data['cover_photo_url'] ?? 'default_album.png'); ?>
                                            <img src="<?= UPLOAD_URL . htmlspecialchars($grid_album_thumb) ?>" alt="Álbum " class="grid-image <?= ($grid_is_sensitive || $grid_is_paid) ? 'media-blur' : '' ?>">
                                            <div class="grid-item-overlay"><i class="fas fa-images"></i></div>
                                        <?php else: ?>
                                            <?php $grid_img = !empty($content_data['thumbnail_path']) ? $content_data['thumbnail_path'] : ($content_data['image_path'] ?? 'default_post.png'); ?>
                                            <img src="<?= UPLOAD_URL . htmlspecialchars($grid_img) ?>" alt="Post" class="grid-image  <?= ($grid_is_sensitive || $grid_is_paid) ? 'media-blur' : '' ?>">
                                        <?php endif; ?>

                                        <?php if ($grid_is_paid && !$item['has_access']): ?>
                                            <div class="grid-explicit-blur-overlay" onclick="event.stopPropagation(); pageModalLoader.open('checkout.php?type=<?= $display_type ?>&id=<?= $item['item_id'] ?>')">
                                                <i class="fas fa-lock" style="font-size: 1.4rem; margin-bottom: 6px;"></i>
                                                <span style="font-size: 0.7rem;"><?= number_format($content_data['price'] ?? 0, 0, ',', '.') ?> MT</span>
                                            </div>
                                        <?php elseif ($grid_is_sensitive): ?>
                                            <div class="grid-blur-overlay" onclick="event.stopPropagation(); unblurGridItem(this)" style="position: absolute; inset: 0; background: rgba(0,0,0,0.45); display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; font-size: 0.7rem; gap: 4px; cursor: pointer; z-index: 10;">
                                                <i class="fas fa-eye-slash" style="font-size: 1.2rem;"></i>
                                                <span>Ver mesmo assim</span>
                                            </div>
                                        <?php elseif (!$item['has_access']): ?>
                                            <div class="grid-explicit-blur-overlay"><i class="fas fa-lock"></i></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-posts-message" style="grid-column: 1 / -1; padding: 60px; text-align: center; color: #666;">
                                <i class="fa-regular fa-folder-open" style="font-size: 3rem; color: #ccc; margin-bottom: 20px; display: block;"></i>
                                <p>Nenhuma Publicações encontrada.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="loadMoreContainer" style="text-align: center; margin: 20px 0; display: none;">
                        <button id="loadMoreBtn" class="btn btn-primary" style="background: var(--primary-gradient); border: none; padding: 10px 30px; border-radius: 20px; cursor: pointer;">Carregar Mais</button>
                    </div>
                </div>
            </div><!-- /profile-feed-col -->
        </section>
    </div>
</div>
<!-- Lightbox Premium para Feed (Facebook Reels Style) -->
<div id="feedLightbox" class="photo-lightbox-modal">
    <div class="close-lightbox" data-action="close-lightbox">
        <i class="fa-solid fa-xmark"></i>
    </div>

    <div class="photo-lightbox-content">
        <!-- NavegaÃ§Ã£o Esquerda (Desktop) -->
        <div class="reels-scroll-nav">
            <button class="scroll-nav-btn" onclick="scrollToReelByOffset(-1)">
                <i class="fa-solid fa-chevron-up"></i>
            </button>
            <button class="scroll-nav-btn" onclick="scrollToReelByOffset(1)">
                <i class="fa-solid fa-chevron-down"></i>
            </button>
        </div>

        <!-- Ãrea Central do Vídeo  -->
        <div class="photo-display-area">
            <div id="lightboxScrollContainer">
                <!-- Reels items injected via JS -->
            </div>
        </div>

        <!-- Sidebar Direita (comentários e Info) -->
        <div class="photo-sidebar">
            <div class="photo-sidebar-header">
                <h3>comentários</h3>
                <button class="sidebar-close-btn" data-action="close-sidebar" style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="photo-sidebar-body" id="lightboxCommentsArea">
                <!-- Comments injected via JS -->
            </div>

            <div class="photo-comment-form-area">
                <?php if (is_logged_in()): ?>
                    <form id="lightboxCommentForm" class="photo-comment-form">
                        <div class="comment-input-wrapper">
                            <input type="text" id="lightboxCommentInput" placeholder="Escreva um comentÃ¡rio..." autocomplete="off">
                            <button type="submit">Enviar</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="login-to-comment">FaÃ§a <a href="<?= BASE_URL ?>login.php">login</a> para comentar.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modais: Publicações , Verificação  -->
<?php if ($is_owner): ?>
    <?php require_once __DIR__ . '/../includes/publication-modals.php'; ?>
<?php endif; ?>

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
            Para acessar Conteúdo s pagos, comprar acessos ou vender publicaÃ§Ãµes,
            é necessÃ¡rio verificar sua conta primeiro.
            <br><br>
            A Verificação ajuda a manter a comunidade segura e aumenta
            a confianÃ§a entre os usuários.
        </p>
        <button class="invite-verify-btn" onclick="proceedToVerification()">
            Fazer Verificação
        </button>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/verificationmodal.php'; ?>



<!-- 1. VariÃ¡veis globais PRIMEIRO -->
<script>
    window.BASE_URL = "<?php echo BASE_URL; ?>";
    window.UPLOAD_URL = "<?php echo UPLOAD_URL; ?>";
    window.CURRENT_USER_ID = <?php echo is_logged_in() ? get_current_user_id() : 'null'; ?>;
    window.POST_OWNER_ID = <?php echo json_encode((int)$profile_user_id); ?>;
    window.IS_POST_OWNER = (window.CURRENT_USER_ID !== null && window.POST_OWNER_ID !== null && window.CURRENT_USER_ID == window.POST_OWNER_ID);
    window.CURRENT_USER_PROFILE_PICTURE = "<?php echo htmlspecialchars($_SESSION['user_profile_picture'] ?? UPLOAD_URL . 'profiles/default_profile.png'); ?>";
    window.IS_VERIFIED_CREATOR = <?php echo json_encode((bool)($logged_in_user_data['is_verified_creator'] ?? false)); ?>;
</script>

<!-- 2. Scripts de dependÃªncia -->
<script src="<?= BASE_URL ?>assets/js/core/common_notifications.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/notifications.js"></script>

<!-- 3. Main.js (tem toggleShareMenu, handleRepost, App.toggleFollow, etc.) -->
<script src="<?= BASE_URL ?>assets/js/core/main.js"></script>

<!-- 4. Comments, tracking e lightbox -->
<script src="<?= BASE_URL ?>assets/js/components/comments.js"></script>
<script src="<?= BASE_URL ?>assets/js/core/track_views.js"></script>
<script src="<?= BASE_URL ?>assets/js/components/premium_lightbox.js"></script>

<!-- 5. Profile page JS -->
<!-- 6. Profile page JS (filtros, unblur, modal Verificação , lightbox protection, modal hoist) â€” carregado por Ãºltimo -->
<script src="<?= BASE_URL ?>assets/js/pages/profile.js"></script>
<script src="<?= BASE_URL ?>assets/js/components/save.js"></script>
<script src="<?= BASE_URL ?>assets/js/pages/media-backdrop.js" defer></script>
<!-- Widgets: visÃ­veis apenas no filtro Feed -->



<?php require_once __DIR__ . '/../includes/profile-footer.php'; ?>