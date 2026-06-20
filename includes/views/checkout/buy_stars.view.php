<?php
// includes/views/checkout/buy_stars.view.php
// Variáveis injectadas pelo BuyStarsController:
//   $user          — ['stars' => int, 'stars_expiration' => string|null]
//   $star_packages — [stars_count => ['monthly' => row, 'yearly' => row], ...]

$cur_stars = (int)($user['stars'] ?? 0);
$expiry    = $user['stars_expiration'] ?? null;
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/buy_stars.css">

<div class="container mt-5">
    <div class="row">
        <div class="col-12 text-center mb-4">
            <h2><i class="fas fa-star text-warning"></i> Compre Estrelas</h2>
            <p class="lead">Aumente o seu nível para vender conteúdos por preços maiores.</p>

            <div class="alert alert-info d-inline-block">
                Nível actual: <strong><?= $cur_stars ?> Estrela<?= $cur_stars !== 1 ? 's' : '' ?></strong>
                <?php if ($expiry): ?>
                    <br><small>Expira em: <?= date('d/m/Y H:i', strtotime($expiry)) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">

        <?php foreach ($star_packages as $stars_count => $plans): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm border-warning">

                    <div class="card-header bg-warning text-dark text-center">
                        <h4 class="my-0 font-weight-normal">
                            <?= (int)$stars_count ?> Estrela<?= $stars_count > 1 ? 's' : '' ?>
                        </h4>
                    </div>

                    <div class="card-body">
                        <ul class="list-unstyled mt-3 mb-4">
                            <li><i class="fas fa-check text-success"></i> Venda de Álbuns</li>

                            <?php if ($stars_count >= 2): ?>
                                <li><i class="fas fa-check text-success"></i> Venda de Vídeos</li>
                            <?php else: ?>
                                <li class="text-muted"><i class="fas fa-times"></i> Venda de Vídeos</li>
                            <?php endif; ?>

                            <li><i class="fas fa-arrow-up text-primary"></i> Preços de venda maiores</li>
                        </ul>

                        <hr>

                        <form action="<?= BASE_URL ?>checkout_stars.php" method="GET">
                            <input type="hidden" name="stars" value="<?= (int)$stars_count ?>">

                            <div class="mb-3">
                                <label class="form-label">Escolha o plano:</label>
                                <select name="duration" class="form-select" required>
                                    <?php foreach ($plans as $duration_type => $plan): ?>
                                        <option value="<?= htmlspecialchars($duration_type) ?>">
                                            <?= $duration_type === 'monthly' ? 'Mensal' : 'Anual' ?>
                                            — <?= number_format($plan['price'], 2) ?> MT
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-lg btn-block btn-outline-warning w-100">
                                Comprar Agora
                            </button>
                        </form>
                    </div>

                </div>
            </div>
        <?php endforeach; ?>

    </div>
</div>
