<?php
define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
SecurityManager::initSecurity();
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\User;
use Massango\Models\Post;
use Massango\Models\Video;
use Massango\Models\Album;
use Massango\Models\Like;
use Massango\Models\Comment;

$current_user_id = get_current_user_id();
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'all'; // all, album, video, photo, profile
$price_filter = isset($_GET['price']) ? $_GET['price'] : 'all'; // all, free, paid

$results = [];
$user_results = [];

if (!empty($query) || $type !== 'all' || $price_filter !== 'all') {

    // 1. Busca de Usuários (Perfis)
    if (($type === 'all' || $type === 'profile') && !empty($query)) {
        $stmt_users = $pdo->prepare("SELECT id, username, profile_picture, bio FROM users WHERE username LIKE ? OR bio LIKE ? LIMIT 20");
        $search_term = "%$query%";
        $stmt_users->execute([$search_term, $search_term]);
        $raw_user_results = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

        foreach ($raw_user_results as $u) {
            // FILTRO DE BLOQUEIO: Se o usuário logado estiver bloqueado pelo usuário encontrado ou vice-versa, oculta
            if (is_logged_in() && $current_user_id != $u['id']) {
                $is_blocked_by_me = User::isBlocking($pdo, $current_user_id, $u['id']);
                $am_i_blocked = User::isBlocking($pdo, $u['id'], $current_user_id);
                if ($is_blocked_by_me || $am_i_blocked) {
                    continue;
                }
            }
            $user_results[] = $u;
        }
    }

    // 2. Busca de Conteúdo
    if ($type !== 'profile') {
        $sql = "SELECT fi.id as feed_item_id, fi.item_type, fi.item_id, fi.user_id, fi.created_at 
                FROM feed_items fi 
                JOIN users u ON fi.user_id = u.id 
                WHERE fi.show_in_feed = 1"; // Apenas itens marcados para aparecer no feed podem ser pesquisados
        $params = [];

        if ($type !== 'all') {
            $sql .= " AND fi.item_type = ?";
            $params[] = ($type === 'photo') ? 'post' : $type;
        }

        $sql .= " ORDER BY fi.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rawItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rawItems as $item) {
            $detailedItem = null;
            if ($item['item_type'] === 'post') {
                $detailedItem = Post::getPostById($pdo, $item['item_id']);
                $detailedItem['search_text'] = $detailedItem['content'] ?? '';
                $detailedItem['is_paid'] = 0;
            } elseif ($item['item_type'] === 'video') {
                $detailedItem = Video::getVideoById($pdo, $item['item_id']);
                $detailedItem['search_text'] = $detailedItem['caption'] ?? '';
                $detailedItem['is_paid'] = isset($detailedItem['is_for_sale']) ? $detailedItem['is_for_sale'] : 0;
            } elseif ($item['item_type'] === 'album') {
                $detailedItem = Album::getAlbumById($pdo, $item['item_id']);
                $detailedItem['search_text'] = ($detailedItem['album_name'] ?? '') . ' ' . ($detailedItem['album_description'] ?? '');
                $detailedItem['is_paid'] = isset($detailedItem['is_for_sale']) ? $detailedItem['is_for_sale'] : 0;
            }

            if ($detailedItem) {
                if (!empty($query)) {
                    if (stripos($detailedItem['search_text'], $query) === false && stripos($item['item_type'], $query) === false) {
                        continue;
                    }
                }

                if ($price_filter === 'free' && $detailedItem['is_paid']) continue;
                if ($price_filter === 'paid' && !$detailedItem['is_paid']) continue;

                $is_approved = isset($detailedItem['is_approved']) ? (int)$detailedItem['is_approved'] : 1;
                $is_owner = ($current_user_id > 0 && (int)$item['user_id'] === $current_user_id);
                $is_admin = isset($_SESSION['admin_id']);
                if (!$is_approved && !$is_owner && !$is_admin) continue;

                // FILTRO DE PRIVACIDADE (LINK PRIVADO): Se show_in_feed for 0, só mostra para o dono, admin ou quem já pagou
                $show_in_feed = isset($detailedItem['show_in_feed']) ? (int)$detailedItem['show_in_feed'] : 1;
                if ($show_in_feed === 0 && !$is_owner && !$is_admin) {
                    $paymentService = new \Massango\Services\PaymentService($pdo);
                    $hasAccess = $paymentService->hasAccess($current_user_id ?? 0, $item['item_type'], $item['item_id']);
                    if (!$hasAccess) {
                        continue;
                    }
                }

                $author = User::getUserById($pdo, $item['user_id']);

                // FILTRO DE PRIVACIDADE: Se o perfil for privado (followers), verifica se o usuário logado segue o autor ou se é mútuo
                $author_privacy = $author['profile_privacy'] ?? 'public';
                if ($author_privacy === 'followers' && !$is_owner && !$is_admin) {
                    if (!is_logged_in()) {
                        continue;
                    }
                    $is_following_author = User::isFollowing($pdo, $current_user_id, $author['id']);
                    $is_mutual_with_author = User::isMutualFollower($pdo, $current_user_id, $author['id']);
                    if (!$is_following_author && !$is_mutual_with_author) {
                        continue;
                    }
                }

                // FILTRO DE BLOQUEIO: Se o usuário logado estiver bloqueado pelo autor ou vice-versa, oculta
                if (is_logged_in() && !$is_owner) {
                    $is_blocked_by_me = User::isBlocking($pdo, $current_user_id, $author['id']);
                    $am_i_blocked = User::isBlocking($pdo, $author['id'], $current_user_id);
                    if ($is_blocked_by_me || $am_i_blocked) {
                        continue;
                    }
                }

                $results[] = array_merge($item, $detailedItem, ['author' => $author]);
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/index_layout.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/forgot_password.css">

<div class="search-page-container">
    <div class="search-header">
        <h2>Resultados da Pesquisa</h2>
        <?php if (!empty($query)): ?>
            <p class="results-count">Mostrando resultados para "<strong><?= htmlspecialchars($query) ?></strong>"</p>
        <?php endif; ?>
    </div>

    <form action="" method="GET" class="filter-bar">
        <input type="hidden" name="q" value="<?= htmlspecialchars($query) ?>">

        <div class="filter-group">
            <label>Tipo de Conteúdo</label>
            <select name="type" class="filter-select" onchange="this.form.submit()">
                <option value="all" <?= $type == 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="profile" <?= $type == 'profile' ? 'selected' : '' ?>>Perfis (Usuários)</option>
                <option value="album" <?= $type == 'album' ? 'selected' : '' ?>>Álbuns</option>
                <option value="video" <?= $type == 'video' ? 'selected' : '' ?>>Vídeos</option>
                <option value="photo" <?= $type == 'photo' ? 'selected' : '' ?>>Fotos</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Preço</label>
            <select name="price" class="filter-select" onchange="this.form.submit()" <?= $type == 'profile' ? 'disabled' : '' ?>>
                <option value="all" <?= $price_filter == 'all' ? 'selected' : '' ?>>Todos</option>
                <option value="free" <?= $price_filter == 'free' ? 'selected' : '' ?>>Grátis</option>
                <option value="paid" <?= $price_filter == 'paid' ? 'selected' : '' ?>>Prémio (Pago)</option>
            </select>
        </div>

        <div class="filter-group" style="margin-left: auto;">
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
    </form>

    <div class="results-list">

        <!-- Exibição de Usuários -->
        <?php if (!empty($user_results)): ?>
            <h3 class="section-title"><i class="fas fa-users"></i> Perfis Encontrados</h3>
            <div class="user-results-grid">
                <?php foreach ($user_results as $u): ?>
                    <div class="user-search-card">
                        <img src="<?= UPLOAD_URL . htmlspecialchars($u['profile_picture'] ?? 'profiles/default_profile.png') ?>" alt="<?= htmlspecialchars($u['username']) ?>">
                        <h4><?= htmlspecialchars($u['username']) ?></h4>
                        <p><?= htmlspecialchars($u['bio'] ?? 'Sem biografia disponível.') ?></p>
                        <a href="<?= BASE_URL ?>profile.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-primary btn-block">Ver Perfil</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Exibição de Conteúdo -->
        <?php if (!empty($results)): ?>
            <?php if ($type === 'all'): ?>
                <h3 class="section-title"><i class="fas fa-stream"></i> Publicações e Mídia</h3>
            <?php endif; ?>

            <?php foreach ($results as $item): ?>
                <?php $author = $item['author']; ?>
                <article class="post-card card" style="margin-bottom: 20px;">
                    <div class="post-header">
                        <img src="<?= UPLOAD_URL . htmlspecialchars($author['profile_picture'] ?? 'default_profile.png') ?>" alt="Foto de perfil" class="profile-thumb">
                        <div class="post-info">
                            <a href="<?= BASE_URL ?>profile.php?id=<?= htmlspecialchars($author['id']) ?>" class="post-author"><?= htmlspecialchars($author['username']) ?></a>
                            <span class="post-date"><?= format_datetime_ago($item['created_at']) ?></span>
                        </div>
                        <div style="margin-left: auto;">
                            <span class="badge-price" style="background-color: <?= $item['is_paid'] ? '#f59e0b' : '#10b981' ?>;">
                                <?= $item['is_paid'] ? 'PRÉMIO' : 'GRÁTIS' ?>
                            </span>
                        </div>
                    </div>

                    <div class="post-content" style="padding: 15px 0;">
                        <?php if ($item['item_type'] === 'post'): ?>
                            <p><?= nl2br(htmlspecialchars($item['content'])) ?></p>
                            <?php if (!empty($item['image_path'])): ?>
                                <img src="<?= UPLOAD_URL . htmlspecialchars($item['image_path']) ?>" style="width: 100%; border-radius: 8px; margin-top: 10px;">
                            <?php endif; ?>
                        <?php elseif ($item['item_type'] === 'video'): ?>
                            <p><?= nl2br(htmlspecialchars($item['caption'])) ?></p>
                            <div class="video-preview" style="position: relative; background: #000; border-radius: 8px; overflow: hidden; aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center;">
                                <?php if (!empty($item['thumbnail_path'])): ?>
                                    <img src="<?= UPLOAD_URL . htmlspecialchars($item['thumbnail_path']) ?>" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.6;">
                                <?php endif; ?>
                                <i class="fas fa-play-circle" style="position: absolute; font-size: 3rem; color: #fff;"></i>
                            </div>
                        <?php elseif ($item['item_type'] === 'album'): ?>
                            <h4><?= htmlspecialchars($item['album_name']) ?></h4>
                            <p><?= nl2br(htmlspecialchars($item['album_description'])) ?></p>
                            <div class="album-preview" style="display: grid; grid-template-columns: 1fr 1fr; gap: 5px; margin-top: 10px;">
                                <?php if (!empty($item['cover_photo_url'])): ?>
                                    <?php $album_thumb = $item['thumbnail_path'] ?? $item['cover_photo_url']; ?>
                                    <img src="<?= UPLOAD_URL . htmlspecialchars($album_thumb) ?>" style="width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 8px;">
                                <?php endif; ?>
                                <div style="background: var(--surface-bg); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--text-secondary);">
                                    <i class="fas fa-images" style="font-size: 1.5rem;"></i>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="post-footer" style="border-top: 1px solid var(--border-light); padding-top: 10px; display: flex; justify-content: space-between; gap: 10px;">
                        <a href="<?= BASE_URL ?>index.php#feed-item-<?= $item['feed_item_id'] ?>" class="btn btn-sm btn-secondary" style="flex: 1;">Ver no Feed</a>

                        <?php if ($item['is_paid']): ?>
                            <a href="<?= BASE_URL ?>checkout.php?type=<?= $item['item_type'] ?>&id=<?= $item['item_id'] ?>" class="btn btn-sm btn-primary" style="flex: 1;">Comprar Acesso</a>
                        <?php else: ?>
                            <?php if ($item['item_type'] === 'album'): ?>
                                <a href="<?= BASE_URL ?>view_album.php?id=<?= $item['item_id'] ?>" class="btn btn-sm btn-primary" style="flex: 1;">Ver Álbum</a>
                            <?php else: ?>
                                <a href="<?= BASE_URL ?>post.php?id=<?= $item['feed_item_id'] ?>" class="btn btn-sm btn-primary" style="flex: 1;">Ver Detalhes</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (empty($user_results) && empty($results)): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <p>Nenhum resultado encontrado para sua pesquisa.</p>
                <a href="<?= BASE_URL ?>index.php" class="btn btn-primary" style="margin-top: 15px;">Voltar ao Início</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/header.php'; ?>