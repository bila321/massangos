<?php /** @var array $chart_revenue */ ?>
<!-- ── Gráfico — últimos 30 dias ── -->
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
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.5" opacity=".3">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
            </svg>
            <p>Sem dados de venda nos últimos 30 dias</p>
        </div>
    <?php endif; ?>
</div>
