<?php
// includes/views/checkout/checkout_content.view.php
// Variáveis injectadas pelo CheckoutController (modo 'content'):
//   $type      — 'video' | 'album' | 'post'
//   $id        — int
//   $title     — string
//   $price     — float
//   $seller_id — int
//   $is_ajax   — bool
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
                    <p class="text-muted">Tipo: <?= htmlspecialchars(ucfirst($type)) ?></p>
                    <hr>

                    <div class="d-flex justify-content-between mb-3">
                        <span>Preço:</span>
                        <span class="h4 text-success"><?= number_format($price, 2, ',', '.') ?> MT</span>
                    </div>

                    <form action="<?= BASE_URL ?>actions/payment.php" method="POST" id="payment-form">
                        <input type="hidden" name="content_type" value="<?= htmlspecialchars($type) ?>">
                        <input type="hidden" name="content_id"   value="<?= (int)$id ?>">
                        <input type="hidden" name="amount"       value="<?= (float)$price ?>">
                        <input type="hidden" name="seller_id"    value="<?= (int)$seller_id ?>">

                        <?php require __DIR__ . '/_payment_methods.php'; ?>

                        <button type="submit" class="btn btn-success w-100 btn-lg">
                            Pagar Agora
                        </button>
                    </form>
                </div>

                <div class="card-footer text-center text-muted">
                    <small>Ao clicar em "Pagar Agora", receberá um pedido de confirmação no seu telemóvel.</small>
                </div>

            </div>
        </div>
    </div>
</div>
