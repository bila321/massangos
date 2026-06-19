<?php
/**
 * Partial: _form_login.php
 * Formulário de autenticação — exclusivo do login.
 *
 * @var string $csrf_token
 */
?>
<form action="" method="POST" class="auth-form" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <!-- Usuário ou E-mail -->
    <div class="form-group">
        <label for="username_email">Usuário ou E-mail</label>
        <div class="auth-input-wrap">
            <i class="ti ti-user auth-input-icon" aria-hidden="true"></i>
            <input
                type="text"
                id="username_email"
                name="username_email"
                class="form-control"
                value="<?= htmlspecialchars($_POST['username_email'] ?? '') ?>"
                placeholder="seu@email.com ou @usuario"
                autocomplete="username"
                required>
        </div>
    </div>

    <!-- Senha -->
    <div class="form-group">
        <label for="password">Senha</label>
        <div class="auth-input-wrap">
            <i class="ti ti-lock auth-input-icon" aria-hidden="true"></i>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                placeholder="Sua senha"
                autocomplete="current-password"
                required>
            <button
                type="button"
                class="auth-password-toggle"
                aria-label="Mostrar/ocultar senha"
                data-target="password">
                <i class="ti ti-eye" aria-hidden="true"></i>
            </button>
        </div>
        <a href="<?= BASE_URL ?>forgot_password.php" class="auth-forgot">
            Esqueceu a senha?
        </a>
    </div>

    <button type="submit" class="btn-auth-submit">Entrar</button>
</form>
