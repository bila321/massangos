<?php
define('SECURE_ACCESS', true);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    redirect(BASE_URL . 'login.php');
}

// Verificar se é uma requisição AJAX para o modal
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if (!$is_ajax) {
    require_once __DIR__ . '/../includes/header.php';
}

$user_id = get_current_user_id();
$stmt = $pdo->prepare("SELECT account_type FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_type = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan'])) {
    $new_plan = $_POST['plan'];
    if (in_array($new_plan, ['professional', 'premium'])) {
        $stmt = $pdo->prepare("UPDATE users SET account_type = ? WHERE id = ?");
        if ($stmt->execute([$new_plan, $user_id])) {
            set_message("Sua conta foi atualizada para " . ucfirst($new_plan) . " com sucesso!", "success");
            redirect(BASE_URL . 'profile.php?id=' . $user_id);
        }
    }
}
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/notifications.css">
<div class="main-content-area">
    <section class="upgrade-section">
        <h2 class="text-center">Escolha seu Plano</h2>
        <p class="text-center">Aumente seu alcance e recursos no massangos.</p>

        <div class="pricing-container" style="display: flex; justify-content: space-around; gap: 20px; margin-top: 30px; flex-wrap: wrap;">
            <!-- Plano Standard -->
            <div class="pricing-card <?= $current_type === 'standard' ? 'active' : '' ?>" style="border: 1px solid #ddd; padding: 20px; border-radius: 10px; width: 250px; text-align: center;">
                <h3>Standard</h3>
                <p>Grátis</p>
                <ul style="list-style: none; padding: 0; margin: 20px 0;">
                    <li>Recursos básicos</li>
                    <li>Postagens limitadas</li>
                </ul>
                <?php if ($current_type === 'standard'): ?>
                    <button class="btn btn-secondary" disabled>Plano Atual</button>
                <?php endif; ?>
            </div>

            <!-- Plano Professional -->
            <div class="pricing-card <?= $current_type === 'professional' ? 'active' : '' ?>" style="border: 2px solid #4f46e5; padding: 20px; border-radius: 10px; width: 250px; text-align: center; position: relative;">
                <span style="position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: #4f46e5; color: white; padding: 2px 10px; border-radius: 5px; font-size: 0.8em;">RECOMENDADO</span>
                <h3>Profissional</h3>
                <p>Destaque-se no feed</p>
                <ul style="list-style: none; padding: 0; margin: 20px 0;">
                    <li>Selo Profissional</li>
                    <li>Prioridade no Feed</li>
                    <li>Estatísticas Avançadas</li>
                </ul>
                <form method="POST">
                    <input type="hidden" name="plan" value="professional">
                    <button type="submit" class="btn btn-primary" <?= $current_type === 'professional' ? 'disabled' : '' ?>>
                        <?= $current_type === 'professional' ? 'Plano Atual' : 'Atualizar Agora' ?>
                    </button>
                </form>
            </div>

            <!-- Plano Premium -->
            <div class="pricing-card <?= $current_type === 'premium' ? 'active' : '' ?>" style="border: 2px solid #ffd700; padding: 20px; border-radius: 10px; width: 250px; text-align: center;">
                <h3>Premium</h3>
                <p>O máximo de recursos</p>
                <ul style="list-style: none; padding: 0; margin: 20px 0;">
                    <li>Selo Premium</li>
                    <li>Suporte Prioritário</li>
                    <li>Zero Anúncios</li>
                    <li>Recursos Exclusivos</li>
                </ul>
                <form method="POST">
                    <input type="hidden" name="plan" value="premium">
                    <button type="submit" class="btn btn-warning" style="background: #ffd700; border: none; color: #000;" <?= $current_type === 'premium' ? 'disabled' : '' ?>>
                        <?= $current_type === 'premium' ? 'Plano Atual' : 'Tornar-se Premium' ?>
                    </button>
                </form>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>