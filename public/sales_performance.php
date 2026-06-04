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

<style>
    /* ═══════════════════════════════════════════════
   SALES PERFORMANCE — Design System Compliant
═══════════════════════════════════════════════ */
    .sp-page {
        min-height: 150vh;
        background: var(--bg-main);
        padding-top: var(--space-xl);
    }

    .sp-inner {
        max-width: 1100px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    /* ── Topbar ── */
    .sp-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 12px;
    }

    .sp-title-block {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .sp-title {
        font-size: var(--text-xl);
        font-weight: var(--weight-extrabold);
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .sp-title-icon {
        width: 36px;
        height: 36px;
        background: var(--primary-soft);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
    }

    .sp-subtitle {
        font-size: var(--text-sm);
        color: var(--text-light);
        margin: 0;
    }

    .sp-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .sp-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: var(--radius-full);
        font-size: var(--text-sm);
        font-weight: var(--weight-semibold);
        border: 1px solid var(--border);
        background: var(--bg-card);
        color: var(--text-main);
        text-decoration: none;
        cursor: pointer;
        transition: border-color .15s, background .15s;
        white-space: nowrap;
    }

    .sp-btn:hover {
        border-color: var(--primary);
        background: var(--primary-soft);
        color: var(--primary);
    }

    .sp-btn.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    /* ── Period tabs ── */
    .period-tabs {
        display: flex;
        gap: 4px;
        background: var(--bg-surface);
        padding: 4px;
        border-radius: var(--radius-full);
        border: 1px solid var(--border);
    }

    .period-tab {
        padding: 6px 14px;
        border-radius: var(--radius-full);
        font-size: 12px;
        font-weight: var(--weight-semibold);
        color: var(--text-muted);
        cursor: pointer;
        border: none;
        background: transparent;
        transition: all .15s;
        text-decoration: none;
    }

    .period-tab:hover {
        color: var(--text-main);
    }

    .period-tab.active {
        background: var(--bg-card);
        color: var(--primary);
        box-shadow: var(--shadow-sm);
    }

    /* ── Alert banner (item filtrado) ── */
    .sp-filter-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: var(--primary-soft);
        border: 1px solid rgba(7, 201, 91, .25);
        border-radius: var(--radius-lg);
        padding: 12px 18px;
        gap: 12px;
    }

    .sp-filter-banner-left {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: var(--text-sm);
        color: var(--text-main);
    }

    .sp-filter-banner strong {
        color: var(--primary);
    }

    .approved-badge,
    .pending-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: var(--radius-full);
        font-size: 11px;
        font-weight: 700;
    }

    .approved-badge {
        background: rgba(16, 185, 129, .12);
        color: var(--success);
    }

    .pending-badge {
        background: rgba(245, 158, 11, .12);
        color: var(--warning);
    }

    /* ── KPI Grid ── */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 14px;
    }

    @media (max-width: 900px) {
        .kpi-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 540px) {
        .kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    .kpi-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        padding: 18px 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        transition: border-color .2s, transform .2s;
        position: relative;
        overflow: hidden;
    }

    .kpi-card::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 3px;
        border-radius: 0 0 var(--radius-lg) var(--radius-lg);
        opacity: 0;
        transition: opacity .2s;
    }

    .kpi-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }

    .kpi-card:hover::after {
        opacity: 1;
    }

    .kpi-card.accent-green::after {
        background: var(--success);
    }

    .kpi-card.accent-red::after {
        background: var(--danger);
    }

    .kpi-card.accent-blue::after {
        background: var(--info);
    }

    .kpi-card.accent-orange::after {
        background: var(--warning);
    }

    .kpi-card.accent-primary::after {
        background: var(--primary);
    }

    .kpi-card.accent-purple::after {
        background: #8b5cf6;
    }

    .kpi-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .kpi-icon {
        width: 34px;
        height: 34px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .kpi-icon.green {
        background: rgba(16, 185, 129, .12);
        color: var(--success);
    }

    .kpi-icon.red {
        background: rgba(239, 68, 68, .12);
        color: var(--danger);
    }

    .kpi-icon.blue {
        background: rgba(59, 130, 246, .12);
        color: var(--info);
    }

    .kpi-icon.orange {
        background: rgba(245, 158, 11, .12);
        color: var(--warning);
    }

    .kpi-icon.primary {
        background: var(--primary-soft);
        color: var(--primary);
    }

    .kpi-icon.purple {
        background: rgba(139, 92, 246, .12);
        color: #8b5cf6;
    }

    .kpi-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-light);
        font-weight: var(--weight-semibold);
    }

    .kpi-value {
        font-size: 20px;
        font-weight: var(--weight-extrabold);
        color: var(--text-main);
        line-height: 1;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }

    .kpi-value.green {
        color: var(--success);
    }

    .kpi-value.red {
        color: var(--danger);
    }

    .kpi-value.blue {
        color: var(--info);
    }

    .kpi-value.orange {
        color: var(--warning);
    }

    .kpi-value.primary {
        color: var(--primary);
    }

    .kpi-value.purple {
        color: #8b5cf6;
    }

    .kpi-unit {
        font-size: 10px;
        font-weight: var(--weight-normal);
        color: var(--text-light);
        margin-left: 3px;
    }

    /* ── Chart card ── */
    .chart-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        padding: 22px 22px 18px;
    }

    .chart-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 10px;
    }

    .chart-title {
        font-size: var(--text-base);
        font-weight: var(--weight-bold);
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    .chart-title svg {
        color: var(--primary);
    }

    .chart-legend {
        display: flex;
        gap: 14px;
    }

    .legend-dot {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        color: var(--text-muted);
    }

    .legend-dot::before {
        content: '';
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }

    .legend-revenue::before {
        background: var(--primary);
    }

    .legend-sales::before {
        background: var(--info);
    }

    .chart-container {
        position: relative;
        height: 200px;
    }

    /* ── Items section ── */
    .items-section {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .items-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .items-title {
        font-size: var(--text-base);
        font-weight: var(--weight-bold);
        color: var(--text-main);
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    .items-title svg {
        color: var(--primary);
    }

    .items-controls {
        display: flex;
        gap: 8px;
    }

    .search-input {
        padding: 8px 14px 8px 36px;
        border-radius: var(--radius-full);
        border: 1px solid var(--border);
        background: var(--bg-surface);
        color: var(--text-main);
        font-size: var(--text-sm);
        outline: none;
        transition: border-color .15s, width .2s;
        width: 180px;
    }

    .search-input:focus {
        border-color: var(--primary);
    }

    .search-wrap {
        position: relative;
    }

    .search-wrap svg {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-light);
        pointer-events: none;
    }

    /* ── Item Card ── */
    .item-card {
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
        padding: 18px 20px;
        display: grid;
        grid-template-columns: 40px 1fr auto;
        gap: 16px;
        align-items: center;
        transition: border-color .15s, box-shadow .15s;
        position: relative;
        overflow: hidden;
    }

    .item-card:hover {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-soft);
    }

    .item-card.hidden {
        display: none;
    }

    .item-type-icon {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .item-type-video {
        background: rgba(59, 130, 246, .1);
    }

    .item-type-album {
        background: rgba(245, 158, 11, .1);
    }

    .item-type-post {
        background: rgba(139, 92, 246, .1);
    }

    .item-body {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 0;
    }

    .item-title-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .item-title {
        font-size: var(--text-sm);
        font-weight: var(--weight-semibold);
        color: var(--text-main);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 340px;
    }

    .type-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: var(--radius-full);
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .type-pill.video {
        background: rgba(59, 130, 246, .12);
        color: var(--info);
    }

    .type-pill.album {
        background: rgba(245, 158, 11, .12);
        color: var(--warning);
    }

    .type-pill.post {
        background: rgba(139, 92, 246, .12);
        color: #8b5cf6;
    }

    .item-progress-row {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .item-progress-track {
        flex: 1;
        height: 5px;
        border-radius: var(--radius-full);
        background: var(--bg-surface);
        overflow: hidden;
        max-width: 200px;
    }

    .item-progress-fill {
        height: 100%;
        border-radius: var(--radius-full);
        background: var(--primary-gradient);
        transition: width .6s ease;
    }

    .item-progress-label {
        font-size: 11px;
        color: var(--text-light);
        white-space: nowrap;
    }

    .item-last-sale {
        font-size: 11px;
        color: var(--text-light);
    }

    .item-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 6px;
        flex-shrink: 0;
    }

    .item-revenue {
        font-size: 17px;
        font-weight: var(--weight-extrabold);
        color: var(--success);
        line-height: 1;
        font-family: 'Plus Jakarta Sans', sans-serif;
        white-space: nowrap;
    }

    .item-revenue-unit {
        font-size: 10px;
        font-weight: var(--weight-normal);
        color: var(--text-light);
        margin-left: 2px;
    }

    .item-sales-count {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--primary-soft);
        color: var(--primary);
        font-size: 11px;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: var(--radius-full);
        white-space: nowrap;
    }

    .item-price {
        font-size: 11px;
        color: var(--text-light);
    }

    .item-not-approved {
        position: absolute;
        top: 10px;
        right: 10px;
        background: rgba(245, 158, 11, .12);
        color: var(--warning);
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: var(--radius-full);
    }

    /* ── Empty state ── */
    .items-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 56px 20px;
        gap: 12px;
        color: var(--text-light);
        background: var(--bg-card);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
    }

    .items-empty p {
        font-size: var(--text-sm);
        margin: 0;
    }

    /* ── No data inside chart ── */
    .chart-nodata {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 200px;
        color: var(--text-light);
        font-size: var(--text-sm);
        flex-direction: column;
        gap: 8px;
    }

    /* ── Responsive ── */
    @media (max-width: 640px) {
        .item-card {
            grid-template-columns: 36px 1fr;
        }

        .item-meta {
            display: none;
        }

        .chart-legend {
            display: none;
        }

        .sp-topbar {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

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
<script>
    // ── Gráfico ──────────────────────────────────────────────
    (function() {
        const canvas = document.getElementById('spChart');
        if (!canvas) return;

        const labels = <?= json_encode($chart_days) ?>;
        const revenue = <?= json_encode($chart_revenue) ?>;
        const counts = <?= json_encode($chart_counts) ?>;

        const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const gridColor = isDark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.05)';
        const tickColor = isDark ? '#85888c' : '#94a3b8';

        new Chart(canvas, {
            data: {
                labels,
                datasets: [{
                        type: 'line',
                        label: 'Receita (MZN)',
                        data: revenue,
                        borderColor: '#07c95b',
                        backgroundColor: 'rgba(7,201,91,0.08)',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: '#07c95b',
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        type: 'bar',
                        label: 'Nº de vendas',
                        data: counts,
                        backgroundColor: 'rgba(59,130,246,0.18)',
                        borderColor: 'rgba(59,130,246,0.5)',
                        borderWidth: 1,
                        borderRadius: 4,
                        yAxisID: 'y2'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: isDark ? '#2c2c2c' : '#fff',
                        borderColor: isDark ? '#444' : '#e2e8f0',
                        borderWidth: 1,
                        titleColor: isDark ? '#eee' : '#000',
                        bodyColor: isDark ? '#aaa' : '#64748b',
                        padding: 12,
                        callbacks: {
                            label: ctx => ctx.dataset.yAxisID === 'y' ?
                                ` ${ctx.raw.toLocaleString('pt-MZ',{minimumFractionDigits:2})} MZN` : ` ${ctx.raw} venda${ctx.raw!==1?'s':''}`
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: tickColor,
                            font: {
                                size: 10
                            },
                            maxRotation: 0,
                            callback: (v, i) => i % 5 === 0 ? labels[i] : ''
                        }
                    },
                    y: {
                        position: 'left',
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: tickColor,
                            font: {
                                size: 10
                            },
                            callback: v => v.toLocaleString('pt-MZ', {
                                minimumFractionDigits: 0
                            }) + ' MT'
                        }
                    },
                    y2: {
                        position: 'right',
                        grid: {
                            drawOnChartArea: false
                        },
                        ticks: {
                            color: tickColor,
                            font: {
                                size: 10
                            },
                            stepSize: 1
                        }
                    }
                }
            }
        });
    })();

    // ── Search + filter ──────────────────────────────────────
    (function() {
        const searchInput = document.getElementById('itemSearch');
        const typeFilter = document.getElementById('typeFilter');
        const cards = document.querySelectorAll('#itemsList .item-card');

        function applyFilters() {
            const q = searchInput.value.toLowerCase().trim();
            const type = typeFilter.value;
            cards.forEach(card => {
                const matchQ = !q || card.dataset.title.includes(q);
                const matchType = type === 'all' || card.dataset.type === type;
                card.classList.toggle('hidden', !(matchQ && matchType));
            });
        }

        if (searchInput) searchInput.addEventListener('input', applyFilters);
        if (typeFilter) typeFilter.addEventListener('change', applyFilters);
    })();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>