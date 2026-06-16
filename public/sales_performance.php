<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
SecurityManager::initSecurity();
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Services\PricingRuleService;

if (!is_logged_in()) {
    redirect(BASE_URL . 'login.php');
    exit();
}

$user_id = get_current_user_id();

// ── Filtros ──────────────────────────────────────────────
$filter_type = $_GET['type'] ?? null;
$filter_id   = isset($_GET['id']) ? (int)$_GET['id'] : null;
$period      = $_GET['period'] ?? 'all'; // all | 7 | 30 | 90

$period_sql = '';
$period_params = [];
if (in_array($period, ['7', '30', '90'])) {
    $period_sql = " AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $period_params[] = (int)$period;
}

// ── Stats gerais ─────────────────────────────────────────
$p = array_merge([$user_id], $period_params);
if ($filter_type && $filter_id) {
    $extra = " AND content_type = ? AND content_id = ?";
    $p2 = array_merge([$user_id], $period_params, [$filter_type, $filter_id]);
} else {
    $extra = '';
    $p2 = $p;
}

$stmt = $pdo->prepare("
    SELECT
        COUNT(*)                        AS total_sales,
        COALESCE(SUM(amount),0)         AS total_gross,
        COALESCE(SUM(seller_amount),0)  AS total_net,
        COALESCE(SUM(commission_amount),0) AS total_commission,
        COUNT(DISTINCT content_id)      AS unique_items,
        COUNT(DISTINCT buyer_id)        AS unique_buyers
    FROM sales s
    WHERE seller_id = ? AND status = 'completed'
    $period_sql $extra
");
$stmt->execute($p2);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// ── Vendas por dia (últimos 30 dias para o gráfico) ───────
$chart_stmt = $pdo->prepare("
    SELECT DATE(created_at) as day, COUNT(*) as cnt, COALESCE(SUM(seller_amount),0) as revenue
    FROM sales
    WHERE seller_id = ? AND status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
$chart_stmt->execute([$user_id]);
$chart_raw = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);

// Preencher dias sem venda com 0
$chart_days = [];
$chart_revenue = [];
$chart_counts = [];
$start = new DateTime('-29 days');
$end = new DateTime('today');
$interval = new DateInterval('P1D');
$range = new DatePeriod($start, $interval, $end->modify('+1 day'));
$chart_map = array_column($chart_raw, null, 'day');
foreach ($range as $dt) {
    $k = $dt->format('Y-m-d');
    $chart_days[]    = $dt->format('d/m');
    $chart_revenue[] = isset($chart_map[$k]) ? (float)$chart_map[$k]['revenue'] : 0;
    $chart_counts[]  = isset($chart_map[$k]) ? (int)$chart_map[$k]['cnt'] : 0;
}

// ── Itens com performance ────────────────────────────────
$stmt2 = $pdo->prepare("
    SELECT
        s.content_type,
        s.content_id,
        COUNT(*)                       AS sales_count,
        COALESCE(SUM(s.amount),0)      AS item_gross,
        COALESCE(SUM(s.seller_amount),0) AS item_net,
        MAX(s.created_at)              AS last_sale
    FROM sales s
    WHERE s.seller_id = ? AND s.status = 'completed'
    $period_sql $extra
    GROUP BY s.content_type, s.content_id
    ORDER BY sales_count DESC
");
$stmt2->execute($p2);
$item_sales = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Enriquecer com título, preço, aprovação
$max_sales = 1;
foreach ($item_sales as &$item) {
    if ($item['content_type'] === 'video') {
        $s = $pdo->prepare("SELECT caption AS title, price, is_approved FROM videos WHERE id = ?");
    } elseif ($item['content_type'] === 'album') {
        $s = $pdo->prepare("SELECT name AS title, price, is_approved FROM albums WHERE id = ?");
    } else {
        $s = $pdo->prepare("SELECT SUBSTRING(content,1,80) AS title, price, 1 AS is_approved FROM posts WHERE id = ?");
    }
    $s->execute([$item['content_id']]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    $item['title']       = $row['title']       ?? 'Item Removido';
    $item['price']       = (float)($row['price'] ?? 0);
    $item['is_approved'] = (int)($row['is_approved'] ?? 1);
    if ((int)$item['sales_count'] > $max_sales) $max_sales = (int)$item['sales_count'];
}
unset($item);

// Top buyer
$top_buyer_stmt = $pdo->prepare("
    SELECT u.username, COUNT(*) AS cnt
    FROM sales s JOIN users u ON u.id = s.buyer_id
    WHERE s.seller_id = ? AND s.status='completed' $period_sql
    GROUP BY s.buyer_id ORDER BY cnt DESC LIMIT 1
");
$top_buyer_stmt->execute(array_merge([$user_id], $period_params));
$top_buyer = $top_buyer_stmt->fetch(PDO::FETCH_ASSOC);

$commission_rate = (float) PricingRuleService::getSetting($pdo, 'commission_rate', 15);
$pageTitle = "Performance de Vendas";
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/sales_performance.css">

<div class="sp-page">
    <div class="sp-inner">

        <!-- ── Topbar ── -->
        <div class="sp-topbar">
            <div class="sp-title-block">
                <h1 class="sp-title">
                    <span class="sp-title-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                        </svg>
                    </span>
                    Performance de Vendas
                </h1>
                <p class="sp-subtitle">Acompanha o desempenho de cada publicação à venda</p>
            </div>

            <div class="sp-actions">
                <!-- Filtros de período -->
                <div class="period-tabs">
                    <?php foreach (['all' => 'Tudo', '7' => '7d', '30' => '30d', '90' => '90d'] as $val => $label): ?>
                        <a href="?period=<?= $val ?><?= $filter_type ? "&type=$filter_type" : '' ?><?= $filter_id ? "&id=$filter_id" : '' ?>"
                            class="period-tab <?= $period === $val ? 'active' : '' ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>

                <?php if ($filter_type && $filter_id): ?>
                    <a href="sales_performance.php?period=<?= $period ?>" class="sp-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                        Limpar filtro
                    </a>
                <?php endif; ?>

                <a href="profile.php" class="sp-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6" />
                    </svg>
                    Perfil
                </a>
            </div>
        </div>

        <!-- ── Banner de filtro por item ── -->
        <?php if ($filter_type && $filter_id && !empty($item_sales)): ?>
            <div class="sp-filter-banner">
                <div class="sp-filter-banner-left">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--primary)">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                    </svg>
                    A filtrar por: <strong><?= htmlspecialchars(mb_strimwidth($item_sales[0]['title'], 0, 60, '…')) ?></strong>
                    <?php if ($item_sales[0]['is_approved']): ?>
                        <span class="approved-badge">✓ Aprovado</span>
                    <?php else: ?>
                        <span class="pending-badge">⏳ Pendente</span>
                    <?php endif; ?>
                </div>
                <a href="sales_performance.php?period=<?= $period ?>" class="sp-btn" style="font-size:12px;padding:6px 12px;">Ver tudo</a>
            </div>
        <?php endif; ?>

        <!-- ── KPIs ── -->
        <div class="kpi-grid">
            <div class="kpi-card accent-primary">
                <div class="kpi-top">
                    <div class="kpi-label">Vendas</div>
                    <div class="kpi-icon primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                        </svg>
                    </div>
                </div>
                <div class="kpi-value primary"><?= number_format($stats['total_sales']) ?></div>
            </div>

            <div class="kpi-card accent-green">
                <div class="kpi-top">
                    <div class="kpi-label">Ganhos líquidos</div>
                    <div class="kpi-icon green">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <line x1="12" y1="1" x2="12" y2="23" />
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                        </svg>
                    </div>
                </div>
                <div class="kpi-value green">
                    <?= number_format($stats['total_net'], 2, ',', '.') ?>
                    <span class="kpi-unit">MZN</span>
                </div>
            </div>

            <div class="kpi-card accent-blue">
                <div class="kpi-top">
                    <div class="kpi-label">Receita bruta</div>
                    <div class="kpi-icon blue">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <rect x="2" y="7" width="20" height="14" rx="2" />
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" />
                        </svg>
                    </div>
                </div>
                <div class="kpi-value blue">
                    <?= number_format($stats['total_gross'], 2, ',', '.') ?>
                    <span class="kpi-unit">MZN</span>
                </div>
            </div>

            <div class="kpi-card accent-red">
                <div class="kpi-top">
                    <div class="kpi-label">Comissão <?= $commission_rate ?>%</div>
                    <div class="kpi-icon red">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="8" y1="12" x2="16" y2="12" />
                        </svg>
                    </div>
                </div>
                <div class="kpi-value red">
                    <?= number_format($stats['total_commission'], 2, ',', '.') ?>
                    <span class="kpi-unit">MZN</span>
                </div>
            </div>

            <div class="kpi-card accent-orange">
                <div class="kpi-top">
                    <div class="kpi-label">Publicações</div>
                    <div class="kpi-icon orange">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                        </svg>
                    </div>
                </div>
                <div class="kpi-value orange"><?= number_format($stats['unique_items']) ?></div>
            </div>

            <div class="kpi-card accent-purple">
                <div class="kpi-top">
                    <div class="kpi-label">Compradores</div>
                    <div class="kpi-icon purple">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </div>
                </div>
                <div class="kpi-value purple"><?= number_format($stats['unique_buyers']) ?></div>
            </div>
        </div>

        <!-- ── Gráfico ── -->
        <div class="chart-card">
            <div class="chart-header">
                <h3 class="chart-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="23 6 13.5 15.5 8.5 10.5 1 18" />
                        <polyline points="17 6 23 6 23 12" />
                    </svg>
                    Evolução — últimos 30 dias
                </h3>
                <div class="chart-legend">
                    <span class="legend-dot legend-revenue">Receita (MZN)</span>
                    <span class="legend-dot legend-sales">Nº de vendas</span>
                </div>
            </div>
            <?php if (array_sum($chart_revenue) > 0): ?>
                <div class="chart-container">
                    <canvas id="spChart"></canvas>
                </div>
            <?php else: ?>
                <div class="chart-nodata">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".3">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                    </svg>
                    <p>Sem dados de venda nos últimos 30 dias</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Lista de itens ── -->
        <div class="items-section">
            <div class="items-header">
                <h3 class="items-title">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" />
                        <rect x="14" y="3" width="7" height="7" />
                        <rect x="14" y="14" width="7" height="7" />
                        <rect x="3" y="14" width="7" height="7" />
                    </svg>
                    Performance por Publicação
                </h3>
                <div class="items-controls">
                    <div class="search-wrap">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                        <input type="text" id="itemSearch" class="search-input" placeholder="Pesquisar...">
                    </div>
                    <select id="typeFilter" class="sp-btn" style="cursor:pointer; appearance:none; padding-right:20px;">
                        <option value="all">Todos</option>
                        <option value="video">Vídeos</option>
                        <option value="album">Álbuns</option>
                        <option value="post">Posts</option>
                    </select>
                </div>
            </div>

            <?php if (empty($item_sales)): ?>
                <div class="items-empty">
                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="3" width="7" height="7" />
                        <rect x="14" y="3" width="7" height="7" />
                        <rect x="14" y="14" width="7" height="7" />
                        <rect x="3" y="14" width="7" height="7" />
                    </svg>
                    <p>Nenhuma venda encontrada para este período.</p>
                </div>
            <?php else: ?>

                <div id="itemsList">
                    <?php foreach ($item_sales as $item):
                        $pct   = $max_sales > 0 ? round(($item['sales_count'] / $max_sales) * 100) : 0;
                        $icons = ['video' => '🎬', 'album' => '🖼️', 'post' => '📝'];
                        $icon  = $icons[$item['content_type']] ?? '📦';
                        $last  = $item['last_sale'] ? (new DateTime($item['last_sale']))->format('d/m/Y') : '—';
                        $type  = $item['content_type'];
                    ?>
                        <div class="item-card" data-type="<?= $type ?>" data-title="<?= htmlspecialchars(strtolower($item['title'])) ?>">

                            <?php if (!$item['is_approved']): ?>
                                <span class="item-not-approved">Pendente</span>
                            <?php endif; ?>

                            <div class="item-type-icon item-type-<?= $type ?>"><?= $icon ?></div>

                            <div class="item-body">
                                <div class="item-title-row">
                                    <span class="item-title"><?= htmlspecialchars(mb_strimwidth($item['title'], 0, 70, '…')) ?></span>
                                    <span class="type-pill <?= $type ?>"><?= ucfirst($type) ?></span>
                                </div>

                                <div class="item-progress-row">
                                    <div class="item-progress-track">
                                        <div class="item-progress-fill" style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <span class="item-progress-label"><?= $item['sales_count'] ?> venda<?= $item['sales_count'] != 1 ? 's' : '' ?></span>
                                    <span class="item-last-sale">· última: <?= $last ?></span>
                                </div>

                                <?php if ($item['price'] > 0): ?>
                                    <div style="font-size:11px;color:var(--text-light);">Preço: <strong style="color:var(--text-main)"><?= number_format($item['price'], 2, ',', '.') ?> MZN</strong></div>
                                <?php endif; ?>
                            </div>

                            <div class="item-meta">
                                <div class="item-revenue">
                                    <?= number_format($item['item_net'], 2, ',', '.') ?>
                                    <span class="item-revenue-unit">MZN</span>
                                </div>
                                <div class="item-sales-count">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                                    </svg>
                                    <?= $item['sales_count'] ?>
                                </div>
                                <a href="?type=<?= $type ?>&id=<?= $item['content_id'] ?>&period=<?= $period ?>"
                                    class="sp-btn" style="font-size:11px;padding:5px 12px;">Detalhe</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/pages/sales_performance.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>