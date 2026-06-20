<?php /** @var array $rules */ ?>
<!-- ===================== SECÇÃO: TEXTO ===================== -->
<div id="section-text" class="post-form-section active">
    <form action="<?= BASE_URL ?>actions/post.php" method="POST"
        class="ajax-post-form" data-type="text">
        <input type="hidden" name="post_type" value="text">
        <input type="hidden" name="content" class="content-hidden">

        <div class="text-editor-container">
            <div id="editor-text" style="height: 200px;"></div>
        </div>

        <?php require __DIR__ . '/../_sale_options_post.php'; ?>

        <div class="form-actions">
            <button type="submit" class="btn-publish">Publicar</button>
        </div>
    </form>
</div>
