<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if (!$is_ajax) {
    require_once __DIR__ . '/../includes/header.php';
}

// Buscar usuários ordenados por estrelas
try {
    $stmt = $pdo->query("SELECT id, username, profile_picture, stars 
                         FROM users 
                         ORDER BY stars DESC 
                         LIMIT 50");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}
?>

<!-- Facebook Reels-Inspired Lightbox Modal -->
<div id="feedLightbox" class="photo-lightbox-modal">
    <!-- Close Button -->
    <button class="lightbox-close-btn" data-action="close-lightbox" title="Fechar (ESC)">
        <i class="fa-solid fa-xmark"></i>
    </button>

    <!-- Main Content Layout -->
    <div class="photo-lightbox-content">
        <!-- Left Navigation (Desktop Only) -->
        <div class="reels-scroll-nav">
            <button class="scroll-nav-btn scroll-up-btn" onclick="scrollToReelByOffset(-1)" title="Vídeo anterior (↑)">
                <i class="fa-solid fa-chevron-up"></i>
            </button>
            <button class="scroll-nav-btn scroll-down-btn" onclick="scrollToReelByOffset(1)" title="Próximo vídeo (↓)">
                <i class="fa-solid fa-chevron-down"></i>
            </button>
        </div>

        <!-- Main Video Display Area -->
        <div class="photo-main-display">
            <!-- Scroll Container with Reels -->
            <div class="horizontal-scroll-container" id="lightboxScrollContainer">
                <!-- Reels items injected via JS -->
            </div>

            <!-- Volume Control (Top Left) -->
            <div class="reels-volume-container">
                <button class="reels-volume-btn" onclick="toggleGlobalMute(event)" title="Mutar/Desmutar (M)">
                    <i class="fa-solid fa-volume-high"></i>
                </button>
            </div>

            <!-- Progress Bar -->
            <div class="reels-progress-bar"></div>
        </div>

        <!-- Right Actions Sidebar (Injected by JS) -->
        <!-- Will be populated with: like, comment, share, save buttons -->

        <!-- Bottom Info Overlay (Injected by JS) -->
        <!-- Will be populated with: author, caption, timestamp -->

        <!-- Comments Sidebar (Mobile Overlay / Desktop Panel) -->
        <div class="photo-sidebar">
            <!-- Sidebar Header -->
            <div class="sidebar-header">
                <h3>Comentários</h3>
                <button class="sidebar-close-btn" onclick="closeSidebar()" title="Fechar comentários">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Sidebar Content - Comments Area -->
            <div class="sidebar-content">
                <!-- Author Info (Mobile) -->
                <div class="sidebar-author-info" id="sidebarAuthorInfo" style="display: none;">
                    <div class="author-info">
                        <img id="sidebarAuthorPic" src="" alt="Autor" class="author-thumb">
                        <div class="author-text">
                            <a id="sidebarAuthorName" href="" class="author-name"></a>
                            <span id="sidebarPostDate" class="post-date"></span>
                        </div>
                    </div>
                    <div class="post-description" id="sidebarDescription"></div>
                </div>

                <!-- Stats Bar -->
                <div class="photo-stats-bar">
                    <div class="stat-item">
                        <i class="fa-solid fa-thumbs-up"></i> <span id="lightboxLikes">0</span>
                    </div>
                    <div class="stat-item">
                        <i class="fa-solid fa-comment"></i> <span id="lightboxComments">0</span>
                    </div>
                </div>

                <!-- Comments List -->
                <div class="photo-sidebar-body" id="lightboxCommentsArea">
                    <!-- Comments injected via JS -->
                </div>
            </div>

            <!-- Comment Form -->
            <div class="photo-comment-form-area">
                <?php if (is_logged_in()): ?>
                    <form id="lightboxCommentForm" class="photo-comment-form">
                        <div class="comment-input-wrapper">
                            <input type="text" id="lightboxCommentInput" placeholder="Adicione um comentário..." autocomplete="off">
                            <button type="submit" title="Publicar comentário">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="login-to-comment">Faça <a href="<?= BASE_URL ?>login.php">login</a> para comentar.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
if (!$is_ajax) {
    require_once __DIR__ . '/../includes/footer.php';
}
?>