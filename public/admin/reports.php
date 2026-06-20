<?php
// public/admin/reports.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/header.php';

// Relatório mensal de comissões
$monthly_report = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total_sales,
        SUM(amount) as total_volume,
        SUM(commission_amount) as total_commission
    FROM sales 
    WHERE status = 'completed'
    GROUP BY month
    ORDER BY month DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Top vendedores
$top_sellers = $pdo->query("
    SELECT 
        u.username,
        COUNT(s.id) as sales_count,
        SUM(s.amount) as total_volume,
        SUM(s.commission_amount) as total_commission
    FROM sales s
    JOIN users u ON s.seller_id = u.id
    WHERE s.status = 'completed'
    GROUP BY s.seller_id
    ORDER BY total_volume DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-card">
    <h3>Relatório Mensal de Desempenho</h3>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Mês</th>
                    <th>Vendas</th>
                    <th>Volume Total (MT)</th>
                    <th>Comissão Plataforma (MT)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly_report as $row): ?>
                    <tr>
                        <td><strong><?= $row['month'] ?></strong></td>
                        <td><?= $row['total_sales'] ?></td>
                        <td><?= number_format($row['total_volume'], 2) ?> MT</td>
                        <td><span style="color: var(--admin-success); font-weight: bold;"><?= number_format($row['total_commission'], 2) ?> MT</span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($monthly_report)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px;">Sem dados disponíveis.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <h3>Top 10 Vendedores (por Volume)</h3>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Vendedor</th>
                    <th>Vendas</th>
                    <th>Volume Total</th>
                    <th>Comissão Gerada</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_sellers as $seller): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($seller['username']) ?></strong></td>
                        <td><?= $seller['sales_count'] ?></td>
                        <td><?= number_format($seller['total_volume'], 2) ?> MT</td>
                        <td><?= number_format($seller['total_commission'], 2) ?> MT</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($top_sellers)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px;">Sem dados disponíveis.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>