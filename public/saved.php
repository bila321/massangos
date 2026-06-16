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
        -- Photo (album_photos)
        ap.thumbnail_path       AS photo_thumb,
        ap.photo_path           AS photo_path,
        ap.caption              AS photo_caption,
        ap.album_id             AS photo_album_id,

        -- Autor
        u.username,
        u.profile_picture,
        u.id AS author_id

    FROM saved_posts sp
    LEFT JOIN posts  p ON sp.item_type IN ('post','reel') AND sp.item_id = p.id
    LEFT JOIN videos v ON sp.item_type = 'video'          AND sp.item_id = v.id
    LEFT JOIN albums a ON sp.item_type = 'album'          AND sp.item_id = a.id
    LEFT JOIN album_photos ap ON sp.item_type = 'photo' AND sp.item_id = ap.id
    LEFT JOIN users  u ON (
        CASE sp.item_type
            WHEN 'post'  THEN p.user_id
            WHEN 'reel'  THEN p.user_id
            WHEN 'video' THEN v.user_id
            WHEN 'album' THEN a.user_id
            WHEN 'photo' THEN ap.user_id
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
$get_analysis_type = static function (array $item): string {
    return match ($item['item_type']) {
        'album' => 'album',
        'video' => 'video',
        'photo' => 'image',
        'reel'  => 'video',
        'post'  => (!empty($item['post_video_thumb']) || (($item['post_type'] ?? '') === 'reel'))
            ? 'video'
            : 'image',
        default => '',
    };
};

$ai_analysis_map = [];
if (!empty($items)) {
    $ids_by_analysis_type = [];

    foreach ($items as $it) {
        $analysis_type = $get_analysis_type($it);
        if ($analysis_type === '') {
            continue;
        }

        $ids_by_analysis_type[$analysis_type][] = (int)$it['item_id'];
    }

    foreach ($ids_by_analysis_type as $analysis_type => $ids) {
        $ids = array_values(array_unique($ids));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $q = $pdo->prepare(
            "SELECT post_id, risk_level, status, explicit_percentage
             FROM media_analysis
             WHERE post_id IN ($placeholders) AND type = ?
             ORDER BY id DESC"
        );

        $q->execute([...$ids, $analysis_type]);

        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $analysis_type . '_' . $row['post_id'];
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
            'photo' => $item['photo_thumb'] ?: $item['photo_path'] ?: '',
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
            'photo' => BASE_URL . 'view_album.php?id=' . $item['photo_album_id'] . '#photo-' . $item['item_id'],
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
            'photo' => 'fa-image',
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
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/saved.css">



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
            'photo' => ['icon' => 'fa-image',        'label' => 'Fotos'],
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
                    'photo'        => false,
                    default        => false,
                };
                $price = match ($item['item_type']) {
                    'post', 'reel' => $item['post_price'] ?? 0,
                    'video'        => $item['video_price'] ?? 0,
                    'album'        => $item['album_price'] ?? 0,
                    'photo'        => 0,
                    default        => 0,
                };
                $avatar = !empty($item['profile_picture'])
                    ? UPLOAD_URL . htmlspecialchars($item['profile_picture'])
                    : BASE_URL . 'assets/images/default_profile.png';

                // ── Lógica de blur (conteúdo sensível detetado pela IA) ──
                $analysis_type = $get_analysis_type($item);
                $ai_key      = $analysis_type . '_' . $item['item_id'];
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

<script>
    window.CSRF_TOKEN = '<?= htmlspecialchars($csrf_token) ?>';
</script>
<script src="<?= BASE_URL ?>assets/js/pages/saved.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>