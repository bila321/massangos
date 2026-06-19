<?php /** @var string $csrf_token */ ?>
<form action="" method="POST" class="auth-form" novalidate>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <!-- Nome de usuário -->
    <div class="form-group">
        <label for="username">Nome de Usuário</label>
        <div class="auth-input-wrap">
            <i class="ti ti-user auth-input-icon" aria-hidden="true"></i>
            <input type="text" id="username" name="username" class="form-control"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                placeholder="Escolha um nome de usuário"
                autocomplete="username" required>
        </div>
    </div>

    <!-- E-mail -->
    <div class="form-group">
        <label for="email">E-mail</label>
        <div class="auth-input-wrap">
            <i class="ti ti-mail auth-input-icon" aria-hidden="true"></i>
            <input type="email" id="email" name="email" class="form-control"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                placeholder="seu@email.com"
                autocomplete="email" required>
        </div>
    </div>

    <!-- Senha -->
    <div class="form-group">
        <label for="password">Senha</label>
        <div class="auth-input-wrap">
            <i class="ti ti-lock auth-input-icon" aria-hidden="true"></i>
            <input type="password" id="password" name="password" class="form-control"
                placeholder="Mínimo 6 caracteres"
                autocomplete="new-password" required>
            <button type="button" class="auth-password-toggle"
                aria-label="Mostrar/ocultar senha"
                data-target="password">
                <i class="ti ti-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <!-- Confirmar senha -->
    <div class="form-group">
        <label for="confirm_password">Confirmar Senha</label>
        <div class="auth-input-wrap">
            <i class="ti ti-lock auth-input-icon" aria-hidden="true"></i>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                placeholder="Repita sua senha"
                autocomplete="new-password" required>
            <button type="button" class="auth-password-toggle"
                aria-label="Mostrar/ocultar senha"
                data-target="confirm_password">
                <i class="ti ti-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <button type="submit" class="btn-auth-submit">Cadastrar</button>
</form>
