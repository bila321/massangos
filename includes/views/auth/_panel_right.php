<?php
/**
 * @var array  $errors
 * @var string $success_message
 * @var string $csrf_token
 */
?>
<!-- ════════════════════════════════════
     PAINEL DIREITO — Formulário de registo
     ════════════════════════════════════ -->
<div class="auth-right">
    <div class="auth-card">

        <div class="auth-header">
            <h2>Criar conta 🚀</h2>
            <p>Junte-se à comunidade massangos</p>
        </div>

        <?php require __DIR__ . '/_alerts.php'; ?>
        <?php require __DIR__ . '/_form_register.php'; ?>

        <div class="auth-divider">ou cadastre-se com</div>

        <?php require __DIR__ . '/_social_buttons.php'; ?>

        <div class="auth-footer">
            <p>Já tem uma conta? <a href="<?= BASE_URL ?>login.php">Faça login</a></p>
        </div>

    </div>
</div>
