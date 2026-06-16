<?php
// public/checkout.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
SecurityManager::initSecurity();
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Services\PaymentService;
use Massango\Models\User;

if (!is_logged_in()) {
    redirect(BASE_URL . 'login.php');
    exit();
}

$type = $_GET['type'] ?? null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$type || !$id) {
    set_message("Conteúdo inválido.", "danger");
    redirect(BASE_URL . 'index.php');
}

// Buscar detalhes do conteúdo
$content = null;
if ($type === 'video') {
    $stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ?");
    $stmt->execute([$id]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    $title = $content['caption'] ?? 'Vídeo';
} elseif ($type === 'album') {
    $stmt = $pdo->prepare("SELECT * FROM albums WHERE id = ?");
    $stmt->execute([$id]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    $title = $content['name'] ?? 'Álbum';
} elseif ($type === 'post') {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$id]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);
    $title = $content['content'] ?? 'Foto';
}

if (!$content || !$content['is_for_sale']) {
    set_message("Este conteúdo não está à venda.", "warning");
    redirect(BASE_URL . 'index.php');
}

$price = $content['price'];
$seller_id = $content['user_id'];
$buyer_id = get_current_user_id();

if ($seller_id == $buyer_id) {
    set_message("Você não pode comprar seu próprio conteúdo.", "info");
    redirect(BASE_URL . 'index.php');
}

// Verificar se já comprou
$paymentService = new PaymentService($pdo);
if ($paymentService->hasAccess($buyer_id, $type, $id)) {
    set_message("Você já tem acesso a este conteúdo.", "info");
    redirect(BASE_URL . ($type === 'video' ? 'post.php?id=' . $id : 'view_album.php?id=' . $id));
}

$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if (!$is_ajax) {
    include __DIR__ . '/../includes/header.php';
}
?>

<div class="<?= $is_ajax ? 'checkout-modal-container' : 'container mt-5' ?>">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Finalizar Compra</h4>
                </div>
                <div class="card-body">
                    <h5><?= htmlspecialchars($title) ?></h5>
                    <p class="text-muted">Tipo: <?= ucfirst($type) ?></p>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Preço:</span>
                        <span class="h4 text-success"><?= number_format($price, 2, ',', '.') ?> MT</span>
                    </div>

                    <form action="actions/payment.php" method="POST" id="payment-form">
                        <input type="hidden" name="content_type" value="<?= $type ?>">
                        <input type="hidden" name="content_id" value="<?= $id ?>">
                        <input type="hidden" name="amount" value="<?= $price ?>">
                        <input type="hidden" name="seller_id" value="<?= $seller_id ?>">

                        <div class="mb-3">
                            <label class="form-label">Método de Pagamento</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="mpesa" value="mpesa" checked>
                                    <label class="form-check-label" for="mpesa">
                                        M-Pesa
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="emola" value="emola">
                                    <label class="form-check-label" for="emola">
                                        e-Mola
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="phone_number" class="form-label">Número de Telefone (84/85 para M-Pesa, 86/87 para e-Mola)</label>
                            <input type="text" class="form-control" id="phone_number" name="phone_number" placeholder="Ex: 841234567" required>
                        </div>

                        <button type="submit" class="btn btn-success w-100 btn-lg">Pagar Agora</button>
                    </form>
                </div>
                <div class="card-footer text-center text-muted">
                    <small>Ao clicar em "Pagar Agora", você receberá um pedido de confirmação no seu telemóvel.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
if (!$is_ajax) {
    include __DIR__ . '/../includes/footer.php'; 
}
?>