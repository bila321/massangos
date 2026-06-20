<?php
/**
 * View: forgot_password.view.php
 *
 * Layout HTML completo (página standalone, sem header.php/footer.php).
 *
 * Variáveis disponíveis (definidas no ForgotPasswordController):
 *   @var string     $step        'request' | 'sent' | 'reset'
 *   @var list<string> $errors
 *   @var array|null $token_row
 *   @var string     $raw_token
 *   @var string     $csrf_token
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php require __DIR__ . '/_forgot_head.php'; ?>
</head>
<body>

    <div class="auth-container">
        <div class="auth-card">

            <a href="<?= BASE_URL ?>login.php" class="auth-back-link">
                <i class="ti ti-arrow-left"></i> Voltar ao login
            </a>

            <?php require __DIR__ . '/_alerts.php'; ?>

            <?php if ($step === 'request'): ?>
                <?php require __DIR__ . '/_forgot_step_request.php'; ?>
            <?php elseif ($step === 'sent'): ?>
                <?php require __DIR__ . '/_forgot_step_sent.php'; ?>
            <?php elseif ($step === 'reset' && $token_row): ?>
                <?php require __DIR__ . '/_forgot_step_reset.php'; ?>
            <?php endif; ?>

        </div><!-- /.auth-card -->
    </div><!-- /.auth-container -->

    <script src="<?= BASE_URL ?>assets/js/pages/forgot_password.js"></script>
</body>
</html>
