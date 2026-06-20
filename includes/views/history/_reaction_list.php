<?php
use Massango\Services\HistoryService;

/** @var array $reactions */
?>
<!-- ── Lista de reações ── -->
<ol class="reaction-list">
    <?php foreach ($reactions as $r):
        $r_type    = $r['reaction_type'] ?? 'like';
        $source    = $r['source'];
        $label     = HistoryService::sourceLabel($source, $r['content_type'] ?? '');
        $url       = HistoryService::sourceUrl($r);
        $preview   = trim($r['content_preview'] ?? '');
        $thumb     = trim($r['media_thumb'] ?? '');
        $name      = htmlspecialchars($r['owner_username'] ?? 'Utilizador');
        $avatar    = HistoryService::avatarSrc($r['owner_avatar'] ?? '');
        $thumb_url = $thumb !== '' ? BASE_URL . 'media-proxy.php?file=' . ltrim($thumb, '/') : '';
    ?>
        <li>
            <a href="<?= htmlspecialchars($url) ?>" class="reaction-card">

                <!-- Ícone reação -->
                <div class="reaction-badge reaction-badge--<?= htmlspecialchars($r_type) ?>">
                    <?= HistoryService::reactionIcon($r_type) ?>
                </div>

                <!-- Corpo -->
                <div class="reaction-body">
                    <div class="reaction-meta">
                        <img src="<?= htmlspecialchars($avatar) ?>"
                            alt="<?= $name ?>"
                            class="reaction-meta__avatar"
                            loading="lazy"
                            onerror="this.src='<?= BASE_URL ?>assets/img/default_profile.png'">
                        <span class="reaction-meta__name"><?= $name ?></span>
                        <span class="reaction-meta__badge"><?= htmlspecialchars($label) ?></span>
                    </div>

                    <?php if ($preview !== ''): ?>
                        <p class="reaction-preview"><?= htmlspecialchars($preview) ?></p>
                    <?php endif; ?>

                    <time class="reaction-time" datetime="<?= htmlspecialchars($r['created_at']) ?>">
                        <?= HistoryService::formatDate($r['created_at']) ?>
                    </time>
                </div>

                <!-- Miniatura -->
                <?php if ($thumb_url !== ''): ?>
                    <img src="<?= htmlspecialchars($thumb_url) ?>"
                        alt="Miniatura"
                        class="reaction-thumb"
                        loading="lazy"
                        onerror="this.style.display='none'">
                <?php else: ?>
                    <div class="reaction-thumb--empty" aria-hidden="true">
                        <i class="fa-regular fa-image"></i>
                    </div>
                <?php endif; ?>

            </a>
        </li>
    <?php endforeach; ?>
</ol>
