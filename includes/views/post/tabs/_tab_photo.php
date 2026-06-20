<?php /** @var array $rules */ ?>
<!-- ===================== SECÇÃO: FOTO ===================== -->
<div id="section-photo" class="post-form-section">
    <form action="<?= BASE_URL ?>actions/post.php" method="POST"
        enctype="multipart/form-data" class="ajax-post-form" data-type="photo">
        <input type="hidden" name="post_type" value="photo">
        <textarea name="content" class="form-control mb-3"
            placeholder="Escreva uma legenda..."></textarea>

        <div class="file-upload-area" onclick="document.getElementById('input-photo').click()">
            <i class="fa-solid fa-cloud-arrow-up"></i>
            <p>Clique para selecionar uma foto</p>
            <input type="file" id="input-photo" name="image" accept="image/*"
                style="display:none" onchange="previewMedia(this, 'photo')">
        </div>

        <div id="preview-photo" class="preview-area">
            <div class="preview-title">Preview da Foto</div>
            <img src="" class="media-preview">
        </div>

        <?php require __DIR__ . '/../_sale_options_post.php'; ?>

        <div class="form-actions">
            <button type="submit" class="btn-publish">Publicar Foto</button>
        </div>
    </form>
</div>
