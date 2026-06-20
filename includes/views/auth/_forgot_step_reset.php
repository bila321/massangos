<?php
/**
 * @var array  $token_row
 * @var string $csrf_token
 * @var string $raw_token
 */
$min_len = defined('MIN_PASSWORD_LENGTH') ? MIN_PASSWORD_LENGTH : 8;
?>
<!-- ══ ETAPA 3 — Nova senha ══ -->
<div class="auth-icon-wrap">
    <i class="ti ti-key"></i>
</div>

<div class="auth-header">
    <h2>Criar nova senha</h2>
    <p>Olá, <strong><?= htmlspecialchars($token_row['username']) ?></strong>. Escolha uma senha segura para a sua conta.</p>
</div>

<form action="" method="POST" class="auth-form" id="resetForm" novalidate>
    <input type="hidden" name="action" value="reset">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="token" value="<?= htmlspecialchars($raw_token) ?>">

    <!-- Nova senha -->
    <div class="form-group">
        <label for="password">Nova Senha</label>
        <div class="input-pw-wrap">
            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                placeholder="Mínimo <?= $min_len ?> caracteres"
                autocomplete="new-password"
                required
                autofocus>
            <button type="button" class="pw-toggle" data-target="password" aria-label="Mostrar senha">
                <i class="ti ti-eye"></i>
            </button>
        </div>

        <div class="strength-bar">
            <div class="strength-fill" id="strengthFill"></div>
        </div>
        <p class="strength-label" id="strengthLabel"></p>

        <ul class="pw-requirements" id="pwReqs">
            <li id="req-len"><i class="ti ti-circle"></i> Mínimo <?= $min_len ?> caracteres</li>
            <li id="req-upper"><i class="ti ti-circle"></i> Uma letra maiúscula (A–Z)</li>
            <li id="req-num"><i class="ti ti-circle"></i> Um número (0–9)</li>
        </ul>
    </div>

    <!-- Confirmar senha -->
    <div class="form-group">
        <label for="confirm_password">Confirmar Nova Senha</label>
        <div class="input-pw-wrap">
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                class="form-control"
                placeholder="Repita a nova senha"
                autocomplete="new-password"
                required>
            <button type="button" class="pw-toggle" data-target="confirm_password" aria-label="Mostrar senha">
                <i class="ti ti-eye"></i>
            </button>
        </div>
        <p class="match-label" id="matchLabel"></p>
    </div>

    <button type="submit" class="btn-auth-submit">
        <i class="ti ti-shield-check auth-btn-icon"></i>Guardar nova senha
    </button>
</form>

<div class="auth-footer">
    <p><a href="<?= BASE_URL ?>login.php">Cancelar e voltar ao login</a></p>
</div>

<script>
    window.MIN_PASSWORD_LENGTH = <?= (int)$min_len ?>;
</script>
