<?php
/**
 * View: sales_performance.view.php
 *
 * Coordenador dos partials.
 *
 * Variáveis disponíveis (via extract no Controller):
 *   @var array       $stats
 *   @var array       $chart_days
 *   @var array       $chart_revenue
 *   @var array       $chart_counts
 *   @var array       $item_sales
 *   @var int         $max_sales
 *   @var array|false $top_buyer
 *   @var float       $commission_rate
 *   @var string      $period
 *   @var string|null $filter_type
 *   @var int|null    $filter_id
 */
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/sales_performance.css">

<div class="sp-page">
    <div class="sp-inner">

        <?php require __DIR__ . '/_topbar.php'; ?>
        <?php require __DIR__ . '/_filter_banner.php'; ?>
        <?php require __DIR__ . '/_kpis.php'; ?>
        <?php require __DIR__ . '/_chart.php'; ?>
        <?php require __DIR__ . '/_items_list.php'; ?>

    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    window.SP_CHART = {
        labels: <?= json_encode($chart_days) ?>,
        revenue: <?= json_encode($chart_revenue) ?>,
        counts: <?= json_encode($chart_counts) ?>
    };
</script>
<script src="<?= BASE_URL ?>assets/js/pages/sales_performance.js"></script>
