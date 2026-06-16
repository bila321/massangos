<!-- Lightbox Portal Fix: garante que o modal é filho directo do <body>
     e que o position:fixed cobre o viewport inteiro, independentemente
     de qualquer container com transform/overflow/will-change. -->
<style>
    #feedLightbox {
        position: fixed !important;
        inset: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 99999 !important;
        /* Isolar stacking context próprio */
        isolation: isolate;
        /* Reset de qualquer transform herdado */
        transform: none !important;
        will-change: auto !important;
        overflow: hidden !important;
        /* Escondido por defeito */
        display: none;
        background: rgba(0, 0, 0, 0.97);
    }

    #feedLightbox.active {
        display: flex !important;
    }
</style>
<script>
    // Portal: move #feedLightbox para document.body imediatamente após o DOM estar pronto.
    // Isto quebra qualquer stacking context criado por containers com transform/overflow.
    (function() {
        function portalLightbox() {
            var lb = document.getElementById('feedLightbox');
            if (lb && lb.parentElement !== document.body) {
                document.body.appendChild(lb);
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', portalLightbox);
        } else {
            portalLightbox();
        }
    })();
</script>

<!-- Lightbox Modal -->
<div id="feedLightbox" class="photo-lightbox-modal">
    <div class="close-lightbox" data-action="close-lightbox">
        <i class="fa-solid fa-xmark"></i>
    </div>

    <div class="photo-lightbox-content">
        <!-- Navegação Esquerda (Desktop) -->
        <div class="reels-scroll-nav">
            <button class="scroll-nav-btn" onclick="scrollToReelByOffset(-1)">
                <i class="fa-solid fa-chevron-up"></i>
            </button>
            <button class="scroll-nav-btn" onclick="scrollToReelByOffset(1)">
                <i class="fa-solid fa-chevron-down"></i>
            </button>
        </div>

        <!-- Área Central do Vídeo -->
        <div class="photo-display-area">
            <div id="lightboxScrollContainer">
                <!-- Reels items injected via JS -->
            </div>
        </div>

        <!-- Sidebar Direita (Comentários e Info) -->
        <div class="photo-sidebar">
            <div class="photo-sidebar-header">
                <h3>Comentários</h3>
                <button class="sidebar-close-btn" data-action="close-sidebar"
                    style="background:none; border:none; color:#fff; cursor:pointer; font-size:20px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="photo-sidebar-body" id="lightboxCommentsArea">
                <!-- Comments injected via JS -->
            </div>

            <div class="photo-comment-form-area">
                <?php if (is_logged_in()): ?>
                    <form id="lightboxCommentForm" class="photo-comment-form">
                        <div class="comment-input-wrapper">
                            <input type="text" id="lightboxCommentInput"
                                placeholder="Escreva um comentário..." autocomplete="off">
                            <button type="submit">Enviar</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="login-to-comment"
                        style="padding:10px; text-align:center; color:#b0b3b8; font-size:14px;">
                        Faça <a href="<?= BASE_URL ?>login.php"
                            style="color:var(--reels-accent); text-decoration:none; font-weight:600;">login</a> para comentar.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>