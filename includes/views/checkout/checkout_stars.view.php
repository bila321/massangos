<?php
// includes/views/checkout/checkout_stars.view.php
// Variáveis injectadas pelo CheckoutController (modo 'stars'):
//   $stars      — int
//   $duration   — 'monthly' | 'yearly'
//   $price      — float
//   $buyer_id   — int  (disponível mas não usado directamente na view)

$duration_label = $duration === 'monthly' ? 'Mensal' : 'Anual';
$stars_label    = $stars . ' Estrela' . ($stars > 1 ? 's' : '');
?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">

                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">Finalizar Compra de Estrelas</h4>
                </div>

                <div class="card-body">
                    <h5><?= htmlspecialchars($stars_label) ?> (<?= $duration_label ?>)</h5>
                    <p class="text-muted">Aumente o seu nível de vendedor na plataforma.</p>
                    <hr>

                    <div class="d-flex justify-content-between mb-3">
                        <span>Total a Pagar:</span>
                        <span class="h4 text-success"><?= number_format($price, 2, ',', '.') ?> MT</span>
                    </div>

                    <form action="<?= BASE_URL ?>actions/payment_stars.php" method="POST" id="payment-form">
                        <input type="hidden" name="stars"    value="<?= (int)$stars ?>">
                        <input type="hidden" name="duration" value="<?= htmlspecialchars($duration) ?>">
                        <input type="hidden" name="amount"   value="<?= (float)$price ?>">

                        <?php require __DIR__ . '/_payment_methods.php'; ?>

                        <button type="submit" class="btn btn-warning w-100 btn-lg">
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
