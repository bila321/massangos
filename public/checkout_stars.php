<?php
// public/checkout_stars.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
SecurityManager::initSecurity();

if (!is_logged_in()) {
    redirect(BASE_URL . 'login.php');
    exit();
}

$stars = (int)($_GET['stars'] ?? 0);
$duration = $_GET['duration'] ?? '';

if (!$stars || !in_array($duration, ['monthly', 'yearly'])) {
    set_message("Seleção inválida.", "danger");
    redirect(BASE_URL . 'buy_stars.php');
}

// Buscar preço
$price_data = fetchOne("SELECT * FROM star_prices WHERE stars = ? AND duration_type = ?", [$stars, $duration]);

if (!$price_data) {
    set_message("Preço não encontrado.", "danger");
    redirect(BASE_URL . 'buy_stars.php');
}

$price = $price_data['price'];
$buyer_id = get_current_user_id();

include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">Finalizar Compra de Estrelas</h4>
                </div>
                <div class="card-body">
                    <h5><?= $stars ?> Estrela<?= $stars > 1 ? 's' : '' ?> (<?= $duration === 'monthly' ? 'Mensal' : 'Anual' ?>)</h5>
                    <p class="text-muted">Aumente seu nível de vendedor na plataforma.</p>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Total a Pagar:</span>
                        <span class="h4 text-success"><?= number_format($price, 2, ',', '.') ?> MT</span>
                    </div>

                    <form action="process_payment_stars.php" method="POST" id="payment-form">
                        <input type="hidden" name="stars" value="<?= $stars ?>">
                        <input type="hidden" name="duration" value="<?= $duration ?>">
                        <input type="hidden" name="amount" value="<?= $price ?>">

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

                        <button type="submit" class="btn btn-warning w-100 btn-lg">Pagar Agora</button>
                    </form>
                </div>
                <div class="card-footer text-center text-muted">
                    <small>Ao clicar em "Pagar Agora", você receberá um pedido de confirmação no seu telemóvel.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
