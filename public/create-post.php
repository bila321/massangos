<?php
header('Content-Type: text/html; charset=UTF-8');

/**
 * Massango - Create Post Page
 * Modern UI style Facebook for creating posts, photos, videos and albums.
 */

define('SECURE_ACCESS', true);
define('ENVIRONMENT', 'development');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Massango\Models\User;

SecurityManager::initSecurity();

if (!is_logged_in()) {
    set_message("Você precisa estar logado para publicar.", "danger");
    redirect(BASE_URL . 'login.php');
}

$stmt_user = $pdo->prepare("SELECT stars, balance, is_verified_creator FROM users WHERE id = ?");
$stmt_user->execute([get_current_user_id()]);
$user_stats = $stmt_user->fetch(PDO::FETCH_ASSOC);

$current_user_id = get_current_user_id();
$user_data = User::getUserById($pdo, $current_user_id);

// Regras de venda baseadas nas estrelas do usuário
$rules = [
    'can_sell_post' => $user_stats['stars'] >= 1,
    'can_sell_video' => $user_stats['stars'] >= 2,
    'can_sell_album' => $user_stats['stars'] >= 3,
    'max_post_price' => 1000.00,
    'max_video_price' => 5000.00,
    'max_album_price' => 10000.00
];
?>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/text-editor.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/create-post.css">

<?php
$extra_css = ['reels.css'];
$extra_head_js = [];
$hide_feed_container = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="create-post-container">
    <div class="create-post-header">
        <h1>Criar Publicação</h1>
        <a href="<?= BASE_URL ?>index.php" class="btn-close-standalone"><i class="fa-solid fa-xmark"></i></a>
    </div>

    <div class="create-post-tabs">
        <div class="tab-item active" data-tab="text"><i class="fa-solid fa-font"></i> Texto</div>
        <div class="tab-item" data-tab="photo"><i class="fa-solid fa-image"></i> Foto</div>
        <div class="tab-item" data-tab="video"><i class="fa-solid fa-video"></i> Vídeo</div>
        <div class="tab-item" data-tab="album"><i class="fa-solid fa-images"></i> Álbum</div>
    </div>

    <div class="create-post-body">
        <div class="user-info-small">
            <img src="<?= UPLOAD_URL . htmlspecialchars($user_data['profile_picture'] ?? 'profiles/default_profile.png') ?>" alt="Perfil">
            <div class="name"><?= htmlspecialchars($user_data['username']) ?></div>
        </div>

        <!-- Progress Bar Global -->
        <div id="global-progress" class="progress-container">
            <div class="progress-bar-wrapper">
                <div class="progress-bar-fill" id="progress-fill"></div>
            </div>
            <div class="progress-text">
                <span id="progress-status">Processando...</span>
                <span id="progress-percent">0%</span>
            </div>
        </div>

        <!-- Formulário Texto -->
        <div id="section-text" class="post-form-section active">
            <form action="<?= BASE_URL ?>process_post.php" method="POST" class="ajax-post-form" data-type="text">
                <input type="hidden" name="post_type" value="text">
                <input type="hidden" name="content" class="content-hidden">

                <div class="text-editor-container">
                    <div id="editor-text" style="height: 200px;"></div>
                </div>

                <div class="sale-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="is_for_sale" value="1" class="toggle-sale" <?= !$rules['can_sell_post'] ? 'disabled' : '' ?>>
                        <span>Colocar à venda (Requer 1 estrela)</span>
                    </label>
                    <div class="price-input-group" style="display:none; margin-top:10px;">
                        <input type="number" name="price" class="form-control" placeholder="Preço (MT)" step="0.01" max="<?= $rules['max_post_price'] ?>">
                        <small class="form-help">Máximo: <?= number_format($rules['max_post_price'], 2) ?> MT</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-publish">Publicar</button>
                </div>
            </form>
        </div>

        <!-- Formulário Foto -->
        <div id="section-photo" class="post-form-section">
            <form action="<?= BASE_URL ?>process_post.php" method="POST" enctype="multipart/form-data" class="ajax-post-form" data-type="photo">
                <input type="hidden" name="post_type" value="photo">
                <textarea name="content" class="form-control mb-3" placeholder="Escreva uma legenda..."></textarea>

                <div class="file-upload-area" onclick="document.getElementById('input-photo').click()">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <p>Clique para selecionar uma foto</p>
                    <input type="file" id="input-photo" name="image" accept="image/*" style="display:none" onchange="previewMedia(this, 'photo')">
                </div>

                <div id="preview-photo" class="preview-area">
                    <div class="preview-title">Preview da Foto</div>
                    <img src="" class="media-preview">
                </div>

                <div class="sale-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="is_for_sale" value="1" class="toggle-sale" <?= !$rules['can_sell_post'] ? 'disabled' : '' ?>>
                        <span>Colocar à venda (Requer 1 estrela)</span>
                    </label>
                    <div class="price-input-group" style="display:none; margin-top:10px;">
                        <input type="number" name="price" class="form-control" placeholder="Preço (MT)" step="0.01" max="<?= $rules['max_post_price'] ?>">
                        <small class="form-help">Máximo: <?= number_format($rules['max_post_price'], 2) ?> MT</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-publish">Publicar Foto</button>
                </div>
            </form>
        </div>

        <!-- Formulário Vídeo -->
        <div id="section-video" class="post-form-section">
            <form action="<?= BASE_URL ?>process_video_post.php" method="POST" enctype="multipart/form-data" class="ajax-post-form" data-type="video">
                <input type="hidden" name="post_type" value="video">
                <textarea name="caption" class="form-control mb-3" placeholder="Escreva uma legenda para o vídeo..."></textarea>

                <div class="file-upload-area" onclick="document.getElementById('input-video').click()">
                    <i class="fa-solid fa-film"></i>
                    <p>Clique para selecionar um vídeo</p>
                    <input type="file" id="input-video" name="video" accept="video/*" style="display:none" onchange="previewMedia(this, 'video')">
                </div>

                <div id="preview-video" class="preview-area">
                    <div class="preview-title">Preview do Vídeo</div>
                    <video class="media-preview" controls></video>
                </div>

                <div class="sale-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="is_for_sale" value="1" class="toggle-sale" <?= !$rules['can_sell_video'] ? 'disabled' : '' ?>>
                        <span>Vender vídeo (Requer 2 estrelas)</span>
                    </label>
                    <div class="price-input-group" style="display:none; margin-top:10px;">
                        <input type="number" name="price" class="form-control" placeholder="Preço (MT)" step="0.01" max="<?= $rules['max_video_price'] ?>">
                        <small class="form-help">Máximo: <?= number_format($rules['max_video_price'], 2) ?> MT</small>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-publish">Publicar Vídeo</button>
                </div>
            </form>
        </div>

        <!-- Formulário Álbum -->
        <div id="section-album" class="post-form-section">
            <form action="<?= BASE_URL ?>process_album_post.php" method="POST" enctype="multipart/form-data" class="ajax-post-form" data-type="album">
                <input type="hidden" name="post_type" value="album">
                <input type="hidden" name="cover_index" id="cover_index" value="0">

                <div class="form-group mb-3">
                    <label class="form-label">Nome do Álbum <span class="text-danger">*</span></label>
                    <input type="text" name="album_name" class="form-control" placeholder="Nome do Álbum" required>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Descrição</label>
                    <textarea name="album_description" class="form-control" placeholder="Descrição do álbum..."></textarea>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Categoria <span class="text-danger">*</span></label>
                    <select name="categoria" class="form-control" onchange="handleCategoryChange(this)" required>
                        <option value="normal">Normal</option>
                        <option value="18+">18+ (Conteúdo Adulto)</option>
                    </select>
                </div>

                <div id="subcat-group-album" class="form-group mb-3" style="display:none">
                    <label class="form-label">Subcategoria <span class="text-danger">*</span></label>
                    <input type="text" name="subcategoria" id="subcat-input-album" class="form-control" placeholder="Ex: Erótico, Nudez, etc.">
                </div>

                <div class="file-upload-area" onclick="document.getElementById('input-album').click()">
                    <i class="fa-solid fa-images"></i>
                    <p>Clique para selecionar as fotos</p>
                    <input type="file" id="input-album" name="images[]" accept="image/*" multiple style="display:none" onchange="previewAlbum(this)" required>
                </div>

                <div id="preview-album" class="preview-area">
                    <div class="preview-title">Fotos do Álbum (Clique para definir a capa)</div>
                    <div class="album-preview-grid" id="album-grid"></div>
                </div>

                <div class="sale-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="is_for_sale" value="1" class="toggle-sale" <?= !$rules['can_sell_album'] ? 'disabled' : '' ?>>
                        <span>Vender álbum (Requer 3 estrelas)</span>
                    </label>
                    <div class="price-input-group" style="display:none; margin-top:10px;">
                        <input type="number" name="price" class="form-control" placeholder="Preço (MT)" step="0.01" max="<?= $rules['max_album_price'] ?>">
                        <small class="form-help">Máximo: <?= number_format($rules['max_album_price'], 2) ?> MT</small>

                        <?php if ($user_stats['stars'] >= 3): ?>
                            <div class="form-group mt-3">
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

                <div class="form-actions">
                    <button type="submit" class="btn-publish">Criar Álbum</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<script>
    function handleCategoryChange(select) {
        const subcatGroup = document.getElementById('subcat-group-album');
        const subcatInput = document.getElementById('subcat-input-album');
        if (select.value === '18+') {
            subcatGroup.style.display = 'block';
            subcatInput.setAttribute('required', 'required');
        } else {
            subcatGroup.style.display = 'none';
            subcatInput.removeAttribute('required');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const quill = new Quill('#editor-text', {
            theme: 'snow',
            placeholder: 'No que você está pensando?',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{
                        'list': 'ordered'
                    }, {
                        'list': 'bullet'
                    }],
                    ['clean']
                ]
            }
        });

        const tabs = document.querySelectorAll('.tab-item');
        const sections = document.querySelectorAll('.post-form-section');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.tab;
                tabs.forEach(t => t.classList.remove('active'));
                sections.forEach(s => s.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById('section-' + target).classList.add('active');
            });
        });

        document.querySelectorAll('.toggle-sale').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const priceGroup = this.closest('.sale-options').querySelector('.price-input-group');
                priceGroup.style.display = this.checked ? 'block' : 'none';
                if (this.checked) {
                    priceGroup.querySelector('input').focus();
                }
            });
        });

        document.querySelectorAll('.ajax-post-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const type = this.dataset.type;
                if (type === 'text') {
                    const content = quill.root.innerHTML;
                    if (quill.getText().trim() === '') {
                        alert("Por favor, escreva algo.");
                        return;
                    }
                    this.querySelector('.content-hidden').value = content;
                }

                if (type === 'album') {
                    const files = document.getElementById('input-album').files;
                    if (files.length === 0) {
                        alert("Por favor, selecione pelo menos uma foto para o álbum.");
                        return;
                    }
                }

                const formData = new FormData(this);
                const xhr = new XMLHttpRequest();
                const progressContainer = document.getElementById('global-progress');
                const progressFill = document.getElementById('progress-fill');
                const progressPercent = document.getElementById('progress-percent');
                const progressStatus = document.getElementById('progress-status');
                const submitBtn = this.querySelector('button[type="submit"]');

                xhr.open('POST', this.action, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.upload.onprogress = function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        progressContainer.style.display = 'block';
                        progressFill.style.width = percent + '%';
                        progressPercent.textContent = percent + '%';
                        progressStatus.textContent = percent === 100 ? 'Processando no servidor...' : 'Enviando arquivos...';
                    }
                };

                xhr.onload = function() {
                    if (xhr.status === 200) {
                        try {
                            const res = JSON.parse(xhr.responseText);
                            if (res.success) {
                                window.location.href = '<?= BASE_URL ?>index.php';
                            } else {
                                alert('Erro: ' + (res.message || 'Erro desconhecido'));
                                submitBtn.disabled = false;
                                progressContainer.style.display = 'none';
                            }
                        } catch (e) {
                            // Resposta não é JSON (redirect ou HTML de erro)
                            alert('Erro inesperado do servidor. Veja o console.');
                            console.error('Resposta do servidor:', xhr.responseText);
                            submitBtn.disabled = false;
                            progressContainer.style.display = 'none';
                        }
                    } else {
                        alert('Erro HTTP ' + xhr.status + '. Tente novamente.');
                        submitBtn.disabled = false;
                        progressContainer.style.display = 'none';
                    }
                };

                xhr.onerror = function() {
                    alert('Erro de conexão.');
                    submitBtn.disabled = false;
                    progressContainer.style.display = 'none';
                };

                submitBtn.disabled = true;
                xhr.send(formData);
            });
        });
    });

    function previewMedia(input, type) {

        const previewArea = document.getElementById('preview-' + type);

        const previewMedia =
            previewArea.querySelector('.media-preview');

        if (input.files && input.files[0]) {

            const file = input.files[0];

            // Create safe blob URL
            const fileUrl = URL.createObjectURL(file);

            previewMedia.src = fileUrl;

            previewArea.style.display = 'block';
        }
    }

    function previewAlbum(input) {
        if (input.files && input.files.length > 0) {
            const previewArea = document.getElementById('preview-album');
            const grid = document.getElementById('album-grid');
            const coverInput = document.getElementById('cover_index');
            grid.innerHTML = '';
            coverInput.value = 0;
            Array.from(input.files).forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const item = document.createElement('div');
                    item.className = 'album-preview-item' + (index === 0 ? ' is-cover' : '');
                    item.onclick = function() {
                        document.querySelectorAll('.album-preview-item').forEach(el => el.classList.remove('is-cover'));
                        item.classList.add('is-cover');
                        coverInput.value = index;
                    };
                    item.innerHTML = `<img src="${e.target.result}">`;
                    grid.appendChild(item);
                }
                reader.readAsDataURL(file);
            });
            previewArea.style.display = 'block';
        }
    }
</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>