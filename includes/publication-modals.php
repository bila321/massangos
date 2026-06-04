<?php

/**
 * Publication Modals Component
 * Separated modals for Text, Photo, Video, and Album
 */

if (!defined('SECURE_ACCESS')) {
    die('Direct access not allowed');
}

if (!is_logged_in()) {
    return;
}

use Massango\Models\User;

$current_user_id = get_current_user_id();
$user_data = \Massango\Models\User::getUserById($pdo, $current_user_id);
$user_stars = (int)($user_data['stars'] ?? 0);
$rules = \Massango\Services\PricingRuleService::getRules($pdo, $user_stars);
?>

<!-- Quill.js para Editor de Texto -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/text-editor.css">
<script src="<?= BASE_URL ?>assets/js/components/text-editor.js"></script>

<!-- CSS para os Modais de Publicação -->
<style>
    /* Força o cursor de texto e garante que a camada do editor receba cliques */
    .ql-container.ql-snow {
        border: 1px solid var(--border) !important;
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
        background: #fff;
        /* Fundo branco para facilitar a leitura no editor */
        color: #000;
        /* Texto preto dentro do editor */
    }

    .ql-editor {
        min-height: 200px;
        cursor: text !important;
        -webkit-user-select: text;
        /* Garante que o texto seja selecionável */
        user-select: text;
    }


    /* Garante que o toolbar não suma */
    .ql-toolbar.ql-snow {
        background: #f3f4f6;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
        z-index: 10;
    }

    .pub-modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(5px);
    }

    .pub-modal.show {
        display: block;
        animation: fadeIn 0.3s ease-out;
    }

    .pub-modal-content {
        background-color: var(--bg-surface, #121212);
        margin: 5vh auto;
        padding: 0;
        border: 1px solid var(--border, #333);
        width: 90%;
        max-width: 650px;
        border-radius: 16px;
        position: relative;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        max-height: 90vh;
        overflow-y: auto;
    }

    .pub-modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border, #333);
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        background: var(--bg-surface, #121212);
        z-index: 10;
    }

    .pub-modal-header h2 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--text-main, #fff);
    }

    .pub-modal-close {
        background: none;
        border: none;
        color: var(--text-light, #888);
        font-size: 1.5rem;
        cursor: pointer;
        transition: color 0.2s;
    }

    .pub-modal-close:hover {
        color: var(--danger, #ff4444);
    }

    .pub-modal-body {
        padding: 24px;
    }

    /* No publication-modals.php */
    .text-editor-container {
        border: 1px solid var(--border);
        border-radius: 8px;
        position: relative;
        z-index: 10001;
        /* Garante que fique acima de camadas do modal */
    }


    /* Garante que o modal não bloqueie eventos de mouse */
    .pub-modal {
        pointer-events: auto;
    }

    /* Estilos copiados do upload.php e adaptados */
    .form-section {
        margin-bottom: 24px;
    }

    .form-section-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-section-title i {
        color: var(--primary);
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: var(--text-main);
        font-size: 0.9rem;
    }

    .form-label .required {
        color: var(--danger);
    }

    .form-control {
        width: 100%;
        padding: 12px 16px;
        font-size: 0.9rem;
        color: var(--text-main);
        background: var(--bg-surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        transition: all 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px var(--primary-glow);
    }

    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }

    .form-help {
        display: block;
        margin-top: 6px;
        font-size: 0.8rem;
        color: var(--text-muted);
    }

    .sale-options-box {
        background: rgba(79, 70, 229, 0.05);
        border: 1px solid var(--primary-glow);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 16px;
    }

    .checkbox-wrapper {
        display: flex;
        align-items: center;
        cursor: pointer;
    }

    .checkbox-wrapper input {
        width: 18px;
        height: 18px;
        margin-right: 10px;
        accent-color: var(--primary);
    }

    .price-options {
        display: none;
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--border);
    }

    .price-options.visible {
        display: block;
    }

    .file-upload-area {
        border: 2px dashed var(--border);
        border-radius: 12px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        background: rgba(255, 255, 255, 0.02);
    }

    .file-upload-area:hover,
    .file-upload-area.dragover {
        border-color: var(--primary);
        background: rgba(79, 70, 229, 0.05);
    }

    .file-upload-area i {
        font-size: 2rem;
        color: var(--primary);
        margin-bottom: 10px;
        display: block;
    }

    .file-name {
        color: var(--primary);
        font-weight: 600;
        margin-top: 10px;
        font-size: 0.85rem;
    }

    .btn-pub-submit {
        width: 100%;
        padding: 14px;
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .btn-pub-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px var(--primary-glow);
    }

    .album-preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
        gap: 10px;
        margin-top: 15px;
    }

    .album-preview-item {
        position: relative;
        aspect-ratio: 1;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid transparent;
        cursor: pointer;
    }

    .album-preview-item.cover-selected {
        border-color: var(--primary);
    }

    .album-preview-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .cover-badge {
        position: absolute;
        top: 4px;
        right: 4px;
        background: var(--primary);
        color: white;
        font-size: 0.6rem;
        padding: 2px 6px;
        border-radius: 4px;
        display: none;
    }

    .album-preview-item.cover-selected .cover-badge {
        display: block;
    }

    .image-preview-container {
        display: none;
        margin-top: 15px;
    }

    .image-preview-container.visible {
        display: block;
    }

    .image-preview-img {
        width: 100%;
        border-radius: 8px;
        max-height: 300px;
        object-fit: contain;
        background: #000;
    }

    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        font-size: 0.85rem;
        display: flex;
        gap: 10px;
    }

    .alert-info {
        background: rgba(59, 130, 246, 0.1);
        color: #93c5fd;
        border: 1px solid rgba(59, 130, 246, 0.2);
    }

    .alert-warning {
        background: rgba(245, 158, 11, 0.1);
        color: #fcd34d;
        border: 1px solid rgba(245, 158, 11, 0.2);
    }
</style>

<!-- Modal Texto -->
<div id="modal-pub-text" class="pub-modal">
    <div class="pub-modal-content">
        <div class="pub-modal-header">
            <h2><i class="fa-solid fa-pen-fancy"></i> Novo Post de Texto</h2>
            <button class="pub-modal-close" onclick="closePubModal('text')">&times;</button>
        </div>
        <div class="pub-modal-body">
            <form action="<?= BASE_URL ?>process_post.php" method="POST" id="form-pub-text" onsubmit="return validateTextPost()">
                <input type="hidden" name="post_type" value="text">
                <input type="hidden" name="redirect_to" value="<?= basename($_SERVER['PHP_SELF']) ?>">
                <input type="hidden" name="content" id="text-content-hidden">

                <div class="form-section">
                    <div class="form-group">
                        <label class="form-label">O que está em sua mente? <span class="required">*</span></label>
                        <div class="text-editor-container">
                            <div id="editor-pub-text" style="height: 200px;"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Categoria <span class="required">*</span></label>
                        <select name="categoria" class="form-control" onchange="handleCategoryChange(this, 'text')" required>
                            <option value="normal">Normal</option>
                            <option value="18+">18+ (Conteúdo Adulto)</option>
                        </select>
                    </div>

                    <div class="form-group subcategory-group" id="subcat-group-text" style="display:none">
                        <label class="form-label">Subcategoria <span class="required">*</span></label>
                        <input type="text" name="subcategoria" class="form-control" placeholder="Ex: Erótico, Nudez, etc.">
                    </div>
                </div>

                <?php if (($user_data['is_verified_creator'] ?? 0)): ?>
                    <div class="form-section">
                        <div class="form-section-title"><i class="fa-solid fa-tag"></i> Opções de Venda</div>
                        <div class="sale-options-box" id="pub_text_sale_container" style="display: <?= $rules['can_sell_post'] ? 'block' : 'none' ?>">
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="is_for_sale" id="text_is_for_sale" value="1" onchange="togglePubSale('text')">
                                <span>Colocar à venda</span>
                            </label>
                            <div class="price-options" id="text_price_group">
                                <div class="form-group">
                                    <label class="form-label">Preço (MT)</label>
                                    <input type="number" name="price" class="form-control" step="0.01" min="0" max="<?= $rules['max_post_price'] ?>">
                                    <span class="form-help">Máximo: <?= number_format($rules['max_post_price'], 2) ?> MT</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn-pub-submit"><i class="fa-solid fa-paper-plane"></i> Publicar Texto</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal Foto -->
<div id="modal-pub-photo" class="pub-modal">
    <div class="pub-modal-content">
        <div class="pub-modal-header">
            <h2><i class="fa-solid fa-camera"></i> Compartilhar Foto</h2>
            <button class="pub-modal-close" onclick="closePubModal('photo')">&times;</button>
        </div>
        <div class="pub-modal-body">
            <form action="<?= BASE_URL ?>process_post.php" method="POST" enctype="multipart/form-data" id="form-pub-photo" onsubmit="return validatePhotoForm()">
                <input type="hidden" name="post_type" value="photo">
                <input type="hidden" name="redirect_to" value="<?= basename($_SERVER['PHP_SELF']) ?>">

                <div class="form-section">
                    <div class="form-group">
                        <label class="form-label">Legenda</label>
                        <textarea name="content" class="form-control" placeholder="Escreva algo sobre esta foto..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Categoria <span class="required">*</span></label>
                        <select name="categoria" class="form-control" onchange="handleCategoryChange(this, 'photo')" required>
                            <option value="normal">Normal</option>
                            <option value="18+">18+ (Conteúdo Adulto)</option>
                        </select>
                    </div>

                    <div class="form-group subcategory-group" id="subcat-group-photo" style="display:none">
                        <label class="form-label">Subcategoria <span class="required">*</span></label>
                        <input type="text" name="subcategoria" class="form-control" placeholder="Ex: Erótico, Nudez, etc.">
                    </div>

                    <div class="file-upload-area" onclick="document.getElementById('pub_photo_file').click()">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <p>Clique para selecionar uma foto</p>
                        <div class="file-name" id="pub_photo_name"></div>
                    </div>
                    <input type="file" id="pub_photo_file" name="image" class="form-control" accept="image/*" style="display:none" onchange="previewPubPhoto(this)" required>

                    <div class="image-preview-container" id="pub_photo_preview_container">
                        <img id="pub_photo_preview_img" src="" alt="Preview" class="image-preview-img">
                    </div>
                </div>

                <?php if (($user_data['is_verified_creator'] ?? 0)): ?>
                    <div class="form-section">
                        <div class="form-section-title"><i class="fa-solid fa-tag"></i> Opções de Venda</div>
                        <div class="sale-options-box" id="pub_photo_sale_container" style="display: <?= $rules['can_sell_post'] ? 'block' : 'none' ?>">
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="is_for_sale" id="photo_is_for_sale" value="1" onchange="togglePubSale('photo')">
                                <span>Colocar à venda</span>
                            </label>
                            <div class="price-options" id="photo_price_group">
                                <div class="form-group">
                                    <label class="form-label">Preço (MT)</label>
                                    <input type="number" name="price" class="form-control" step="0.01" min="0" max="<?= $rules['max_post_price'] ?>">
                                    <span class="form-help">Máximo: <?= number_format($rules['max_post_price'], 2) ?> MT</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn-pub-submit"><i class="fa-solid fa-paper-plane"></i> Publicar Foto</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal Vídeo -->
<div id="modal-pub-video" class="pub-modal">
    <div class="pub-modal-content">
        <div class="pub-modal-header">
            <h2><i class="fa-solid fa-video"></i> Compartilhar Vídeo</h2>
            <button class="pub-modal-close" onclick="closePubModal('video')">&times;</button>
        </div>
        <div class="pub-modal-body">
            <form action="<?= BASE_URL ?>process_video_post.php" method="POST" enctype="multipart/form-data" id="form-pub-video">
                <input type="hidden" name="redirect_to" value="<?= basename($_SERVER['PHP_SELF']) ?>">

                <div class="form-section">
                    <div class="form-group">
                        <label class="form-label">Título do Vídeo <span class="required">*</span></label>
                        <input type="text" name="video_title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descrição</label>
                        <textarea name="video_description" class="form-control"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Categoria <span class="required">*</span></label>
                        <select name="categoria" class="form-control" onchange="handleCategoryChange(this, 'video')" required>
                            <option value="normal">Normal</option>
                            <option value="18+">18+ (Conteúdo Adulto)</option>
                        </select>
                    </div>

                    <div class="form-group subcategory-group" id="subcat-group-video" style="display:none">
                        <label class="form-label">Subcategoria <span class="required">*</span></label>
                        <input type="text" name="subcategoria" class="form-control" placeholder="Ex: Erótico, Nudez, etc.">
                    </div>

                    <div class="file-upload-area" onclick="document.getElementById('pub_video_file').click()">
                        <i class="fa-solid fa-video"></i>
                        <p>Clique para selecionar um vídeo</p>
                        <div class="file-name" id="pub_video_name"></div>
                    </div>
                    <input type="file" id="pub_video_file" name="video" class="form-control" accept="video/*" style="display:none" onchange="checkPubVideo(this)" required>
                    <div id="pub_video_info" style="margin-top:10px; font-size:0.85rem; color:var(--primary); display:none;"></div>
                </div>

                <?php if (($user_data['is_verified_creator'] ?? 0)): ?>
                    <div class="form-section">
                        <div class="form-section-title"><i class="fa-solid fa-tag"></i> Opções de Venda</div>
                        <div id="pub_video_sale_warning" class="alert alert-warning" style="display:none">
                            <i class="fas fa-exclamation-triangle"></i> Vídeos com menos de 1 minuto não podem ser vendidos.
                        </div>
                        <div class="sale-options-box" id="pub_video_sale_container" style="display: <?= $rules['can_sell_video'] ? 'block' : 'none' ?>">
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="is_for_sale" id="video_is_for_sale" value="1" onchange="togglePubSale('video')">
                                <span>Colocar à venda</span>
                            </label>
                            <div class="price-options" id="video_price_group">
                                <div class="form-group">
                                    <label class="form-label">Preço (MT)</label>
                                    <input type="number" name="price" class="form-control" step="0.01" min="0" max="<?= $rules['max_video_price'] ?>">
                                    <span class="form-help">Máximo: <?= number_format($rules['max_video_price'], 2) ?> MT</span>
                                </div>
                                <?php if ($user_stars >= 3): ?>
                                    <div class="form-group">
                                        <label class="form-label">Onde disponibilizar?</label>
                                        <div class="radio-group">
                                            <label class="radio-wrapper">
                                                <input type="radio" name="show_in_feed" value="1" checked>
                                                <span class="radio-label">No Feed (visível para todos)</span>
                                            </label>
                                            <label class="radio-wrapper">
                                                <input type="radio" name="show_in_feed" value="0">
                                                <span class="radio-label">Apenas via Link (privado)</span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn-pub-submit"><i class="fa-solid fa-paper-plane"></i> Publicar Vídeo</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal Álbum -->
<div id="modal-pub-album" class="pub-modal">
    <div class="pub-modal-content">
        <div class="pub-modal-header">
            <h2><i class="fa-solid fa-images"></i> Novo Álbum</h2>
            <button class="pub-modal-close" onclick="closePubModal('album')">&times;</button>
        </div>
        <div class="pub-modal-body">
            <form action="<?= BASE_URL ?>process_album_post.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="post_type" value="album">
                <input type="hidden" name="redirect_to" value="<?= basename($_SERVER['PHP_SELF']) ?>">

                <div class="form-section">
                    <div class="form-group">
                        <label class="form-label">Nome do Álbum <span class="required">*</span></label>
                        <input type="text" name="album_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descrição</label>
                        <textarea name="album_description" class="form-control"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Categoria <span class="required">*</span></label>
                        <select name="categoria" class="form-control" onchange="handleCategoryChange(this, 'album')" required>
                            <option value="normal">Normal</option>
                            <option value="18+">18+ (Conteúdo Adulto)</option>
                        </select>
                    </div>

                    <div class="form-group subcategory-group" id="subcat-group-album" style="display:none">
                        <label class="form-label">Subcategoria <span class="required">*</span></label>
                        <input type="text" name="subcategoria" class="form-control" placeholder="Ex: Erótico, Nudez, etc.">
                    </div>

                    <div class="file-upload-area" onclick="document.getElementById('pub_album_files').click()">
                        <i class="fa-solid fa-images"></i>
                        <p>Clique para selecionar as fotos</p>
                        <div class="file-name" id="pub_album_name"></div>
                    </div>
                    <input type="file" id="pub_album_files" name="images[]" class="form-control" accept="image/*" multiple style="display:none" onchange="previewPubAlbum(this)" required>

                    <div id="pub_album_preview_container" style="display:none">
                        <div class="form-section-title" style="margin-top:20px">Selecione a Capa</div>
                        <div class="album-preview-grid" id="pub_album_previews"></div>
                        <input type="hidden" name="cover_index" id="pub_cover_index" value="0">
                    </div>
                </div>

                <?php if (($user_data['is_verified_creator'] ?? 0)): ?>
                    <div class="form-section">
                        <div class="form-section-title"><i class="fa-solid fa-tag"></i> Opções de Venda</div>
                        <div id="pub_album_sale_warning" class="alert alert-warning" style="display:none">
                            <i class="fas fa-exclamation-triangle"></i> Álbuns com menos de 10 fotos não podem ser vendidos.
                        </div>
                        <div class="sale-options-box" id="pub_album_sale_container" style="display: <?= $rules['can_sell_album'] ? 'block' : 'none' ?>">
                            <label class="checkbox-wrapper">
                                <input type="checkbox" name="is_for_sale" id="album_is_for_sale" value="1" onchange="togglePubSale('album')">
                                <span>Colocar à venda</span>
                            </label>
                            <div class="price-options" id="album_price_group">
                                <div class="form-group">
                                    <label class="form-label">Preço (MT)</label>
                                    <input type="number" name="price" class="form-control" step="0.01" min="0" max="<?= $rules['max_album_price'] ?>">
                                    <span class="form-help">Máximo: <?= number_format($rules['max_album_price'], 2) ?> MT</span>
                                </div>

                                <?php if ($user_stars >= 3): ?>
                                    <div class="form-group">
                                        <label class="form-label">Onde disponibilizar?</label>
                                        <div class="radio-group">
                                            <label class="radio-wrapper">
                                                <input type="radio" name="show_in_feed" value="1" checked>
                                                <span class="radio-label">No Feed (visível para todos)</span>
                                            </label>
                                            <label class="radio-wrapper">
                                                <input type="radio" name="show_in_feed" value="0">
                                                <span class="radio-label">Apenas via Link (privado)</span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn-pub-submit"><i class="fa-solid fa-paper-plane"></i> Criar Álbum</button>
            </form>
        </div>
    </div>
</div>

<script>
    window.pubRules = window.pubRules || <?= json_encode($rules) ?>;

    function openPubModal(type) {
        document.getElementById('modal-pub-' + type).classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closePubModal(type) {
        document.getElementById('modal-pub-' + type).classList.remove('show');
        document.body.style.overflow = '';
    }

    function handleCategoryChange(select, type) {
        const subcatGroup = document.getElementById('subcat-group-' + type);
        const subcatInput = subcatGroup.querySelector('input');

        if (select.value === '18+') {
            subcatGroup.style.display = 'block';
            subcatInput.setAttribute('required', 'required');
        } else {
            subcatGroup.style.display = 'none';
            subcatInput.removeAttribute('required');
        }
    }

    function togglePubSale(type) {
        const checkbox = document.getElementById(type + '_is_for_sale');
        const priceGroup = document.getElementById(type + '_price_group');

        if (checkbox.checked) {
            let canSell = false;
            if (type === 'text' || type === 'photo') canSell = pubRules['can_sell_post'];
            else if (type === 'video') canSell = pubRules['can_sell_video'];
            else if (type === 'album') canSell = pubRules['can_sell_album'];

            if (!canSell) {
                alert("Você não tem estrelas suficientes para vender este tipo de conteúdo.");
                checkbox.checked = false;
                return;
            }
            priceGroup.classList.add('visible');
        } else {
            priceGroup.classList.remove('visible');
        }
    }

    function previewPubPhoto(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            document.getElementById('pub_photo_name').textContent = '✓ ' + file.name;
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('pub_photo_preview_img').src = e.target.result;
                document.getElementById('pub_photo_preview_container').classList.add('visible');
            }
            reader.readAsDataURL(file);
        }
    }

    function checkPubVideo(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            document.getElementById('pub_video_name').textContent = '✓ ' + file.name;
            const video = document.createElement('video');
            video.onloadedmetadata = function() {
                const duration = Math.floor(video.duration);
                const min = Math.floor(duration / 60);
                const sec = duration % 60;
                document.getElementById('pub_video_info').textContent = `Duração: ${min}m ${sec}s`;
                document.getElementById('pub_video_info').style.display = 'block';

                if (duration < 60) {
                    document.getElementById('pub_video_sale_warning').style.display = 'flex';
                    document.getElementById('pub_video_sale_container').style.display = 'none';
                } else {
                    document.getElementById('pub_video_sale_warning').style.display = 'none';
                    if (pubRules['can_sell_video']) document.getElementById('pub_video_sale_container').style.display = 'block';
                }
            };
            video.src = URL.createObjectURL(file);
        }
    }

    function previewPubAlbum(input) {
        if (input.files && input.files.length > 0) {
            const container = document.getElementById('pub_album_preview_container');
            const grid = document.getElementById('pub_album_previews');
            const name = document.getElementById('pub_album_name');
            const coverInput = document.getElementById('pub_cover_index');

            grid.innerHTML = '';
            name.textContent = '✓ ' + input.files.length + ' fotos selecionadas';
            container.style.display = 'block';
            coverInput.value = 0;

            Array.from(input.files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.className = 'album-preview-item' + (index === 0 ? ' cover-selected' : '');
                    item.onclick = function() {
                        document.querySelectorAll('.album-preview-item').forEach(el => el.classList.remove('cover-selected'));
                        item.classList.add('cover-selected');
                        coverInput.value = index;
                    };
                    item.innerHTML = `
                        <img src="${e.target.result}">
                        <div class="cover-badge">CAPA</div>
                    `;
                    grid.appendChild(item);
                }
                reader.readAsDataURL(file);
            });

            if (input.files.length < 10) {
                document.getElementById('pub_album_sale_warning').style.display = 'flex';
                document.getElementById('pub_album_sale_container').style.display = 'none';
            } else {
                document.getElementById('pub_album_sale_warning').style.display = 'none';
                if (pubRules['can_sell_album']) document.getElementById('pub_album_sale_container').style.display = 'block';
            }
        }
    }

    function validateTextPost() {
        const editor = document.querySelector('#editor-pub-text .ql-editor');
        const content = editor.innerHTML;
        const textOnly = editor.innerText.trim();

        if (textOnly === '') {
            alert("Por favor, escreva algo.");
            return false;
        }

        document.getElementById('text-content-hidden').value = content;
        return true;
    }

    function validatePhotoForm() {
        const file = document.getElementById('pub_photo_file').files[0];
        if (!file) {
            alert("Por favor, selecione uma foto.");
            return false;
        }
        return true;
    }
</script>