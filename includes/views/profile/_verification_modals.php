<!-- ── Modal de convite de verificação ── -->
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
            Para aceder a conteúdos pagos, comprar acessos ou vender publicações,
            é necessário verificar sua conta primeiro.<br><br>
            A verificação ajuda a manter a comunidade segura e aumenta
            a confiança entre os utilizadores.
        </p>
        <button class="invite-verify-btn" onclick="proceedToVerification()">
            Fazer verificação
        </button>
    </div>
</div>

<?php require_once __DIR__ . '/../../../includes/verificationmodal.php'; ?>
