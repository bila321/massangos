<?php
/**
 * View: login.view.php
 *
 * Layout HTML completo da página de login.
 * Reutiliza os partials partilhados com register:
 *   _head.php, _panel_left.php, _panel_mobile.php,
 *   _alerts.php, _social_buttons.php
 *
 * Variáveis disponíveis (definidas no LoginController):
 *   @var string $slides_json
 *   @var string $csrf_token
 *   @var array  $errors
 *   @var string $success_message
 */

// Caminho base dos partials auth (partilhados com register)
$AUTH_VIEWS = __DIR__ . '/';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php require $AUTH_VIEWS . '_head.php'; ?>
</head>
<body>
    <div class="auth-page">
        <?php require $AUTH_VIEWS . '_panel_left.php'; ?>
        <?php require $AUTH_VIEWS . '_panel_mobile.php'; ?>

        <!-- ════ PAINEL DIREITO — Formulário de login ════ -->
        <div class="auth-right">
            <div class="auth-card">

                <div class="auth-header">
                    <h2>Bem-vindo de volta 👋</h2>
                    <p>Acesse sua conta para continuar</p>
                </div>

                <?php require $AUTH_VIEWS . '_alerts.php'; ?>
                <?php require $AUTH_VIEWS . '_form_login.php'; ?>

                <div class="auth-divider">ou continue com</div>

                <?php require $AUTH_VIEWS . '_social_buttons.php'; ?>

                <div class="auth-footer">
                    <p>Não tem uma conta?
                        <a href="<?= BASE_URL ?>register.php">Cadastre-se grátis</a>
                    </p>
                </div>

            </div>
        </div>

    </div>

    <!-- Carrossel JS (partilhado com register via window.CAROUSEL_SLIDES) -->
    <script src="<?= BASE_URL ?>assets/js/pages/login.js"></script>
</body>
</html>
