<?php
/**
 * @var bool  $can_view_content
 * @var array $enriched_feed
 * @var bool  $is_admin
 * @var bool  $is_owner
 */
?>
<!-- ============================================================
     Conteúdo do Perfil
     ============================================================ -->
<div class="profile-feed-col">
    <div id="profileContentFiltered">

        <?php if (!$can_view_content): ?>

            <!-- Perfil privado -->
            <div class="private-profile-message"
                style="grid-column:1/-1;padding:60px;text-align:center;background:#f9f9f9;">
                <i class="fa-solid fa-lock" style="font-size:3rem;color:#ccc;margin-bottom:20px;"></i>
                <h3>Este perfil é privado</h3>
                <p style="color:#666;">Siga este utilizador para ver as suas publicações.</p>
            </div>

        <?php elseif (!empty($enriched_feed)): ?>

            <?php foreach ($enriched_feed as $item): ?>
                <?php
                // Desempacotar o item para os partials de feed
                $content_data           = $item['content_data'];
                $author                 = $item['author'];
                $like_info              = $item['like_info'];
                $user_vote              = $item['user_vote'];
                $comment_count          = $item['comment_count'];
                $ai_analysis            = $item['ai_analysis'];
                $is_post_owner          = $item['is_post_owner'];
                $is_admin               = $item['is_admin'];
                $should_blur            = $item['should_blur'];
                $isRepost               = $item['isRepost'];
                $sharedData             = $item['sharedData'];
                $sharedType             = $item['sharedType'];
                $sharedAuthor           = $item['sharedAuthor'];
                $sharedId               = $item['sharedId'];
                $can_see_sale_indicator = $item['can_see_sale_indicator'];

                // Variáveis de grid
                $display_type      = $item['item_type'];
                $feed_item_id      = $item['feed_item_id'];
                $grid_is_paid      = !empty($content_data['is_for_sale']);
                $grid_is_sensitive = !empty($content_data['is_sensitive'])
                    || in_array($ai_analysis['risk_level'] ?? '', ['medium', 'high']);
                ?>

                <!-- ── Card de Feed ───────────────────────────────────── -->
                <article class="post-card card feed-item-wrapper <?= $item['item_type'] === 'album' ? 'album-card-style' : '' ?>"
                    data-type="all"
                    data-feed-item-id="<?= (int)$item['feed_item_id'] ?>">

                    <?php include __DIR__ . '/../../feed/_post-header.php'; ?>

                    <div class="post-content">
                        <?php if ($isRepost && $sharedData && $sharedAuthor): ?>
                            <?php include __DIR__ . '/../../feed/_repost-content.php'; ?>
                        <?php else: ?>
                            <?php include __DIR__ . '/../../feed/_' . $item['item_type'] . '-media.php'; ?>
                        <?php endif; ?>
                    </div>

                    <?php include __DIR__ . '/../../feed/_post-footer.php'; ?>

                </article>

                <!-- ── Item de Grid (filtros Fotos/Vídeos/Álbuns) ─────── -->
                <?php require __DIR__ . '/_grid_item.php'; ?>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="no-posts-message"
                style="grid-column:1/-1;padding:60px;text-align:center;color:#666;">
                <i class="fa-regular fa-folder-open"
                    style="font-size:3rem;color:#ccc;margin-bottom:20px;display:block;"></i>
                <p>Nenhuma publicação encontrada.</p>
            </div>

        <?php endif; ?>

    </div><!-- /#profileContentFiltered -->

    <div id="loadMoreContainer" style="text-align:center;margin:20px 0;display:none;">
        <button id="loadMoreBtn" class="btn btn-primary"
            style="background:var(--primary-gradient);border:none;padding:10px 30px;border-radius:20px;cursor:pointer;">
            Carregar Mais
        </button>
    </div>

</div><!-- /.profile-feed-col -->
