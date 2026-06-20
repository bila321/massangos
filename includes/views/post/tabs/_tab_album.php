<?php /** @var array $rules @var array $user_stats */ ?>
<!-- ===================== SECÇÃO: ÁLBUM ===================== -->
<div id="section-album" class="post-form-section">
    <form action="<?= BASE_URL ?>actions/album.php" method="POST"
        enctype="multipart/form-data" class="ajax-post-form" data-type="album">
        <input type="hidden" name="post_type" value="album">
        <input type="hidden" name="cover_index" id="cover_index" value="0">

        <div class="form-group mb-3">
            <label class="form-label">Nome do Álbum <span class="text-danger">*</span></label>
            <input type="text" name="album_name" class="form-control"
                placeholder="Nome do Álbum" required>
        </div>

        <div class="form-group mb-3">
            <label class="form-label">Descrição</label>
            <textarea name="album_description" class="form-control"
                placeholder="Descrição do álbum..."></textarea>
        </div>

        <div class="form-group mb-3">
            <label class="form-label">Categoria <span class="text-danger">*</span></label>
            <select name="categoria" class="form-control"
                onchange="handleCategoryChange(this)" required>
                <option value="normal">Normal</option>
                <option value="18+">18+ (Conteúdo Adulto)</option>
            </select>
        </div>

        <div id="subcat-group-album" class="form-group mb-3" style="display:none">
            <label class="form-label">Subcategoria <span class="text-danger">*</span></label>
            <input type="text" name="subcategoria" id="subcat-input-album"
                class="form-control" placeholder="Ex: Erótico, Nudez, etc.">
        </div>

        <div class="file-upload-area" onclick="document.getElementById('input-album').click()">
            <i class="fa-solid fa-images"></i>
            <p>Clique para selecionar as fotos</p>
            <input type="file" id="input-album" name="images[]" accept="image/*"
                multiple style="display:none" onchange="previewAlbum(this)" required>
        </div>

        <div id="preview-album" class="preview-area">
            <div class="preview-title">Fotos do Álbum (Clique para definir a capa)</div>
            <div class="album-preview-grid" id="album-grid"></div>
        </div>

        <?php require __DIR__ . '/../_sale_options_album.php'; ?>

        <div class="form-actions">
            <button type="submit" class="btn-publish">Criar Álbum</button>
        </div>
    </form>
</div>
