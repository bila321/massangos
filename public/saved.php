<?php

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

ob_end_clean();

if (!is_logged_in()) {
    redirect(BASE_URL . 'login.php');
}

$current_user_id = get_current_user_id();

// Filtro por tipo
$filter          = $_GET['type'] ?? 'all';
$allowed_filters = ['all', 'post', 'video', 'album', 'reel'];
if (!in_array($filter, $allowed_filters, true)) $filter = 'all';

// Paginação
$per_page = 24;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

// ── CORREÇÃO PRINCIPAL ────────────────────────────────────────────────────
// Problema 1: $type_condition usava "sp.item_type" — o alias "sp" só existe
//             na query principal, não na query de contagem.
// Problema 2: $pdo->quote() para interpolação directa é inseguro e desnecessário
//             quando podemos usar parâmetros bind.
// Solução: usar um parâmetro nomeado :type_filter e bind condicional.
// ─────────────────────────────────────────────────────────────────────────

$use_type_filter = ($filter !== 'all');

// ── Query principal ───────────────────────────────────────────────────────
$sql = "
    SELECT
        sp.id           AS save_id,
        sp.item_type,
        sp.item_id,
        sp.created_at   AS saved_at,

        -- Post
        p.image_path            AS post_thumb,
        p.video_thumbnail_path  AS post_video_thumb,
        p.content               AS post_content,
        p.price                 AS post_price,
        p.is_for_sale           AS post_for_sale,
        p.post_type,

        -- Video
        v.thumbnail_path        AS video_thumb,
        v.caption               AS video_caption,
        v.price                 AS video_price,
        v.is_for_sale           AS video_for_sale,

        -- Album
        a.thumbnail_path        AS album_thumb,
        a.cover_photo_url       AS album_cover,
        a.name                  AS album_name,
        a.price                 AS album_price,
        a.is_for_sale           AS album_for_sale,

        -- Autor
        u.username,
        u.profile_picture,
        u.id AS author_id

    FROM saved_posts sp
    LEFT JOIN posts  p ON sp.item_type IN ('post','reel') AND sp.item_id = p.id
    LEFT JOIN videos v ON sp.item_type = 'video'          AND sp.item_id = v.id
    LEFT JOIN albums a ON sp.item_type = 'album'          AND sp.item_id = a.id
    LEFT JOIN users  u ON (
        CASE sp.item_type
            WHEN 'post'  THEN p.user_id
            WHEN 'reel'  THEN p.user_id
            WHEN 'video' THEN v.user_id
            WHEN 'album' THEN a.user_id
        END
    ) = u.id
    WHERE sp.user_id = :uid
" . ($use_type_filter ? "AND sp.item_type = :item_type" : "") . "
    ORDER BY sp.created_at DESC
    LIMIT :lim OFFSET :off
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':uid', $current_user_id, PDO::PARAM_INT);
$stmt->bindValue(':lim', $per_page,        PDO::PARAM_INT);
$stmt->bindValue(':off', $offset,          PDO::PARAM_INT);
if ($use_type_filter) {
    $stmt->bindValue(':item_type', $filter, PDO::PARAM_STR);
}
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Query de contagem — SEM alias, coluna directa ─────────────────────────
$count_sql = "
    SELECT COUNT(*)
    FROM saved_posts
    WHERE user_id = :uid
" . ($use_type_filter ? "AND item_type = :item_type" : "");

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->bindValue(':uid', $current_user_id, PDO::PARAM_INT);
if ($use_type_filter) {
    $count_stmt->bindValue(':item_type', $filter, PDO::PARAM_STR);
}
$count_stmt->execute();
$total       = (int)$count_stmt->fetchColumn();
$total_pages = (int)ceil($total / $per_page);

// ── Análise de IA por item ────────────────────────────────────────────────
// Pré-carrega a análise de IA para todos os itens guardados de uma vez (evita N+1 queries)
$ai_analysis_map = [];
if (!empty($items)) {
    // Agrupa por tipo para fazer queries eficientes
    $ids_by_type = [];
    foreach ($items as $it) {
        $ids_by_type[$it['item_type']][] = (int)$it['item_id'];
    }
    foreach ($ids_by_type as $type => $ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $q = $pdo->prepare(
            "SELECT post_id, risk_level, status, explicit_percentage
             FROM media_analysis
             WHERE post_id IN ($placeholders) AND type = ?
             ORDER BY id DESC"
        );
        $q->execute([...$ids, $type]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            // Guarda apenas a primeira ocorrência (mais recente) por post_id
            $key = $type . '_' . $row['post_id'];
            if (!isset($ai_analysis_map[$key])) {
                $ai_analysis_map[$key] = $row;
            }
        }
    }
}

// Verifica se o utilizador atual é admin
$is_admin = isset($_SESSION['admin_id']);

// ── Helpers ───────────────────────────────────────────────────────────────
if (!function_exists('get_item_thumb')) {
    function get_item_thumb(array $item): string
    {
        return match ($item['item_type']) {
            'post'  => $item['post_video_thumb'] ?: $item['post_thumb'] ?: '',
            'reel'  => $item['post_video_thumb'] ?: $item['post_thumb'] ?: '',
            'video' => $item['video_thumb'] ?: '',
            'album' => $item['album_thumb'] ?: $item['album_cover'] ?: '',
            default => '',
        };
    }
}

if (!function_exists('get_item_url')) {
    function get_item_url(array $item): string
    {
        return match ($item['item_type']) {
            'post'  => BASE_URL . 'post.php?id='       . $item['item_id'],
            'reel'  => BASE_URL . 'reels.php?id='      . $item['item_id'],
            'video' => BASE_URL . 'post.php?id='       . $item['item_id'],
            'album' => BASE_URL . 'view_album.php?id=' . $item['item_id'],
            default => '#',
        };
    }
}

if (!function_exists('get_type_icon')) {
    function get_type_icon(string $type): string
    {
        return match ($type) {
            'post'  => 'fa-image',
            'reel'  => 'fa-clapperboard',
            'video' => 'fa-play',
            'album' => 'fa-images',
            default => 'fa-bookmark',
        };
    }
}

// CSRF — usa o token já existente na sessão (gerado pelo SecurityManager)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<style>
    .saved-page {
        min-height: 150vh;
        background: var(--bg-main);
        padding-top: var(--space-xl);
    }

    .saved-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .saved-header h1 {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .saved-header h1 i {
        color: var(--primary-color);
    }

    .saved-count {
        font-size: 0.85rem;
        color: var(--text-muted);
        font-weight: 500;
    }

    .saved-filters {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .saved-filter-btn {
        padding: 6px 16px;
        border-radius: 20px;
        border: 1px solid var(--border);
        background: none;
        color: var(--text-muted);
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .saved-filter-btn:hover,
    .saved-filter-btn.active {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }

    .saved-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 3px;
    }

    @media (max-width: 600px) {
        .saved-grid {
            gap: 2px;
        }
    }

    .saved-grid-item {
        position: relative;
        aspect-ratio: 1;
        overflow: hidden;
        background: var(--surface-bg);
        cursor: pointer;
    }

    .saved-grid-item:hover .saved-item-overlay {
        opacity: 1;
    }

    .saved-grid-thumb {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .saved-grid-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: var(--text-muted);
        font-size: 0.8rem;
        padding: 8px;
        text-align: center;
    }

    .saved-grid-placeholder i {
        font-size: 1.8rem;
        opacity: 0.5;
    }

    .saved-type-badge {
        position: absolute;
        top: 6px;
        left: 6px;
        background: rgba(0, 0, 0, 0.55);
        color: #fff;
        border-radius: 50%;
        width: 26px;
        height: 26px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
    }

    .saved-price-badge {
        position: absolute;
        top: 6px;
        right: 36px;
        background: rgba(0, 0, 0, 0.65);
        color: #fff;
        border-radius: 12px;
        padding: 2px 8px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .saved-item-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.7) 0%, transparent 60%);
        opacity: 0;
        transition: opacity 0.2s;
        display: flex;
        align-items: flex-end;
        padding: 8px;
    }

    .saved-item-meta {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #fff;
        font-size: 0.75rem;
    }

    .saved-item-meta img {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        object-fit: cover;
    }

    .saved-unsave-btn {
        position: absolute;
        top: 4px;
        right: 4px;
        background: rgba(0, 0, 0, 0.55);
        border: none;
        color: #fff;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        transition: background 0.2s;
    }

    .saved-unsave-btn:hover {
        background: rgba(220, 38, 38, 0.85);
    }

    .saved-empty {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }

    .saved-empty i {
        font-size: 3rem;
        margin-bottom: 16px;
        color: var(--primary-color);
        opacity: 0.5;
        display: block;
    }

    .saved-empty h3 {
        font-size: 1.1rem;
        margin-bottom: 8px;
        color: var(--text-primary);
    }

    .saved-pagination {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 32px;
        flex-wrap: wrap;
    }

    .saved-pagination a {
        padding: 6px 14px;
        border-radius: 8px;
        border: 1px solid var(--border);
        color: var(--text-primary);
        text-decoration: none;
        font-size: 0.85rem;
        transition: all 0.2s;
    }

    .saved-pagination a:hover,
    .saved-pagination a.active {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }

    /* ── Sistema de Blur (conteúdo sensível) ─────────────────────── */
    .media-blur {
        filter: blur(18px);
        transform: scale(1.05);
        /* evita bordas brancas após blur */
    }

    .media-blur-container {
        position: relative;
        overflow: hidden;
    }

    .media-overlay-msg {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.45);
        color: #fff;
        text-align: center;
        padding: 8px;
        z-index: 10;
        gap: 4px;
    }

    .media-overlay-msg i {
        font-size: 1.6rem;
    }

    .media-overlay-msg p {
        font-size: 0.72rem;
        margin: 0;
        line-height: 1.3;
    }

    .media-overlay-msg small {
        font-size: 0.65rem;
        opacity: 0.8;
    }

    .media-overlay-msg button {
        margin-top: 6px;
        padding: 4px 10px;
        font-size: 0.7rem;
        border: 1px solid rgba(255, 255, 255, 0.7);
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
        border-radius: 20px;
        cursor: pointer;
        backdrop-filter: blur(4px);
        transition: background 0.2s;
    }

    .media-overlay-msg button:hover {
        background: rgba(255, 255, 255, 0.3);
    }
</style>




<div class="saved-page">

    <div class="saved-header">
        <h1><i class="fa-solid fa-bookmark"></i> Guardados</h1>
        <span class="saved-count"><?= number_format($total) ?> item<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <div class="saved-filters">
        <?php
        $filters = [
            'all'   => ['icon' => 'fa-th',          'label' => 'Tudo'],
            'post'  => ['icon' => 'fa-image',        'label' => 'Posts'],
            'video' => ['icon' => 'fa-play',         'label' => 'Vídeos'],
            'album' => ['icon' => 'fa-images',       'label' => 'Álbuns'],
            'reel'  => ['icon' => 'fa-clapperboard', 'label' => 'Reels'],
        ];
        foreach ($filters as $key => $f):
            $active = $filter === $key ? 'active' : '';
        ?>
            <a href="?type=<?= $key ?>" class="saved-filter-btn <?= $active ?>">
                <i class="fa-solid <?= $f['icon'] ?>"></i> <?= $f['label'] ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($items)): ?>
        <div class="saved-empty">
            <i class="fa-regular fa-bookmark"></i>
            <h3>Nenhum conteúdo guardado</h3>
            <p>Guarda posts, vídeos e álbuns para os encontrar facilmente aqui.</p>
        </div>
    <?php else: ?>
        <div class="saved-grid" id="savedGrid">
            <?php foreach ($items as $item):
                $thumb   = get_item_thumb($item);
                $url     = get_item_url($item);
                $icon    = get_type_icon($item['item_type']);
                $is_paid = match ($item['item_type']) {
                    'post', 'reel' => (bool)($item['post_for_sale'] ?? false),
                    'video'        => (bool)($item['video_for_sale'] ?? false),
                    'album'        => (bool)($item['album_for_sale'] ?? false),
                    default        => false,
                };
                $price = match ($item['item_type']) {
                    'post', 'reel' => $item['post_price'] ?? 0,
                    'video'        => $item['video_price'] ?? 0,
                    'album'        => $item['album_price'] ?? 0,
                    default        => 0,
                };
                $avatar = !empty($item['profile_picture'])
                    ? UPLOAD_URL . htmlspecialchars($item['profile_picture'])
                    : BASE_URL . 'assets/images/default_profile.png';

                // ── Lógica de blur (conteúdo sensível detetado pela IA) ──
                $ai_key      = $item['item_type'] . '_' . $item['item_id'];
                $ai_analysis = $ai_analysis_map[$ai_key] ?? null;
                $is_high_risk   = ($ai_analysis && $ai_analysis['status'] === 'done' && $ai_analysis['risk_level'] === 'high');
                $is_medium_risk = ($ai_analysis && $ai_analysis['status'] === 'done' && $ai_analysis['risk_level'] === 'medium');
                $should_blur    = ($is_high_risk || $is_medium_risk) && !$is_admin;
                $blur_id        = 'saved-' . (int)$item['save_id'];
            ?>
                <div class="saved-grid-item"
                    id="<?= $blur_id ?>"
                    data-save-id="<?= (int)$item['save_id'] ?>"
                    data-item-type="<?= htmlspecialchars($item['item_type']) ?>"
                    data-item-id="<?= (int)$item['item_id'] ?>">

                    <?php if ($thumb): ?>
                        <a href="<?= htmlspecialchars($url) ?>"
                            class="<?= $should_blur ? 'media-blur-container' : '' ?>"
                            style="display:block; width:100%; height:100%;">
                            <img class="saved-grid-thumb <?= $should_blur ? 'media-blur' : '' ?>"
                                src="<?= UPLOAD_URL . htmlspecialchars($thumb) ?>"
                                alt=""
                                loading="lazy"
                                onerror="this.style.display='none'">
                            <?php if ($should_blur): ?>
                                <div class="media-overlay-msg">
                                    <i class="fas fa-eye-slash"></i>
                                    <p>Conteúdo Sensível<br><small>Detetado pela IA</small></p>
                                    <button onclick="event.preventDefault(); event.stopPropagation(); unblurSaved('<?= $blur_id ?>')">Ver mesmo assim</button>
                                </div>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($url) ?>">
                            <div class="saved-grid-placeholder">
                                <i class="fa-solid <?= $icon ?>"></i>
                                <span><?= htmlspecialchars(
                                            mb_substr($item['album_name'] ?: $item['video_caption'] ?: $item['post_content'] ?: '', 0, 60)
                                        ) ?></span>
                            </div>
                        </a>
                    <?php endif; ?>

                    <div class="saved-type-badge"><i class="fa-solid <?= $icon ?>"></i></div>

                    <?php if ($is_paid && $price > 0): ?>
                        <div class="saved-price-badge"><?= number_format((float)$price, 0) ?> MT</div>
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

        <?php if ($total_pages > 1): ?>
            <div class="saved-pagination">
                <?php if ($page > 1): ?>
                    <a href="?type=<?= $filter ?>&page=<?= $page - 1 ?>"><i class="fa-solid fa-chevron-left"></i></a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?type=<?= $filter ?>&page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?type=<?= $filter ?>&page=<?= $page + 1 ?>"><i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>window.CSRF_TOKEN = '<?= htmlspecialchars($csrf_token) ?>';</script>
<script src="<?= BASE_URL ?>assets/js/pages/saved.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>