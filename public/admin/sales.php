<?php
// public/admin/sales.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/header.php';

$sales = $pdo->query("
    SELECT s.*, u.username as buyer_name, sel.username as seller_name 
    FROM sales s 
    JOIN users u ON s.buyer_id = u.id 
    JOIN users sel ON s.seller_id = sel.id 
    ORDER BY s.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_volume = array_sum(array_column($sales, 'amount'));
$total_commission = array_sum(array_column($sales, 'commission_amount'));
?>

<div class="stats-grid">
    <div class="stat-card">
        <i class="fas fa-shopping-bag" style="color: var(--admin-accent);"></i>
        <div class="stat-info">
            <h3>Total de Vendas</h3>
            <p><?= count($sales) ?></p>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-money-bill-wave" style="color: var(--admin-success);"></i>
        <div class="stat-info">
            <h3>Volume Total</h3>
            <p><?= number_format($total_volume, 2) ?> MT</p>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-percentage" style="color: var(--admin-warning);"></i>
        <div class="stat-info">
            <h3>Comissão Total</h3>
            <p><?= number_format($total_commission, 2) ?> MT</p>
        </div>
    </div>
</div>

<div class="admin-card">
    <h3>Histórico de Transações</h3>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Data</th>
                    <th>Comprador</th>
                    <th>Vendedor</th>
                    <th>Conteúdo</th>
                    <th>Valor</th>
                    <th>Comissão</th>
                    <th>Método</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td>#<?= $sale['id'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></td>
                        <td><?= htmlspecialchars($sale['buyer_name']) ?></td>
                        <td><?= htmlspecialchars($sale['seller_name']) ?></td>
                        <td>
                            <span class="badge badge-info"><?= ucfirst($sale['content_type']) ?></span>
                            <small>(ID: <?= $sale['content_id'] ?>)</small>
                        </td>
                        <td><strong><?= number_format($sale['amount'], 2) ?> MT</strong></td>
                        <td><span style="color: var(--admin-success);"><?= number_format($sale['commission_amount'], 2) ?> MT</span></td>
                        <td><?= strtoupper($sale['payment_method']) ?></td>
                        <td>
                            <span class="badge badge-<?= $sale['status'] == 'completed' ? 'success' : ($sale['status'] == 'pending' ? 'warning' : 'danger') ?>">
                                <?= ucfirst($sale['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($sales)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px;">Nenhuma venda registada.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>