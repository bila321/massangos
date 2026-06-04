<?php
// public/buy_stars.php
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

$user_id = get_current_user_id();
$user = fetchOne("SELECT stars, stars_expiration FROM users WHERE id = ?", [$user_id]);

// Carregar preços das estrelas
$star_prices = fetchAll("SELECT * FROM star_prices ORDER BY stars ASC, duration_type ASC");

include __DIR__ . '/../includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-12 text-center mb-4">
            <h2><i class="fas fa-star text-warning"></i> Compre Estrelas</h2>
            <p class="lead">Aumente seu nível para vender conteúdos por preços maiores.</p>
            <div class="alert alert-info d-inline-block">
                Seu nível atual: <strong><?= $user['stars'] ?> Estrela<?= $user['stars'] != 1 ? 's' : '' ?></strong>
                <?php if ($user['stars_expiration']): ?>
                    <br><small>Expira em: <?= date('d/m/Y H:i', strtotime($user['stars_expiration'])) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <?php 
        $current_stars = 0;
        $first = true;
        foreach ($star_prices as $price): 
            if ($price['stars'] != $current_stars):
                if (!$first) {
                    echo '</select></div><button type="submit" class="btn btn-lg btn-block btn-outline-warning w-100">Comprar Agora</button></form></div></div></div>';
                }
                $current_stars = $price['stars'];
                $first = false;
        ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm border-warning">
                    <div class="card-header bg-warning text-dark text-center">
                        <h4 class="my-0 font-weight-normal"><?= $price['stars'] ?> Estrela<?= $price['stars'] > 1 ? 's' : '' ?></h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mt-3 mb-4">
                            <li><i class="fas fa-check text-success"></i> Venda de Álbuns</li>
                            <?php if ($price['stars'] >= 2): ?>
                                <li><i class="fas fa-check text-success"></i> Venda de Vídeos</li>
                            <?php else: ?>
                                <li class="text-muted"><i class="fas fa-times"></i> Venda de Vídeos</li>
                            <?php endif; ?>
                            <li><i class="fas fa-arrow-up text-primary"></i> Preços de venda maiores</li>
                        </ul>
                        
                        <hr>
                        
                        <form action="checkout_stars.php" method="GET">
                            <input type="hidden" name="stars" value="<?= $price['stars'] ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Escolha o plano:</label>
                                <select name="duration" class="form-select" required>
        <?php endif; ?>
                                    <option value="<?= $price['duration_type'] ?>">
                                        <?= $price['duration_type'] === 'monthly' ? 'Mensal' : 'Anual' ?> - <?= number_format($price['price'], 2) ?> MT
                                    </option>
        <?php endforeach; ?>
        <?php if (!$first): ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-lg btn-block btn-outline-warning w-100">Comprar Agora</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
