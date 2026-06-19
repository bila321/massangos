<!-- ── Modal de Upload de Foto ── -->
<div id="addPhotoModal" class="va-upload-modal">
    <div class="va-upload-modal-box">
        <button class="va-upload-modal-close"
            onclick="document.getElementById('addPhotoModal').classList.remove('open')"
            title="Fechar">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <h2 class="va-upload-modal-title">Adicionar Foto ao Álbum</h2>
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="va-upload-field">
                <label class="va-upload-label">Foto</label>
                <input type="file" name="new_photo" accept="image/*" required
                    class="va-upload-file-input">
            </div>
            <div class="va-upload-field">
                <label class="va-upload-label">Legenda (opcional)</label>
                <input type="text" name="photo_caption" placeholder="Escreve uma legenda…"
                    class="va-upload-text-input">
            </div>
            <button type="submit" class="va-upload-submit">Adicionar Foto</button>
        </form>
    </div>
</div>
