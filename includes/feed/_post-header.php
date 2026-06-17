<?php

use Massango\Models\User;
// Garantir que a classe User está carregada
if (!class_exists('User')) {
    require_once dirname(__DIR__, 2) . '/app/Models/User.php';
    // ajusta o caminho conforme o resultado do findstr acima
}
?>
<div class="post-header">
    <div class="header-author-wrapper">

        <!-- Avatares (principal + secundário no caso de repost) -->
        <div class="avatar-container">
            <img src="<?= UPLOAD_URL . htmlspecialchars($author['profile_picture'] ?? 'default_profile.png') ?>"
                alt="Foto de perfil"
                class="profile-thumb">
            <?php if ($isRepost && $sharedAuthor): ?>
                <img src="<?= UPLOAD_URL . htmlspecialchars($sharedAuthor['profile_picture'] ?? 'default_profile.png') ?>"
                    alt="Original"
                    class="profile-thumb-secondary">
            <?php endif; ?>
        </div>

        <div class="post-info">
            <div class="author-line">

                <!-- Autor principal + verificação -->
                <a href="<?= BASE_URL ?>profile.php?id=<?= htmlspecialchars($author['id']) ?>"
                    class="post-author"><?= htmlspecialchars($author['username']) ?></a>

                <?php if (!empty($author['is_verified'])): ?>
                    <button class="verficacao-btn-mini" title="Conta Verificada">
                        <i class="fa-solid fa-circle-check"></i> VERIFICADO
                    </button>
                <?php endif; ?>

                <!-- Botão de seguir o autor principal -->
                <?php if (is_logged_in() && !$is_post_owner): ?>
                    <?php
                    $follow_label = $item['follow_label'];
                    $follow_class = $item['follow_class'];
                    ?>
                    <button class="follow-btn-mini <?= $follow_class ?>"
                        onclick="App.toggleFollow(<?= (int)$author['id'] ?>, this)"
                        data-user-id="<?= (int)$author['id'] ?>">
                        <?= $follow_label ?>
                    </button>
                <?php endif; ?>

                <!-- Indicador de repost + autor original -->
                <?php if ($isRepost && $sharedAuthor): ?>
                    <i class="fa-solid fa-arrows-retweet repost-icon-small"></i>
                    <a href="<?= BASE_URL ?>profile.php?id=<?= $sharedAuthor['id'] ?>"
                        class="shared-username-link">
                        <?= htmlspecialchars($sharedAuthor['username']) ?>
                    </a>

                    <?php if (!empty($sharedAuthor['is_verified'])): ?>
                        <button class="verficacao-btn-mini" title="Conta Verificada">
                            <i class="fa-solid fa-circle-check"></i> VERIFICADO
                        </button>
                    <?php endif; ?>

                    <?php if (is_logged_in() && $current_user_id != $sharedAuthor['id']): ?>
                        <?php
                        $is_following_shared  = User::isFollowing($pdo, $current_user_id, $sharedAuthor['id']);
                        $has_request_shared   = User::hasPendingFollowRequest($pdo, $current_user_id, $sharedAuthor['id']);
                        $follow_label_shared  = $is_following_shared ? 'Seguindo' : ($has_request_shared ? 'Pendente' : 'Seguir');
                        $follow_class_shared  = $is_following_shared ? 'following' : ($has_request_shared ? 'pending' : '');
                        ?>
                        <button class="follow-btn-mini <?= $follow_class_shared ?>"
                            onclick="App.toggleFollow(<?= (int)$sharedAuthor['id'] ?>, this)"
                            data-user-id="<?= (int)$sharedAuthor['id'] ?>">
                            <?= $follow_label_shared ?>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Badge "À Venda" (visível apenas para dono/admin) -->
                <?php if ($can_see_sale_indicator): ?>
                    <div class="sale-indicator"
                        style="background: var(--premium-gradient); color: white; padding: 5px 5px;
                                border-radius: 20px; font-size: 0.75rem; font-weight: bold;
                                display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-tag"></i> À VENDA
                        <?php if (isset($item['is_approved']) && !$item['is_approved']): ?>
                            <span style="background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 10px; margin-left: 5px;">
                                <i class="fa-regular fa-hourglass-half"></i> Pendente
                            </span>
                        <?php else: ?>
                            <span style="background: rgba(0,0,0,0.2); padding: 2px 8px; border-radius: 10px; margin-left: 5px;">
                                <i class="fa-solid fa-circle-check"></i> Ativo
                            </span>
                        <?php endif; ?>
                        <a href="sales_performance.php?type=<?= $item['item_type'] ?>&id=<?= $item['id'] ?>"
                            title="Ver Desempenho de Vendas"
                            style="color: white; margin-left: 10px; background: rgba(255,255,255,0.2);
                                  width: 24px; height: 24px; display: flex; align-items: center;
                                  justify-content: center; border-radius: 50%; text-decoration: none;">
                            <i class="fa-solid fa-chart-line" style="font-size: 0.7rem;"></i>
                        </a>
                    </div>
                <?php endif; ?>

            </div><!-- /.author-line -->

            <!-- Data + badges de IA -->
            <span class="post-date"><?= format_datetime_ago($item['created_at']) ?></span>

            <?php if ($ai_analysis): ?>
                <?php if (in_array($ai_analysis['status'], ['processing', 'pending'])): ?>
                    <span class="ai-badge ai-badge-analyzing">
                        <i class="fa-regular fa-wand-magic-sparkles"></i> Em análise
                    </span>
                <?php elseif ($ai_analysis['status'] === 'done'): ?>
                    <?php if ($ai_analysis['risk_level'] === 'medium'): ?>
                        <span class="ai-badge ai-badge-sensitive"><i class="fa-solid fa-circle-dot"></i></span>
                    <?php elseif ($ai_analysis['risk_level'] === 'high'): ?>
                        <span class="ai-badge ai-badge-high"><i class="fa-solid fa-circle-dot"></i></span>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

        </div><!-- /.post-info -->
    </div><!-- /.header-author-wrapper -->

    <!-- Ações do lado direito: ocultar + dropdown editar/apagar -->
    <div class="header-right-actions">

        <?php if (is_logged_in() && !$is_post_owner): ?>
            <button class="hide-post-btn"
                onclick="hidePost(<?= (int)$item['feed_item_id'] ?>, this)"
                title="Ocultar publicação">
                <i class="fa-regular fa-eye-slash"></i>
            </button>
        <?php endif; ?>

        <?php if ($is_post_owner || $is_admin): ?>
            <div class="post-actions-dropdown">
                <button class="dropdown-toggle" aria-label="Opções do post">&#x22EE;</button>
                <div class="dropdown-menu">

                    <?php if ($item['item_type'] === 'post'): ?>
                        <?php if ($is_post_owner): ?>
                            <a href="<?= BASE_URL ?>edit_post.php?id=<?= htmlspecialchars($item['item_id']) ?>&redirect_to=index.php">Editar Publicação</a>
                        <?php endif; ?>
                        <form action="<?= BASE_URL ?>actions/post.php" method="POST"
                            onsubmit="return confirm('Tem certeza que deseja apagar esta publicação?');">
                            <input type="hidden" name="action" value="delete_post">
                            <input type="hidden" name="post_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                            <input type="hidden" name="redirect_to" value="index.php">
                            <button type="submit">
                                <?= ($is_admin && !$is_post_owner) ? 'Bloquear/Apagar' : 'Apagar Publicação' ?>
                            </button>
                        </form>

                    <?php elseif ($item['item_type'] === 'video'): ?>
                        <?php if ($is_post_owner): ?>
                            <a href="<?= BASE_URL ?>edit_video.php?id=<?= htmlspecialchars($item['item_id']) ?>&redirect_to=index.php">Editar Vídeo</a>
                        <?php endif; ?>
                        <form action="<?= BASE_URL ?>actions/video.php" method="POST"
                            onsubmit="return confirm('Tem certeza que deseja apagar este vídeo?');">
                            <input type="hidden" name="action" value="delete_video">
                            <input type="hidden" name="video_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                            <input type="hidden" name="redirect_to" value="index.php">
                            <button type="submit">
                                <?= ($is_admin && !$is_post_owner) ? 'Bloquear/Apagar' : 'Apagar Vídeo' ?>
                            </button>
                        </form>

                    <?php elseif ($item['item_type'] === 'album'): ?>
                        <?php if ($is_post_owner): ?>
                            <a href="<?= BASE_URL ?>edit_album.php?id=<?= htmlspecialchars($item['item_id']) ?>&redirect_to=index.php">Editar Álbum</a>
                        <?php endif; ?>
                        <form action="<?= BASE_URL ?>actions/album.php" method="POST"
                            onsubmit="return confirm('Tem certeza que deseja apagar este álbum?');">
                            <input type="hidden" name="action" value="delete_album">
                            <input type="hidden" name="album_id" value="<?= htmlspecialchars($item['item_id']) ?>">
                            <input type="hidden" name="redirect_to" value="index.php">
                            <button type="submit">
                                <?= ($is_admin && !$is_post_owner) ? 'Bloquear/Apagar' : 'Apagar Álbum' ?>
                            </button>
                        </form>
                    <?php endif; ?>

                </div><!-- /.dropdown-menu -->
            </div><!-- /.post-actions-dropdown -->
        <?php endif; ?>

    </div><!-- /.header-right-actions -->
</div><!-- /.post-header -->