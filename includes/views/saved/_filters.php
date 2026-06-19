<?php
$filter_defs = [
    'all'   => ['icon' => 'fa-th',          'label' => 'Tudo'],
    'post'  => ['icon' => 'fa-image',        'label' => 'Posts'],
    'video' => ['icon' => 'fa-play',         'label' => 'Vídeos'],
    'album' => ['icon' => 'fa-images',       'label' => 'Álbuns'],
    'photo' => ['icon' => 'fa-image',        'label' => 'Fotos'],
    'reel'  => ['icon' => 'fa-clapperboard', 'label' => 'Reels'],
];
?>
<!-- ── Filtros de tipo ── -->
<div class="saved-filters">
    <?php foreach ($filter_defs as $key => $f): ?>
        <a href="?type=<?= $key ?>"
            class="saved-filter-btn <?= $filter === $key ? 'active' : '' ?>">
            <i class="fa-solid <?= $f['icon'] ?>"></i>
            <?= $f['label'] ?>
        </a>
    <?php endforeach; ?>
</div>
