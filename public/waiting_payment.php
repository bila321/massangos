<?php
// public/waiting_payment.php
define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../app/bootstrap.php';

SecurityManager::initSecurity();

if (!is_logged_in()) {
    redirect(BASE_URL . 'login.php');
    exit();
}

$sale_id = (int)($_GET['sale_id'] ?? 0);

if (!$sale_id) {
    redirect(BASE_URL . 'index.php');
}

// Buscar status da venda
$stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND buyer_id = ?");
$stmt->execute([$sale_id, get_current_user_id()]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    redirect(BASE_URL . 'index.php');
}

if ($sale['status'] === 'completed') {
    set_message("Pagamento confirmado! Aproveite o conteúdo.", "success");
    redirect(BASE_URL . ($sale['content_type'] === 'video' ? 'post.php?id=' . $sale['content_id'] : 'view_album.php?id=' . $sale['content_id']));
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-5 text-center">
    <div class="card shadow p-5">
        <div class="spinner-border text-primary mb-4" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Aguardando...</span>
        </div>
        <h3>Aguardando Confirmação</h3>
        <p class="lead">Por favor, confirme o pagamento no seu telemóvel.</p>
        <p class="text-muted">Esta página será atualizada automaticamente assim que o pagamento for detectado.</p>

        <div class="mt-4">
            <a href="index.php" class="btn btn-outline-secondary">Voltar ao Início</a>
        </div>
    </div>
</div>

<script>
    // Poll the server every 5 seconds to check payment status
    setInterval(function() {
        fetch('api/check_payment_status.php?sale_id=<?= $sale_id ?>')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'completed') {
                    window.location.href = '<?= ($sale['content_type'] === 'video' ? 'post.php?id=' . $sale['content_id'] : 'view_album.php?id=' . $sale['content_id']) ?>';
                } else if (data.status === 'failed') {
                    alert('O pagamento falhou ou foi cancelado.');
                    if (typeof pageModalLoader !== 'undefined') {
                        pageModalLoader.open('checkout.php?type=<?= $sale['content_type'] ?>&id=<?= $sale['content_id'] ?>');
                    } else {
                        window.location.href = 'checkout.php?type=<?= $sale['content_type'] ?>&id=<?= $sale['content_id'] ?>';
                    }
                }
            });
    }, 5000);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>