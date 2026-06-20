<?php /** @var array $rules */ ?>
<!-- ===================== SECÇÃO: VÍDEO (WIZARD) ===================== -->
<div id="section-video" class="post-form-section">
    <form action="<?= BASE_URL ?>actions/video.php" method="POST"
        enctype="multipart/form-data" class="ajax-post-form"
        data-type="video" id="video-wizard-form">
        <input type="hidden" name="post_type" value="video">

        <!-- Indicador de passos -->
        <div class="video-steps-indicator">
            <?php foreach (['Publicação', 'Edição', 'Conversão'] as $i => $label): ?>
                <div class="video-step-item <?= $i === 0 ? 'active' : '' ?>"
                    id="indicator-step-<?= $i + 1 ?>">
                    <div class="step-number"><?= $i + 1 ?></div>
                    <div class="step-label"><?= $label ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php require __DIR__ . '/video/_step1_upload.php'; ?>
        <?php require __DIR__ . '/video/_step2_trim.php'; ?>
        <?php require __DIR__ . '/video/_step3_compress.php'; ?>

        <!-- Navegação do wizard -->
        <div class="wizard-actions">
            <button type="button" class="btn-wizard-nav" id="btn-wizard-prev" style="display:none;">
                <i class="fa-solid fa-arrow-left"></i> Voltar
            </button>
            <button type="button" class="btn-wizard-nav btn-primary-wizard" id="btn-wizard-next">
                Avançar <i class="fa-solid fa-arrow-right"></i>
            </button>
            <button type="submit" class="btn-wizard-nav btn-primary-wizard" id="btn-wizard-submit" style="display:none;">
                <i class="fa-solid fa-circle-check"></i> Concluir e Publicar
            </button>
        </div>
    </form>
</div>
