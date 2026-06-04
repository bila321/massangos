<?php

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/adult-content-helper.php';

SecurityManager::initSecurity();
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\User;

if (!is_logged_in()) {
    set_message("Você precisa estar logado para acessar o massangos.", "danger");
    redirect(BASE_URL . 'login.php');
}

$current_user_id = get_current_user_id();
$is_admin        = isset($_SESSION['admin_id']);

// Dados do usuário logado (necessário para IS_VERIFIED_CREATOR e acesso ao checkout)
$logged_in_user_data = [];
if (is_logged_in()) {
    $logged_in_user_data = User::getUserById($pdo, $current_user_id) ?? [];
}


// ── Paginação ─────────────────────────────────────────────────────────────────
$perPage = 20;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// ── Filtro ────────────────────────────────────────────────────────────────────
$allowedFilters = ['all', 'posts', 'photos', 'comments'];
$filter = in_array($_GET['filter'] ?? '', $allowedFilters, true)
    ? $_GET['filter']
    : 'all';

// ── Query UNION ───────────────────────────────────────────────────────────────
function buildReactionsQuery(PDO $pdo, int $userId, string $filter, int $limit, int $offset): array
{
    $parts  = [];
    $params = [];

    if ($filter === 'all' || $filter === 'posts') {
        $parts[] = "
            SELECT
                fil.id                AS reaction_id,
                fil.type              AS reaction_type,
                fil.created_at,
                'feed'                AS source,
                fi.item_type          AS content_type,
                fi.item_id            AS content_ref_id,
                fi.id                 AS feed_item_id,
                COALESCE(p.content, v.caption, a.name, '') AS content_preview,
                COALESCE(p.image_path, v.thumbnail_path, '') AS media_thumb,
                owner.username        AS owner_username,
                owner.profile_picture AS owner_avatar,
                owner.id              AS owner_id
            FROM feed_item_likes fil
            INNER JOIN feed_items fi ON fi.id = fil.feed_item_id
            INNER JOIN users owner   ON owner.id = fi.user_id
            LEFT  JOIN posts p       ON fi.item_type = 'post'  AND p.id = fi.item_id
            LEFT  JOIN videos v      ON fi.item_type = 'video' AND v.id = fi.item_id
            LEFT  JOIN albums a      ON fi.item_type = 'album' AND a.id = fi.item_id
            WHERE fil.user_id = :uid_feed
        ";
        $params[':uid_feed'] = $userId;
    }

    if ($filter === 'all' || $filter === 'photos') {
        $parts[] = "
            SELECT
                pl.id                 AS reaction_id,
                pl.type               AS reaction_type,
                pl.created_at,
                'photo'               AS source,
                'album_photo'         AS content_type,
                pl.photo_id           AS content_ref_id,
                NULL                  AS feed_item_id,
                ''                    AS content_preview,
                ap.photo_path         AS media_thumb,
                owner.username        AS owner_username,
                owner.profile_picture AS owner_avatar,
                owner.id              AS owner_id
            FROM photo_likes pl
            INNER JOIN album_photos ap ON ap.id = pl.photo_id
            INNER JOIN albums alb      ON alb.id = ap.album_id
            INNER JOIN users owner     ON owner.id = alb.user_id
            WHERE pl.user_id = :uid_photo
        ";
        $params[':uid_photo'] = $userId;
    }

    if ($filter === 'all' || $filter === 'comments') {
        $parts[] = "
            SELECT
                cv.id                 AS reaction_id,
                cv.vote_type          AS reaction_type,
                cv.created_at,
                'comment'             AS source,
                'comment'             AS content_type,
                cv.comment_id         AS content_ref_id,
                c.feed_item_id        AS feed_item_id,
                SUBSTRING(c.content, 1, 120) AS content_preview,
                ''                    AS media_thumb,
                owner.username        AS owner_username,
                owner.profile_picture AS owner_avatar,
                owner.id              AS owner_id
            FROM comment_votes cv
            INNER JOIN comments c  ON c.id = cv.comment_id
            INNER JOIN users owner ON owner.id = c.user_id
            WHERE cv.user_id = :uid_comment
        ";
        $params[':uid_comment'] = $userId;
    }

    if (empty($parts)) {
        return ['rows' => [], 'total' => 0];
    }

    $union = implode(' UNION ALL ', $parts);

    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM ({$union}) AS t");
    foreach ($params as $key => $val) {
        $stmtCount->bindValue($key, $val, PDO::PARAM_INT);
    }
    $stmtCount->execute();
    $total = (int) $stmtCount->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM ({$union}) AS t ORDER BY created_at DESC LIMIT :lim OFFSET :off");
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val, PDO::PARAM_INT);
    }
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
}

try {
    $result     = buildReactionsQuery($pdo, $current_user_id, $filter, $perPage, $offset);
    $reactions  = $result['rows'];
    $totalItems = $result['total'];
    $totalPages = (int) ceil($totalItems / $perPage);
} catch (PDOException $e) {
    error_log('[history.php] DB error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        // Em desenvolvimento, mostrar o erro real para facilitar debug
        $dbErrorDetail = $e->getMessage();
    }
    $reactions  = [];
    $totalItems = 0;
    $totalPages = 1;
    $dbError    = true;
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function reactionIcon(string $type): string
{
    return $type === 'like'
        ? '<i class="fa-solid fa-heart"></i>'
        : '<i class="fa-solid fa-thumbs-down"></i>';
}

function sourceLabel(string $source, string $contentType): string
{
    return match ($source) {
        'feed'    => match ($contentType) {
            'post'  => 'Publicação',
            'video' => 'Vídeo',
            'album' => 'Álbum',
            default => 'Conteúdo',
        },
        'photo'   => 'Foto',
        'comment' => 'Comentário',
        default   => 'Conteúdo',
    };
}

function sourceUrl(array $row): string
{
    return match ($row['source']) {
        'feed', 'comment' => BASE_URL . 'post.php?id=' . (int) ($row['feed_item_id'] ?? 0),
        'photo'           => BASE_URL . 'view_album.php?photo=' . (int) $row['content_ref_id'],
        default           => '#',
    };
}

function formatDate(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Agora mesmo';
    if ($diff < 3600)   return floor($diff / 60) . 'm atrás';
    if ($diff < 86400)  return floor($diff / 3600) . 'h atrás';
    if ($diff < 604800) return floor($diff / 86400) . 'd atrás';
    return date('d/m/Y', strtotime($datetime));
}

function avatarSrc(string $pic): string
{
    if ($pic === 'default_profile.png' || $pic === '') {
        return BASE_URL . 'assets/img/default_profile.png';
    }
    // profiles/xxx.jpg  → já tem o subdir
    return str_starts_with($pic, 'profiles/')
        ? BASE_URL . 'media-proxy.php?file=' . $pic
        : BASE_URL . 'media-proxy.php?file=profiles/' . $pic;
}

// ── Header do projecto (abre html, body, sidebar, .main-content, .feed-container)
include __DIR__ . '/../includes/header.php';
?>

<style>
    /* ─── Scoped: history.php ─────────────────────────────────────── */
    .history-page {
        min-height: 150vh;
        background: var(--bg-main);
        padding-top: var(--space-xl);
    }

    /* Cabeçalho da página */
    .history-page__header {
        display: flex;
        align-items: center;
        gap: var(--space-sm);
        margin-bottom: var(--space-lg);
    }

    .history-page__title {
        font-size: var(--text-xl);
        font-weight: var(--weight-bold);
        color: var(--text-main);
        margin: 0;
        line-height: var(--leading-tight);
    }

    .history-page__count {
        margin-left: auto;
        font-size: var(--text-xs);
        color: var(--text-muted);
        background: var(--bg-surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-full);
        padding: 3px 10px;
    }

    /* Filtros */
    .history-filters {
        display: flex;
        gap: var(--space-sm);
        flex-wrap: wrap;
        margin-bottom: var(--space-lg);
    }

    .history-filter {
        padding: 6px 14px;
        border-radius: var(--radius-full);
        border: 1px solid var(--border);
        background: var(--bg-card);
        color: var(--text-muted);
        font-size: var(--text-sm);
        font-weight: var(--weight-medium);
        text-decoration: none;
        transition: border-color .18s, color .18s, background .18s;
    }

    .history-filter:hover {
        border-color: var(--primary);
        color: var(--primary);
    }

    .history-filter--active {
        background: var(--primary);
        border-color: var(--primary);
        color: var(--text-on-primary);
    }

    /* Lista */
    .reaction-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: var(--space-sm);
    }

    /* Card */
    .reaction-card {
        display: grid;
        grid-template-columns: 40px 1fr auto;
        gap: var(--space-md);
        align-items: center;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: var(--space-md);
        text-decoration: none;
        color: inherit;
        transition: border-color .18s, box-shadow .18s;
    }

    .reaction-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow-md);
    }

    /* Badge ícone */
    .reaction-badge {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .reaction-badge--like {
        background: rgba(239, 68, 68, .12);
        color: #ef4444;
    }

    .reaction-badge--dislike {
        background: rgba(59, 130, 246, .12);
        color: var(--info);
    }

    /* Corpo */
    .reaction-body {
        min-width: 0;
    }

    .reaction-meta {
        display: flex;
        align-items: center;
        gap: var(--space-xs);
        flex-wrap: wrap;
        margin-bottom: 4px;
    }

    .reaction-meta__avatar {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }

    .reaction-meta__name {
        font-size: var(--text-sm);
        font-weight: var(--weight-semibold);
        color: var(--text-main);
    }

    .reaction-meta__badge {
        font-size: var(--fb-font-size-xsmall);
        padding: 1px 7px;
        border-radius: var(--radius-full);
        background: var(--bg-surface);
        color: var(--text-muted);
        border: 1px solid var(--border);
    }

    .reaction-preview {
        font-size: var(--fb-font-size-small);
        color: var(--text-muted);
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        margin: 0 0 4px;
        line-height: var(--leading-normal);
    }

    .reaction-time {
        font-size: var(--text-xs);
        color: var(--text-light);
    }

    /* Thumbnail */
    .reaction-thumb {
        width: 52px;
        height: 52px;
        border-radius: var(--radius-md);
        object-fit: cover;
        flex-shrink: 0;
        border: 1px solid var(--border);
    }

    .reaction-thumb--empty {
        width: 52px;
        height: 52px;
        border-radius: var(--radius-md);
        background: var(--bg-surface);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-light);
        flex-shrink: 0;
    }

    /* Empty state */
    .history-empty {
        text-align: center;
        padding: 60px var(--space-md);
        color: var(--text-muted);
    }

    .history-empty i {
        font-size: 2.5rem;
        opacity: .3;
        margin-bottom: var(--space-md);
        display: block;
    }

    .history-empty p {
        margin: 0;
        font-size: var(--text-sm);
    }

    /* Erro */
    .history-error {
        background: rgba(239, 68, 68, .08);
        border: 1px solid rgba(239, 68, 68, .25);
        color: #ef4444;
        border-radius: var(--radius-md);
        padding: var(--space-md);
        font-size: var(--text-sm);
        margin-bottom: var(--space-md);
        display: flex;
        align-items: center;
        gap: var(--space-sm);
    }

    /* Paginação */
    .history-pagination {
        display: flex;
        justify-content: center;
        gap: var(--space-xs);
        flex-wrap: wrap;
        margin-top: var(--space-2xl);
    }

    .page-btn {
        min-width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-md);
        border: 1px solid var(--border);
        background: var(--bg-card);
        color: var(--text-muted);
        font-size: var(--text-sm);
        text-decoration: none;
        padding: 0 10px;
        transition: border-color .15s, color .15s;
    }

    .page-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }

    .page-btn--active {
        background: var(--primary);
        border-color: var(--primary);
        color: var(--text-on-primary);
        font-weight: var(--weight-semibold);
        pointer-events: none;
    }

    .page-btn--disabled {
        opacity: .4;
        pointer-events: none;
    }
</style>

<div class="history-page">

    <!-- Cabeçalho -->
    <div class="history-page__header">
        <h1 class="history-page__title">
            <i class="fa-regular fa-clock" style="margin-right:6px; color:var(--primary)"></i>
            Histórico de Reações
        </h1>
        <span class="history-page__count"><?= number_format($totalItems) ?> reações</span>
    </div>

    <!-- Erro de BD -->
    <?php if (!empty($dbError)): ?>
        <div class="history-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            Não foi possível carregar o histórico. Tenta novamente mais tarde.
            <?php if (!empty($dbErrorDetail)): ?>
                <br><small style="opacity:.7;font-family:monospace"><?= htmlspecialchars($dbErrorDetail) ?></small>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <nav class="history-filters" aria-label="Filtro de reações">
        <?php
        $tabs = ['all' => 'Tudo', 'posts' => 'Publicações', 'photos' => 'Fotos', 'comments' => 'Comentários'];
        foreach ($tabs as $key => $label):
            $active = $filter === $key;
        ?>
            <a href="?filter=<?= urlencode($key) ?>&page=1"
                class="history-filter <?= $active ? 'history-filter--active' : '' ?>"
                <?= $active ? 'aria-current="page"' : '' ?>>
                <?= htmlspecialchars($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Lista -->
    <?php if (empty($reactions)): ?>
        <div class="history-empty">
            <i class="fa-regular fa-heart"></i>
            <p>Ainda não reagiste a nenhum conteúdo.</p>
        </div>

    <?php else: ?>
        <ol class="reaction-list">
            <?php foreach ($reactions as $r):
                $rType   = $r['reaction_type'] ?? 'like';
                $source  = $r['source'];
                $label   = sourceLabel($source, $r['content_type'] ?? '');
                $url     = sourceUrl($r);
                $preview = trim($r['content_preview'] ?? '');
                $thumb   = trim($r['media_thumb'] ?? '');
                $name    = htmlspecialchars($r['owner_username'] ?? 'Utilizador');
                $avatar  = avatarSrc($r['owner_avatar'] ?? '');
                $thumbUrl = $thumb !== '' ? BASE_URL . 'media-proxy.php?file=' . ltrim($thumb, '/') : '';
            ?>
                <li>
                    <a href="<?= htmlspecialchars($url) ?>" class="reaction-card">

                        <!-- Ícone reação -->
                        <div class="reaction-badge reaction-badge--<?= htmlspecialchars($rType) ?>">
                            <?= reactionIcon($rType) ?>
                        </div>

                        <!-- Corpo -->
                        <div class="reaction-body">
                            <div class="reaction-meta">
                                <img src="<?= htmlspecialchars($avatar) ?>"
                                    alt="<?= $name ?>"
                                    class="reaction-meta__avatar"
                                    loading="lazy"
                                    onerror="this.src='<?= BASE_URL ?>assets/img/default_profile.png'">
                                <span class="reaction-meta__name"><?= $name ?></span>
                                <span class="reaction-meta__badge"><?= htmlspecialchars($label) ?></span>
                            </div>

                            <?php if ($preview !== ''): ?>
                                <p class="reaction-preview"><?= htmlspecialchars($preview) ?></p>
                            <?php endif; ?>

                            <time class="reaction-time" datetime="<?= htmlspecialchars($r['created_at']) ?>">
                                <?= formatDate($r['created_at']) ?>
                            </time>
                        </div>

                        <!-- Miniatura -->
                        <?php if ($thumbUrl !== ''): ?>
                            <img src="<?= htmlspecialchars($thumbUrl) ?>"
                                alt="Miniatura"
                                class="reaction-thumb"
                                loading="lazy"
                                onerror="this.style.display='none'">
                        <?php else: ?>
                            <div class="reaction-thumb--empty" aria-hidden="true">
                                <i class="fa-regular fa-image"></i>
                            </div>
                        <?php endif; ?>

                    </a>
                </li>
            <?php endforeach; ?>
        </ol>

        <!-- Paginação -->
        <?php if ($totalPages > 1):
            $buildUrl = static fn(int $p): string => '?filter=' . urlencode($filter) . '&page=' . $p;
            $range    = range(max(1, $page - 2), min($totalPages, $page + 2));
        ?>
            <nav class="history-pagination" aria-label="Paginação">

                <a href="<?= $page > 1 ? htmlspecialchars($buildUrl($page - 1)) : '#' ?>"
                    class="page-btn <?= $page <= 1 ? 'page-btn--disabled' : '' ?>"
                    aria-label="Anterior">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>

                <?php if (!in_array(1, $range, true)):
                    echo '<a href="' . htmlspecialchars($buildUrl(1)) . '" class="page-btn">1</a>';
                    if ($range[0] > 2) echo '<span class="page-btn page-btn--disabled">…</span>';
                endif; ?>

                <?php foreach ($range as $p): ?>
                    <a href="<?= htmlspecialchars($buildUrl($p)) ?>"
                        class="page-btn <?= $p === $page ? 'page-btn--active' : '' ?>"
                        <?= $p === $page ? 'aria-current="page"' : '' ?>>
                        <?= $p ?>
                    </a>
                <?php endforeach; ?>

                <?php if (!in_array($totalPages, $range, true)):
                    if (end($range) < $totalPages - 1) echo '<span class="page-btn page-btn--disabled">…</span>';
                    echo '<a href="' . htmlspecialchars($buildUrl($totalPages)) . '" class="page-btn">' . $totalPages . '</a>';
                endif; ?>

                <a href="<?= $page < $totalPages ? htmlspecialchars($buildUrl($page + 1)) : '#' ?>"
                    class="page-btn <?= $page >= $totalPages ? 'page-btn--disabled' : '' ?>"
                    aria-label="Próxima">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>

            </nav>
        <?php endif; ?>

    <?php endif; ?>

</div><!-- .history-page -->

<?php
// fecha: .feed-container > .content-wrapper > .main-content > .app-container > </body></html>
// + inclui verificationmodal + scripts
include __DIR__ . '/../includes/footer.php';
?>