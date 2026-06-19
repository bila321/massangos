<!-- ── Breadcrumb de contexto ── -->
<nav class="va-breadcrumb" aria-label="Localização">
    <a href="<?= BASE_URL ?>index.php" class="va-bc-link">
        <i class="fa-solid fa-house"></i> Início
    </a>
    <i class="fa-solid fa-chevron-right va-bc-sep"></i>
    <a href="<?= BASE_URL ?>profile.php?id=<?= (int)$author['id'] ?>" class="va-bc-link">
        <img src="<?= UPLOAD_URL . htmlspecialchars($author['profile_picture'] ?? 'profiles/default_profile.png') ?>"
            class="va-bc-avatar" alt="">
        <?= htmlspecialchars($author['username']) ?>
    </a>
    <i class="fa-solid fa-chevron-right va-bc-sep"></i>
    <span class="va-bc-current">
        <i class="fa-solid fa-images"></i>
        <?= htmlspecialchars($content_data['album_name'] ?? 'Álbum') ?>
    </span>
</nav>
