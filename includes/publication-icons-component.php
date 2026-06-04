<?php

/**
 * Publication Icons Component
 * Displays the 4-icon publication system (Text, Photo, Video, Album)
 * 
 * Usage: Include this file where you want the publication icons to appear
 * <?php require_once __DIR__ . '/../includes/publication-icons-component.php'; ?>
 */

if (!defined('SECURE_ACCESS')) {
    die('Direct access not allowed');
}

// Only show if user is logged in
if (!is_logged_in()) {
    return;
}

?>


<div class="new-post-form-icons">
    <!-- Text Post Icon -->
    <a href="<?= BASE_URL ?>create-post.php?tab=text" class="publication-icon-btn" title="Criar um post de texto">
        <i class="fa-solid fa-pen-fancy"></i>
        <span class="publication-icon-label">Texto</span>
    </a>

    <!-- Photo Post Icon -->
    <a href="<?= BASE_URL ?>create-post.php?tab=photo" class="publication-icon-btn" title="Compartilhar uma foto">
        <i class="fa-solid fa-image"></i>
        <span class="publication-icon-label">Foto</span>
    </a>

    <!-- Video Post Icon -->
    <a href="<?= BASE_URL ?>create-post.php?tab=video" class="publication-icon-btn" title="Compartilhar um vídeo">
        <i class="fa-solid fa-video"></i>
        <span class="publication-icon-label">Vídeo</span>
    </a>

    <!-- Album Post Icon -->
    <a href="<?= BASE_URL ?>create-post.php?tab=album" class="publication-icon-btn" title="Criar um álbum de fotos">
        <i class="fa-solid fa-images"></i>
        <span class="publication-icon-label">Álbum</span>
    </a>
</div>