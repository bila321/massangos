<?php /** @var string $csrf_token */ ?>
<!-- ══ ETAPA 2 — Confirmação de envio ══ -->
<div class="auth-icon-wrap success-icon">
    <i class="ti ti-mail-check"></i>
</div>

<div class="auth-header">
    <h2>Verifique o seu e-mail</h2>
    <p>Enviámos um link de recuperação para:</p>
</div>

<div class="sent-email-box">
    <i class="ti ti-mail"></i>
    <?= htmlspecialchars($_POST['email'] ?? '') ?>
</div>

<p class="sent-hint">
    O link é válido por <?= (int)(PASSWORD_RESET_EXPIRY / 60) ?> minutos.<br>
    Não recebeu? Verifique a pasta de spam ou reenvie abaixo.
</p>

<form action="" method="POST" class="resend-form" novalidate>
    <input type="hidden" name="action" value="request">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    <button type="submit" class="btn-secondary">
        <i class="ti ti-rotate-clockwise"></i> Reenviar e-mail
    </button>
</form>

<div class="auth-footer">
    <p><a href="<?= BASE_URL ?>login.php">Voltar ao login</a></p>
</div>
