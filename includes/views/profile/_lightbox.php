<!-- ============================================================
     Lightbox Premium
     ============================================================ -->
<div id="feedLightbox" class="photo-lightbox-modal">
    <div class="close-lightbox" data-action="close-lightbox">
        <i class="fa-solid fa-xmark"></i>
    </div>
    <div class="photo-lightbox-content">
        <div class="reels-scroll-nav">
            <button class="scroll-nav-btn" onclick="scrollToReelByOffset(-1)">
                <i class="fa-solid fa-chevron-up"></i>
            </button>
            <button class="scroll-nav-btn" onclick="scrollToReelByOffset(1)">
                <i class="fa-solid fa-chevron-down"></i>
            </button>
        </div>
        <div class="photo-display-area">
            <div id="lightboxScrollContainer">
                <!-- Reels items injetados via JS -->
            </div>
        </div>
        <div class="photo-sidebar">
            <div class="photo-sidebar-header">
                <h3>Comentários</h3>
                <button class="sidebar-close-btn" data-action="close-sidebar"
                    style="background:none;border:none;color:#fff;cursor:pointer;font-size:20px;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="photo-sidebar-body" id="lightboxCommentsArea">
                <!-- Comments injetados via JS -->
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
                    <p class="login-to-comment">
                        Faça <a href="<?= BASE_URL ?>login.php">login</a> para comentar.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
