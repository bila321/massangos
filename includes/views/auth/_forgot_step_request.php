<?php /** @var string $csrf_token */ ?>
<!-- ══ ETAPA 1 — Solicitar e-mail ══ -->
<div class="auth-icon-wrap">
    <i class="ti ti-lock-open"></i>
</div>

<div class="auth-header">
    <h2>Esqueceu a senha?</h2>
    <p>Sem problema — introduza o seu e-mail e enviaremos um link de recuperação.</p>
</div>

<form action="" method="POST" class="auth-form" novalidate>
    <input type="hidden" name="action" value="request">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <div class="form-group">
        <label for="email">Endereço de E-mail</label>
        <input
            type="email"
            id="email"
            name="email"
            class="form-control"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
            placeholder="o-seu@email.com"
            autocomplete="email"
            required
            autofocus>
    </div>

    <button type="submit" class="btn-auth-submit">
        <i class="ti ti-send auth-btn-icon"></i>Enviar link de recuperação
    </button>
</form>

<div class="auth-footer">
    <p>Lembrou-se da senha? <a href="<?= BASE_URL ?>login.php">Entrar</a></p>
    <p class="auth-footer-secondary">Não tem conta? <a href="<?= BASE_URL ?>register.php">Cadastre-se</a></p>
</div>
