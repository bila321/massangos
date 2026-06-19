<!-- ══ 3. Verificação de Criador ════════════════════════════════ -->
<div class="stg-card" data-stg-card>
    <div class="stg-card-head" data-stg-toggle>
        <div class="stg-card-head-left">
            <div class="stg-card-head-icon"><i class="fa-solid fa-id-card"></i></div>
            <div>
                <h3>Verificação de Criador</h3>
                <p>Venda e monetize o seu conteúdo</p>
            </div>
        </div>
        <i class="fa-solid fa-chevron-down stg-chevron"></i>
    </div>

    <div class="stg-card-body">
        <?php $v_status = $user_data['verification_status'] ?? 'none'; ?>

        <?php if (!empty($user_data['is_verified_creator'])): ?>
            <div class="verify-status ok">
                <i class="fa-solid fa-circle-check"></i>
                <div>
                    <strong>Perfil verificado</strong><br>
                    <span>Já pode vender e cobrar acesso ao seu conteúdo.</span>
                </div>
            </div>

        <?php elseif ($v_status === 'pending'): ?>
            <div class="verify-status wait">
                <i class="fa-solid fa-clock"></i>
                <div>
                    <strong>Verificação em análise</strong><br>
                    <span>Os seus documentos estão a ser revisados pela nossa equipa.</span>
                </div>
            </div>

        <?php else: ?>
            <p style="font-size:.85rem;color:var(--text-secondary);margin:0 0 12px;">
                Para vender conteúdo ou cobrar acesso, precisa verificar a sua identidade.
            </p>
            <button class="btn-primary"
                onclick="document.getElementById('verificationModal').style.display='flex'">
                <i class="fa-solid fa-shield-halved"></i> Iniciar verificação
            </button>
            <?php if ($v_status === 'rejected'): ?>
                <p style="color:var(--danger-color);margin-top:10px;font-size:.78rem;">
                    <i class="fa-solid fa-circle-xmark"></i>
                    Verificação anterior rejeitada. Tente novamente.
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<!-- /Verificação -->
