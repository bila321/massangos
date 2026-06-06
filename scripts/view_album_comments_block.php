<!-- Comentários inline -->
<div class="comments-area comment-section-full va-album-comments"
     id="vaCommentsSection"
     data-feed-item-id="<?= htmlspecialchars($feed_item_id) ?>">

    <div class="comments-header">
        <i class="fa-regular fa-message"></i>
        Comentários
        <span style="color:var(--c-text-muted);font-weight:400;font-size:0.78rem;" id="vaPageCommentCountLabel">
            (<?= (int)$comment_count ?>)
        </span>
    </div>

    <!-- ▼ FORM NO TOPO (sticky via CSS) -->
    <?php if ($current_user_id): ?>
    <div class="comment-form-with-avatar">
        <img src="<?= UPLOAD_URL . htmlspecialchars($me_pic) ?>"
             alt="Tu" class="comment-avatar">
        <div class="comment-input-container">
            <textarea
                id="vaCommentInput"
                class="comment-input-container__textarea"
                placeholder="Escreve um comentário no álbum…"
                rows="1"
                aria-label="Escreve um comentário"
                data-feed-item-id="<?= htmlspecialchars($feed_item_id) ?>"></textarea>
            <button class="btn-send-comment"
                    onclick="vaSubmitComment('vaCommentInput')"
                    title="Enviar"
                    aria-label="Enviar comentário">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- ▼ LISTA DE COMENTÁRIOS -->
    <div class="comments-list" id="vaCommentsListInline">
        <?php
        // Misturar comentários do álbum e de fotos, ordenados por data
        $all_comments_merged = [];

        foreach ($comment_tree as $c) {
            $type = !empty($c['source_photo_id']) ? 'photo' : 'album';
            $all_comments_merged[] = [
                'type'       => $type,
                'created_at' => $c['created_at'],
                'data'       => $c,
            ];
        }

        // Ordenar por data (mais recente primeiro — coerente com form no topo)
        usort($all_comments_merged, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

        if (!empty($all_comments_merged)):
            foreach ($all_comments_merged as $item):
                if ($item['type'] === 'album'):
                    // Comentário normal do álbum
                    display_comments([$item['data']], $current_user_id, $is_owner, $pdo);
                else:
                    // Comentário de foto — com marcador clicável
                    $pc            = $item['data'];
                    $idx           = (int)($pc['photo_js_idx'] ?? 0);
                    $num           = (int)($pc['photo_index'] ?? $pc['photo_position'] ?? 0);
                    $pic           = htmlspecialchars($pc['profile_picture'] ?? 'profiles/default_profile.png');
                    $uname         = htmlspecialchars($pc['username'] ?? '');
                    $raw_content   = $pc['content'] ?? '';
                    $raw_content   = preg_replace('/^\s*(?:\[(?:Foto|Photo)\s*#?\d+\]|\b(?:Foto|Photo)\s*#?\d+\b)\s*[:\-–—]?\s*/iu', '', $raw_content);
                    $content       = htmlspecialchars($raw_content);
                    $likes_pc      = (int)($pc['likes_count'] ?? 0);
                    $user_liked_pc = !empty($pc['user_liked']);
                    $pc_id         = (int)($pc['id'] ?? 0);
                    $pc_photo_id   = (int)($pc['photo_id'] ?? 0);
        ?>
            <div class="comment-item va-comment-from-photo"
                 data-comment-id="<?= $pc_id ?>"
                 data-photo-id="<?= $pc_photo_id ?>"
                 data-photo-idx="<?= $idx ?>">

                <img src="<?= UPLOAD_URL . $pic ?>"
                     alt="<?= $uname ?>"
                     class="comment-avatar"
                     onclick="vaOpenLightbox(<?= $idx ?>)"
                     style="cursor:pointer;"
                     onerror="this.src='<?= UPLOAD_URL ?>profiles/default_profile.png'">

                <div class="comment-body">
                    <div class="comment-text-wrapper">
                        <div class="comment-header">
                            <span class="comment-author"><?= $uname ?></span>
                            <span class="va-photo-comment-tag"
                                  onclick="vaOpenLightbox(<?= $idx ?>)"
                                  title="Abrir Foto #<?= $num ?>"
                                  style="cursor:pointer;">
                                <i class="fa-solid fa-image"></i>
                                Foto #<?= $num ?>
                                <i class="fa-solid fa-arrow-up-right-from-square"
                                   style="font-size:9px;opacity:0.6;"></i>
                            </span>
                        </div>
                        <div class="comment-text">
                            <p><?= $content ?></p>
                        </div>
                    </div>

                    <div class="comment-actions">
                        <span class="comment-time"><?= format_datetime_ago($pc['created_at']) ?></span>
                        <button class="btn-comment-like va-pc-like-btn <?= $user_liked_pc ? 'active' : '' ?>"
                                data-comment-id="<?= $pc_id ?>"
                                data-photo-id="<?= $pc_photo_id ?>"
                                onclick="vaTogglePhotoCommentLike(this); event.stopPropagation();">
                            Gosto <span class="comment-likes-count"><?= $likes_pc ?></span>
                        </button>
                        <button class="btn-comment-dislike" disabled
                                title="Indisponível para comentários de fotos">
                            Não gosto
                        </button>
                        <?php if ($current_user_id): ?>
                            <button class="btn-reply-comment va-pc-reply-btn"
                                    data-comment-id="<?= $pc_id ?>"
                                    data-photo-id="<?= $pc_photo_id ?>"
                                    data-photo-idx="<?= $idx ?>"
                                    data-author="<?= $uname ?>"
                                    onclick="vaOpenLightbox(<?= $idx ?>); event.stopPropagation();">
                                Responder
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php
                endif;
            endforeach;
        else: ?>
            <div class="no-comments">
                <i class="fa-regular fa-comment-dots"></i>
                Sem comentários. Sê o primeiro!
            </div>
        <?php endif; ?>
    </div>

</div>
