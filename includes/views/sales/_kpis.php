<?php
/**
 * @var array $stats
 * @var float $commission_rate
 */
?>
<!-- ── KPIs ── -->
<div class="kpi-grid">

    <!-- Vendas -->
    <div class="kpi-card accent-primary">
        <div class="kpi-top">
            <div class="kpi-label">Vendas</div>
            <div class="kpi-icon primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
                </svg>
            </div>
        </div>
        <div class="kpi-value primary"><?= number_format((int)$stats['total_sales']) ?></div>
    </div>

    <!-- Ganhos líquidos -->
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
            <?= number_format((float)$stats['total_net'], 2, ',', '.') ?>
            <span class="kpi-unit">MZN</span>
        </div>
    </div>

    <!-- Receita bruta -->
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
            <?= number_format((float)$stats['total_gross'], 2, ',', '.') ?>
            <span class="kpi-unit">MZN</span>
        </div>
    </div>

    <!-- Comissão -->
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
            <?= number_format((float)$stats['total_commission'], 2, ',', '.') ?>
            <span class="kpi-unit">MZN</span>
        </div>
    </div>

    <!-- Publicações -->
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
        <div class="kpi-value orange"><?= number_format((int)$stats['unique_items']) ?></div>
    </div>

    <!-- Compradores -->
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
        <div class="kpi-value purple"><?= number_format((int)$stats['unique_buyers']) ?></div>
    </div>

</div>
