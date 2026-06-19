<?php
/**
 * @var string|null $filter_type
 * @var int|null    $filter_id
 * @var array       $item_sales
 * @var string      $period
 */
if (!$filter_type || !$filter_id || empty($item_sales)) return;
?>
<!-- ── Banner de filtro por item ── -->
<div class="sp-filter-banner">
    <div class="sp-filter-banner-left">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            style="color:var(--primary)">
            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
        </svg>
        A filtrar por:
        <strong><?= htmlspecialchars(mb_strimwidth($item_sales[0]['title'], 0, 60, '…')) ?></strong>
        <?php if ($item_sales[0]['is_approved']): ?>
            <span class="approved-badge">✓ Aprovado</span>
        <?php else: ?>
            <span class="pending-badge">⏳ Pendente</span>
        <?php endif; ?>
    </div>
    <a href="sales_performance.php?period=<?= htmlspecialchars($period) ?>"
        class="sp-btn" style="font-size:12px;padding:6px 12px;">
        Ver tudo
    </a>
</div>
