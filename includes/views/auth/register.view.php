<?php
/**
 * View: register.view.php
 *
 * Layout HTML completo da página de registo.
 * (Esta página não usa header.php/footer.php — tem o seu próprio HTML.)
 *
 * Variáveis disponíveis (definidas no RegisterController):
 *   @var array  $carousel_slides
 *   @var string $csrf_token
 *   @var string $slides_json
 *   @var array  $errors
 *   @var string $success_message
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php require __DIR__ . '/_head.php'; ?>
</head>
<body>
    <div class="auth-page">
        <?php require __DIR__ . '/_panel_left.php'; ?>
        <?php require __DIR__ . '/_panel_mobile.php'; ?>
        <?php require __DIR__ . '/_panel_right.php'; ?>
    </div>
    <script src="<?= BASE_URL ?>assets/js/pages/register.js"></script>
</body>
</html>
