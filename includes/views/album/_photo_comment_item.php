<?php
/**
 * Partial: um item de comentário de foto individual.
 * Variável disponível: $item (do foreach em _comments_section.php)
 */
$pc            = $item['data'];
$idx           = (int)($pc['photo_js_idx'] ?? 0);
$num           = (int)($pc['photo_index'] ?? 0);
$pic           = htmlspecialchars($pc['profile_picture'] ?? 'profiles/default_profile.png');
$uname         = htmlspecialchars($pc['username'] ?? '');
$raw_content   = $pc['content'] ?? '';
// Remover prefixo "[Foto #N]" antigo
$raw_content   = preg_replace(
    '/^\s*(?:\[(?:Foto|Photo)\s*#?\d+\]|\b(?:Foto|Photo)\s*#?\d+\b)\s*[:\-–—]?\s*/iu',
    '',
    $raw_content
);
$content       = htmlspecialchars($raw_content);
$likes_pc      = (int)($pc['likes_count'] ?? 0);
$user_liked_pc = !empty($pc['user_liked']);
$pc_id         = (int)($pc['id'] ?? 0);
$pc_photo_id   = (int)($pc['photo_id'] ?? 0);
$pc_time       = format_datetime_ago($pc['created_at']);
$is_pc_owner   = ($current_user_id && ($pc['user_id'] ?? 0) == $current_user_id);
$can_delete_pc = ($is_pc_owner || $is_owner);
?>
<ul class="comment-list">
    <li class="comment-item va-comment-from-photo"
        data-comment-id="<?= $pc_id ?>"
        data-photo-id="<?= $pc_photo_id ?>"
        data-photo-idx="<?= $idx ?>"
        data-likes="<?= $likes_pc ?>"
        data-created-at="<?= htmlspecialchars($pc['created_at']) ?>">

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
                        <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:9px;opacity:0.6;"></i>
                    </span>

                    <?php if ($is_pc_owner || $can_delete_pc): ?>
                        <div class="comment-actions-dropdown">
                            <button class="dropdown-toggle" aria-label="Opções do comentário" aria-expanded="false">&#x22EE;</button>
                            <div class="dropdown-menu" style="display:none;">
                                <?php if ($is_pc_owner): ?>
                                    <button class="edit-comment-btn"
                                        data-comment-id="<?= $pc_id ?>"
                                        data-content="<?= htmlspecialchars($raw_content) ?>"
                                        data-source="photo">Editar</button>
                                <?php endif; ?>
                                <?php if ($can_delete_pc): ?>
                                    <button class="delete-comment-btn"
                                        data-comment-id="<?= $pc_id ?>"
                                        data-source="photo">Apagar</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="comment-text">
                    <p><?= $content ?></p>
                </div>
            </div>

            <div class="comment-actions">
                <span class="comment-time"><?= $pc_time ?></span>
                <button class="btn-comment-like <?= $user_liked_pc ? 'active' : '' ?>"
                    data-comment-id="<?= $pc_id ?>"
                    data-photo-id="<?= $pc_photo_id ?>"
                    data-source="photo"
                    data-vote-type="like">
                    <i class="fa-<?= $user_liked_pc ? 'solid' : 'regular' ?> fa-heart"></i>
                    <span class="comment-likes-count"><?= $likes_pc > 0 ? $likes_pc : '' ?></span>
                </button>
                <?php if ($current_user_id): ?>
                    <button class="btn-reply-comment va-pc-reply-btn"
                        data-comment-id="<?= $pc_id ?>"
                        data-photo-id="<?= $pc_photo_id ?>"
                        data-photo-idx="<?= $idx ?>"
                        data-author="<?= $uname ?>">
                        Responder
                    </button>
                <?php endif; ?>
            </div>

            <!-- Respostas -->
            <?php if (!empty($pc['replies'])): ?>
                <ul class="comment-list comment-replies" id="vaReplies-<?= $pc_id ?>">
                    <?php foreach ($pc['replies'] as $reply):
                        $r_uname   = htmlspecialchars($reply['username'] ?? '');
                        $r_pic     = htmlspecialchars($reply['profile_picture'] ?? 'profiles/default_profile.png');
                        $r_content = htmlspecialchars($reply['content'] ?? '');
                        $r_id      = (int)$reply['id'];
                    ?>
                        <li class="comment-item va-pc-reply" data-comment-id="<?= $r_id ?>" data-likes="0">
                            <img src="<?= UPLOAD_URL . $r_pic ?>"
                                alt="<?= $r_uname ?>"
                                class="comment-avatar"
                                onerror="this.src='<?= UPLOAD_URL ?>profiles/default_profile.png'">
                            <div class="comment-body">
                                <div class="comment-text-wrapper">
                                    <div class="comment-header">
                                        <span class="comment-author"><?= $r_uname ?></span>
                                    </div>
                                    <div class="comment-text">
                                        <p><?= $r_content ?></p>
                                    </div>
                                </div>
                                <div class="comment-actions">
                                    <span class="comment-time"><?= format_datetime_ago($reply['created_at']) ?></span>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <ul class="comment-list comment-replies" id="vaReplies-<?= $pc_id ?>" style="display:none;"></ul>
            <?php endif; ?>

        </div>
    </li>
</ul>
