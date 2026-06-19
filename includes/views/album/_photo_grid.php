<?php /** @var array $photos @var bool $is_owner */ ?>

<!-- ── Grid de fotos ── -->
<?php if (!empty($photos)): ?>
    <div class="va-grid" id="vaGrid">
        <?php foreach ($photos as $idx => $photo):
            $this_blur = !empty($photo['show_blur']);
        ?>
            <div class="va-thumb"
                data-index="<?= $idx ?>"
                <?= $this_blur ? 'data-blur="1"' : '' ?>
                onclick="vaThumbClick(<?= $idx ?>, this)">

                <img src="<?= get_protected_media_url('albums/thumbnails/' . basename($photo['photo_path'])) ?>"
                    alt="<?= htmlspecialchars($photo['caption'] ?? 'Foto ' . ($idx + 1)) ?>"
                    class="<?= $this_blur ? 'va-explicit-blur' : '' ?>"
                    loading="lazy">

                <?php if ($this_blur): ?>
                    <div class="va-thumb-blur-overlay">
                        <i class="fa-solid fa-eye-slash"></i>
                        <span>Clique para ver</span>
                    </div>
                <?php else: ?>
                    <div class="va-thumb-overlay"><i class="fa-solid fa-expand"></i></div>
                <?php endif; ?>

                <span class="va-thumb-num"><?= $idx + 1 ?></span>

                <?php if ($is_owner): ?>
                    <button class="va-delete-btn" title="Apagar foto"
                        onclick="event.stopPropagation(); vaDeletePhoto(<?= (int)$photo['id'] ?>, this)">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="va-empty">
        <i class="fa-solid fa-images"></i>
        Este álbum ainda não tem fotos.
    </div>
<?php endif; ?>
