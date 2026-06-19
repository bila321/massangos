<?php
/**
 * Partial: _verification-invite-modal.php
 * Modal exibido quando um utilizador não verificado tenta acessar
 * conteúdo pago (chamado via openVerificationInviteModal() no JS).
 */
?>
<div id="verificationInviteModal" class="verification-invite-modal">
    <div class="verification-invite-content">
        <span class="invite-close" onclick="closeVerificationInviteModal()">&times;</span>

        <div class="invite-illustration">
            <svg viewBox="0 0 200 200" class="invite-id">
                <rect x="40" y="60" width="120" height="80" rx="12" />
                <circle cx="75" cy="95" r="15" />
                <rect x="100" y="85" width="45" height="6" rx="3" />
                <rect x="100" y="100" width="35" height="6" rx="3" />
            </svg>
            <div class="invite-magnifier"></div>
        </div>

        <h2>Verifique sua conta</h2>

        <p>
            Para acessar conteúdos pagos, comprar acessos ou vender publicações,
            é necessário verificar sua conta primeiro.
            <br><br>
            A verificação ajuda a manter a comunidade segura e aumenta
            a confiança entre os usuários.
        </p>

        <button class="invite-verify-btn" onclick="proceedToVerification()">
            Fazer verificação
        </button>
    </div>
</div>
