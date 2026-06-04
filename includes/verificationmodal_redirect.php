<!-- Verification Modal Redirect Bridge -->
<div id="verificationModalRedirect" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="verificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="verificationModalLabel">
                    <i class="fa-solid fa-shield-check"></i> Verificação de Identidade
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <div style="padding: 20px 0;">
                    <i class="fa-solid fa-arrow-right" style="font-size: 3rem; color: var(--primary); margin-bottom: 20px;"></i>
                    <p style="font-size: 1.1rem; margin-bottom: 10px;">
                        A verificação de identidade foi movida para uma nova página dedicada!
                    </p>
                    <p style="color: var(--text-muted); margin-bottom: 20px;">
                        Clique no botão abaixo para acessar a nova interface de verificação moderna e segura.
                    </p>
                </div>
            </div>
            <div class="modal-footer" style="justify-content: center;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="redirectToNewVerificationPage()">
                    <i class="fa-solid fa-arrow-right"></i> Ir para Verificação
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    /**
     * Redirect to new verification page
     */
    function redirectToNewVerificationPage() {
        const baseUrl = '<?php echo BASE_URL; ?>' || '/massangos/public/';
        window.location.href = baseUrl + 'verification/';
    }

    // Expose modal for backward compatibility
    window.verificationModalRedirect = {
        show: function() {
            const modal = document.getElementById('verificationModalRedirect');
            if (modal && typeof $ !== 'undefined') {
                $(modal).modal('show');
            } else if (modal) {
                modal.style.display = 'block';
            }
        },
        hide: function() {
            const modal = document.getElementById('verificationModalRedirect');
            if (modal && typeof $ !== 'undefined') {
                $(modal).modal('hide');
            } else if (modal) {
                modal.style.display = 'none';
            }
        }
    };
</script>

<style>
    #verificationModalRedirect .modal-content {
        border-radius: var(--radius-lg);
        border: 1px solid var(--border);
    }

    #verificationModalRedirect .modal-header {
        background: linear-gradient(135deg, var(--primary-soft) 0%, rgba(0, 245, 118, 0.05) 100%);
        border-bottom: 1px solid var(--border);
    }

    #verificationModalRedirect .modal-title {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--text-main);
        font-weight: var(--weight-semibold);
    }

    #verificationModalRedirect .modal-title i {
        color: var(--primary);
    }

    #verificationModalRedirect .btn-primary {
        background: var(--primary);
        border-color: var(--primary);
    }

    #verificationModalRedirect .btn-primary:hover {
        background: var(--primary-hover);
        border-color: var(--primary-hover);
    }
</style>