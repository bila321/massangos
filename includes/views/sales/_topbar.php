<?php
/**
 * @var string      $period
 * @var string|null $filter_type
 * @var int|null    $filter_id
 */
$period_tabs = ['all' => 'Tudo', '7' => '7d', '30' => '30d', '90' => '90d'];
?>
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
            <?php foreach ($period_tabs as $val => $label):
                $qs = '?period=' . $val
                    . ($filter_type ? '&type=' . urlencode($filter_type) : '')
                    . ($filter_id   ? '&id='   . $filter_id              : '');
            ?>
                <a href="<?= $qs ?>"
                    class="period-tab <?= $period === $val ? 'active' : '' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($filter_type && $filter_id): ?>
            <a href="sales_performance.php?period=<?= htmlspecialchars($period) ?>" class="sp-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6"  y2="18" />
                    <line x1="6"  y1="6" x2="18" y2="18" />
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
