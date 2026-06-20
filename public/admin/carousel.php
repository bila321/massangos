<?php

/**
 * public/admin/carousel.php
 * Gestão dos slides do carrossel — com upload de imagem.
 * Acesso restrito a superadmin.
 */

define('SECURE_ACCESS', true);
require_once __DIR__ . '/header.php';

// Ensure $pdo is defined to avoid 'Undefined variable' notices in environments
// where header.php doesn't expose the PDO instance. This will at least
// define the variable; if it's null, subsequent DB operations will trigger
// their own errors which should be addressed by proper app configuration.
if (!isset($pdo)) {
    $pdo = $GLOBALS['pdo'] ?? null;
}

if ($_SESSION['admin_role'] !== 'superadmin') {
    $_SESSION['admin_message']      = 'Acesso restrito apenas para SuperAdministradores.';
    $_SESSION['admin_message_type'] = 'danger';
    header('Location: index.php');
    exit();
}

/* ── Directório de uploads ───────────────────────────────── */
//
// Caminho físico no servidor onde as imagens serão gravadas.
// Em ambiente XAMPP (Windows) usa o separador correcto automaticamente.
// Para produção Linux basta mudar para o caminho absoluto do servidor,
// por exemplo: '/var/www/html/massangos/storage/uploads'
//
/* ── Directório de uploads ──────────────────────────────────────
 *
 * Posicao deste ficheiro: public/admin/carousel.php
 *   dirname(__DIR__, 1) = public/admin/
 *   dirname(__DIR__, 2) = public/
 *   dirname(__DIR__, 3) = raiz do projecto  (C:\xampp\htdocs\massangos)
 *
 * Destino dos ficheiros: storage/uploads/carousel/
 * URL publica:           BASE_URL/storage/uploads/carousel/
 *
 * BASE_URL vem de includes/config.php, carregado via public/admin/header.php
 * ─────────────────────────────────────────────────────── */

// Raiz absoluta do projecto
// Windows XAMPP: C:\xampp\htdocs\massangos
// Linux/Mac:     /var/www/html/massangos  (ou similar)
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__, 2)); // public/admin -> public -> raiz do projecto
}

// Caminho fisico absoluto: <raiz>/public/storage/uploads
// (tem de estar dentro de public/ para ser servido pelo Apache)
if (!defined('STORAGE_UPLOADS_PATH')) {
    define(
        'STORAGE_UPLOADS_PATH',
        PROJECT_ROOT
            . DIRECTORY_SEPARATOR . 'public'
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'uploads'
    );
}

// URL publica: http://localhost/massangos/public/storage/uploads
if (!defined('STORAGE_UPLOADS_URL')) {
    define('STORAGE_UPLOADS_URL', rtrim(BASE_URL, '/') . '/storage/uploads');
}

// Subdirectorio do carrossel
$CAROUSEL_UPLOAD_DIR = STORAGE_UPLOADS_PATH . DIRECTORY_SEPARATOR . 'carousel' . DIRECTORY_SEPARATOR;
$CAROUSEL_UPLOAD_URL = STORAGE_UPLOADS_URL  . '/carousel/';

if (!is_dir($CAROUSEL_UPLOAD_DIR)) {
    mkdir($CAROUSEL_UPLOAD_DIR, 0755, true);
    file_put_contents(
        $CAROUSEL_UPLOAD_DIR . '.htaccess',
        "Options -Indexes\n<FilesMatch \"\\.php$\">\n  Deny from all\n</FilesMatch>\n"
    );
}

/* ── Helper: processar upload ────────────────────────────── */
function processCarouselUpload(array $file, string $uploadDir): array
{
    $result = ['filename' => '', 'error' => ''];

    if ($file['error'] === UPLOAD_ERR_NO_FILE) return $result;
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'Erro no upload (código ' . $file['error'] . ').';
        return $result;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        $result['error'] = 'Imagem demasiado grande. Máximo: 5 MB.';
        return $result;
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime     = $finfo->file($file['tmp_name']);
    $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    if (!array_key_exists($mime, $allowed)) {
        $result['error'] = 'Tipo não permitido. Use JPG, PNG, WebP ou GIF.';
        return $result;
    }

    $filename = 'slide_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        $result['error'] = 'Não foi possível guardar a imagem. Verifique permissões do directório.';
        return $result;
    }

    $result['filename'] = $filename;
    return $result;
}

/* ── Helper: URL pública ─────────────────────────────────── */
function slideImgUrl(string $stored, string $baseUrl): string
{
    if (!$stored) return '';
    if (filter_var($stored, FILTER_VALIDATE_URL)) return $stored;
    return $baseUrl . rawurlencode(basename($stored));
}

/* ── Acções POST ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id            = intval($_POST['id'] ?? 0);
        $title         = trim($_POST['title']    ?? '');
        $subtitle      = trim($_POST['subtitle'] ?? '');
        $order         = intval($_POST['sort_order'] ?? 0);
        $active        = isset($_POST['is_active']) ? 1 : 0;
        $current_image = trim($_POST['current_image'] ?? '');
        $img_stored    = $current_image;

        /* Novo upload? */
        if (!empty($_FILES['slide_image']['name'])) {
            $upload = processCarouselUpload($_FILES['slide_image'], $CAROUSEL_UPLOAD_DIR);
            if ($upload['error']) {
                $_SESSION['admin_message']      = $upload['error'];
                $_SESSION['admin_message_type'] = 'danger';
                header('Location: carousel.php' . ($id ? "?edit={$id}" : ''));
                exit();
            }
            if ($upload['filename']) {
                /* Apaga imagem antiga (ficheiro local) */
                if ($current_image && !filter_var($current_image, FILTER_VALIDATE_URL)) {
                    $old = $CAROUSEL_UPLOAD_DIR . basename($current_image);
                    if (file_exists($old)) unlink($old);
                }
                $img_stored = $upload['filename'];
            }
        }

        /* Remover imagem? */
        if (($_POST['remove_image'] ?? '0') === '1') {
            if ($current_image && !filter_var($current_image, FILTER_VALIDATE_URL)) {
                $old = $CAROUSEL_UPLOAD_DIR . basename($current_image);
                if (file_exists($old)) unlink($old);
            }
            $img_stored = '';
        }

        /* URL externa (só aplica se não houve upload) */
        if (!$img_stored && !empty($_POST['image_url_ext'])) {
            $img_stored = trim($_POST['image_url_ext']);
        }

        if ($id > 0) {
            $pdo->prepare("UPDATE auth_carousel_slides SET title=?,subtitle=?,image_url=?,sort_order=?,is_active=? WHERE id=?")
                ->execute([$title, $subtitle, $img_stored, $order, $active, $id]);
            $_SESSION['admin_message'] = 'Slide actualizado com sucesso.';
        } else {
            $pdo->prepare("INSERT INTO auth_carousel_slides (title,subtitle,image_url,sort_order,is_active) VALUES (?,?,?,?,?)")
                ->execute([$title, $subtitle, $img_stored, $order, $active]);
            $_SESSION['admin_message'] = 'Slide criado com sucesso.';
        }
        $_SESSION['admin_message_type'] = 'success';
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $row = $pdo->prepare("SELECT image_url FROM auth_carousel_slides WHERE id=?");
            $row->execute([$id]);
            $img = $row->fetchColumn();
            if ($img && !filter_var($img, FILTER_VALIDATE_URL)) {
                $path = $CAROUSEL_UPLOAD_DIR . basename($img);
                if (file_exists($path)) unlink($path);
            }
            $pdo->prepare("DELETE FROM auth_carousel_slides WHERE id=?")->execute([$id]);
            $_SESSION['admin_message']      = 'Slide eliminado.';
            $_SESSION['admin_message_type'] = 'warning';
        }
    } elseif ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE auth_carousel_slides SET is_active = NOT is_active WHERE id=?")->execute([$id]);
            $_SESSION['admin_message']      = 'Estado do slide actualizado.';
            $_SESSION['admin_message_type'] = 'info';
        }
    }

    header('Location: carousel.php');
    exit();
}

/* ── Leitura ─────────────────────────────────────────────── */
$slides  = $pdo->query("SELECT * FROM auth_carousel_slides ORDER BY sort_order ASC, id ASC")->fetchAll();
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM auth_carousel_slides WHERE id=?");
    $stmt->execute([intval($_GET['edit'])]);
    $editing = $stmt->fetch();
}

$editImgUrl    = $editing ? slideImgUrl($editing['image_url'], $CAROUSEL_UPLOAD_URL) : '';
$editImgStored = $editing['image_url'] ?? '';
?>
<!DOCTYPE html><!-- partial — este ficheiro é incluído pelo header.php, que já emite o <head> -->

<style>
    /* ── Grelha de cards ─────────────────────────────────────── */
    .carousel-admin-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 18px;
        margin-top: 20px;
    }

    .carousel-slide-card {
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        overflow: hidden;
        background: var(--bg-surface, #fff);
        transition: box-shadow .2s, transform .2s;
    }

    .carousel-slide-card:hover {
        box-shadow: 0 6px 24px rgba(0, 0, 0, .10);
        transform: translateY(-2px);
    }

    /* Thumbnail */
    .csc-thumb {
        height: 140px;
        position: relative;
        overflow: hidden;
        background: linear-gradient(135deg, #07c95b, #00a844);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .csc-thumb img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .csc-thumb-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to bottom, transparent 30%, rgba(0, 0, 0, .6));
    }

    .csc-thumb-badge {
        position: absolute;
        top: 8px;
        left: 10px;
        background: rgba(0, 0, 0, .45);
        color: #fff;
        font-size: .7rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        z-index: 2;
    }

    .csc-thumb-no-img {
        color: rgba(255, 255, 255, .35);
        font-size: 2.2rem;
    }

    /* Body */
    .csc-body {
        padding: 13px 15px;
    }

    .csc-body h5 {
        margin: 0 0 4px;
        font-size: .92rem;
        color: var(--text-main, #111);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .csc-body p {
        margin: 0;
        font-size: .78rem;
        color: #6b7280;
    }

    .csc-body .badge {
        display: inline-block;
        padding: 2px 9px;
        border-radius: 20px;
        font-size: .7rem;
        font-weight: 700;
        margin-top: 7px;
    }

    .badge-on {
        background: #d1fae5;
        color: #065f46;
    }

    .badge-off {
        background: #f1f5f9;
        color: #64748b;
    }

    /* Acções */
    .csc-actions {
        display: flex;
        gap: 7px;
        padding: 9px 14px;
        border-top: 1px solid #f0f0f0;
        flex-wrap: wrap;
    }

    .btn-xs {
        padding: 5px 11px;
        font-size: .76rem;
        border-radius: 7px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        white-space: nowrap;
    }

    .btn-xs.edit {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .btn-xs.toggle {
        background: #f3e8ff;
        color: #7e22ce;
    }

    .btn-xs.del {
        background: #fee2e2;
        color: #b91c1c;
    }

    /* ── Formulário ──────────────────────────────────────────── */
    .form-slide {
        max-width: 660px;
    }

    .form-slide label {
        display: block;
        font-size: .8rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 5px;
        margin-top: 16px;
    }

    .form-slide label:first-child {
        margin-top: 0;
    }

    .form-slide input[type=text],
    .form-slide input[type=url],
    .form-slide input[type=number],
    .form-slide textarea {
        width: 100%;
        padding: 10px 13px;
        border: 1px solid #d1d5db;
        border-radius: 9px;
        font-size: .9rem;
        font-family: inherit;
        background: var(--bg-surface, #fff);
        color: var(--text-main, #111);
        transition: border-color .2s, box-shadow .2s;
        box-sizing: border-box;
    }

    .form-slide input:focus,
    .form-slide textarea:focus {
        outline: none;
        border-color: #07c95b;
        box-shadow: 0 0 0 3px rgba(7, 201, 91, .14);
    }

    /* ── Zona de upload ──────────────────────────────────────── */
    .upload-zone {
        position: relative;
        border: 2px dashed #d1d5db;
        border-radius: 12px;
        padding: 36px 24px;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s, background .2s;
        background: var(--bg-surface, #fafafa);
        margin-top: 4px;
    }

    .upload-zone input[type=file] {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
        border: none;
        padding: 0;
        margin: 0;
    }

    .upload-zone:hover,
    .upload-zone.drag-over {
        border-color: #07c95b;
        background: rgba(7, 201, 91, .05);
    }

    .upload-zone.drag-over {
        border-style: solid;
    }

    .upload-icon {
        font-size: 2.4rem;
        color: #9ca3af;
        margin-bottom: 10px;
    }

    .upload-zone.drag-over .upload-icon {
        color: #07c95b;
    }

    .upload-label {
        font-size: .9rem;
        color: #374151;
        font-weight: 500;
    }

    .upload-label strong {
        color: #07c95b;
    }

    .upload-hint {
        font-size: .75rem;
        color: #9ca3af;
        margin-top: 5px;
    }

    /* ── Preview da imagem ───────────────────────────────────── */
    .img-preview {
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        background: #111;
        margin-top: 4px;
        height: 180px;
    }

    .img-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: opacity .25s;
    }

    .img-preview-bar {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(transparent, rgba(0, 0, 0, .7));
        padding: 20px 14px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }

    .img-preview-name {
        color: #fff;
        font-size: .78rem;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .btn-remove-img {
        background: rgba(239, 68, 68, .85);
        color: #fff;
        border: none;
        border-radius: 6px;
        padding: 4px 10px;
        font-size: .75rem;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        flex-shrink: 0;
        transition: background .2s;
    }

    .btn-remove-img:hover {
        background: #dc2626;
    }

    /* Divisor "ou" */
    .or-divider {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 18px 0 0;
        color: #9ca3af;
        font-size: .8rem;
    }

    .or-divider::before,
    .or-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e5e7eb;
    }

    /* Barra de progresso (visual durante envio) */
    .upload-progress {
        height: 4px;
        border-radius: 2px;
        background: #e5e7eb;
        margin-top: 8px;
        overflow: hidden;
        display: none;
    }

    .upload-progress-bar {
        height: 100%;
        width: 0;
        background: #07c95b;
        transition: width .3s;
    }
</style>

<!-- ══════════════════════════════════════════════════════════
     LISTA DE SLIDES
     ══════════════════════════════════════════════════════════ -->
<div class="admin-card">
    <h3><i class="fas fa-images"></i> Carrossel da Página de Login</h3>
    <p>Slides exibidos no painel esquerdo (desktop) e no hero mobile da página de autenticação.
        Arraste para reordenar. Sem imagem, o slide usa um gradiente de cor automático.</p>

    <?php if (!empty($_SESSION['admin_message'])): ?>
        <?php $mt = $_SESSION['admin_message_type'] ?? 'info'; ?>
        <div style="margin-top:14px;padding:10px 15px;border-radius:9px;
                    background:<?= $mt === 'success' ? 'rgba(7,201,91,.08)' : ($mt === 'danger' ? 'rgba(239,68,68,.08)' : 'rgba(245,158,11,.08)') ?>;
                    border:1px solid <?= $mt === 'success' ? 'rgba(7,201,91,.2)' : ($mt === 'danger' ? 'rgba(239,68,68,.18)' : 'rgba(245,158,11,.2)') ?>;
                    color:<?= $mt === 'success' ? '#065f46' : ($mt === 'danger' ? '#b91c1c' : '#92400e') ?>;">
            <i class="fas fa-<?= $mt === 'success' ? 'check-circle' : ($mt === 'danger' ? 'exclamation-circle' : 'info-circle') ?>"></i>
            <?= htmlspecialchars($_SESSION['admin_message']) ?>
        </div>
        <?php unset($_SESSION['admin_message'], $_SESSION['admin_message_type']); ?>
    <?php endif; ?>

    <?php if (!empty($slides)): ?>
        <div class="carousel-admin-grid">
            <?php foreach ($slides as $s):
                $imgUrl = slideImgUrl($s['image_url'], $CAROUSEL_UPLOAD_URL);
            ?>
                <div class="carousel-slide-card">
                    <div class="csc-thumb">
                        <?php if ($imgUrl): ?>
                            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="" loading="lazy">
                            <div class="csc-thumb-overlay"></div>
                        <?php else: ?>
                            <i class="fas fa-image csc-thumb-no-img"></i>
                        <?php endif; ?>
                        <span class="csc-thumb-badge">Ordem <?= (int)$s['sort_order'] ?></span>
                    </div>
                    <div class="csc-body">
                        <h5 title="<?= htmlspecialchars(strip_tags($s['title'])) ?>"><?= strip_tags($s['title']) ?></h5>
                        <p><?= htmlspecialchars(mb_strimwidth($s['subtitle'] ?? '', 0, 72, '…')) ?></p>
                        <span class="badge <?= $s['is_active'] ? 'badge-on' : 'badge-off' ?>">
                            <?= $s['is_active'] ? '● Activo' : '○ Inactivo' ?>
                        </span>
                    </div>
                    <div class="csc-actions">
                        <a href="?edit=<?= $s['id'] ?>" class="btn-xs edit"><i class="fas fa-edit"></i> Editar</a>
                        <form method="POST" style="margin:0">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn-xs toggle"><?= $s['is_active'] ? 'Desactivar' : 'Activar' ?></button>
                        </form>
                        <form method="POST" style="margin:0"
                            onsubmit="return confirm('Eliminar este slide permanentemente?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button class="btn-xs del"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color:#6b7280;margin-top:16px">
            <i class="fas fa-info-circle"></i> Nenhum slide encontrado. Crie o primeiro abaixo.
        </p>
    <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════
     FORMULÁRIO CRIAR / EDITAR
     ══════════════════════════════════════════════════════════ -->
<div class="admin-card" style="margin-top:22px">
    <h4 style="margin-bottom:4px">
        <i class="fas fa-<?= $editing ? 'edit' : 'plus-circle' ?>"></i>
        <?= $editing ? 'Editar Slide #' . $editing['id'] : 'Novo Slide' ?>
    </h4>
    <p style="font-size:.85rem;color:#6b7280;margin-top:0">
        <?= $editing ? 'Altere os campos que pretende actualizar.' : 'Preencha os dados e faça upload da imagem ou introduza uma URL externa.' ?>
    </p>

    <form method="POST" enctype="multipart/form-data" class="form-slide" id="slideForm" style="margin-top:20px">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= $editing['id'] ?? 0 ?>">
        <input type="hidden" name="current_image" value="<?= htmlspecialchars($editImgStored) ?>" id="currentImage">
        <input type="hidden" name="remove_image" value="0" id="removeFlag">

        <!-- Título -->
        <label>Título
            <small style="font-weight:400;color:#9ca3af">— suporta &lt;span&gt; para realce de cor</small>
        </label>
        <input type="text" name="title" required
            placeholder="A rede social que <span>te conecta</span>"
            value="<?= htmlspecialchars($editing['title'] ?? '') ?>">

        <!-- Subtítulo -->
        <label>Subtítulo</label>
        <textarea name="subtitle" rows="2"
            placeholder="Descrição curta exibida abaixo do título."><?= htmlspecialchars($editing['subtitle'] ?? '') ?></textarea>

        <!-- ── Imagem ────────────────────────────────────────── -->
        <label>Imagem de Fundo
            <small style="font-weight:400;color:#9ca3af">— opcional; sem imagem usa gradiente automático</small>
        </label>

        <!-- Preview (visível quando já existe imagem) -->
        <div id="previewWrap" style="<?= $editImgUrl ? '' : 'display:none' ?>">
            <div class="img-preview">
                <img id="previewImg" src="<?= htmlspecialchars($editImgUrl) ?>" alt="Preview da imagem">
                <div class="img-preview-bar">
                    <span class="img-preview-name" id="previewName">
                        <?= htmlspecialchars($editImgStored ? basename($editImgStored) : '') ?>
                    </span>
                    <button type="button" class="btn-remove-img" onclick="removeImage()">
                        <i class="fas fa-times"></i> Remover
                    </button>
                </div>
            </div>
            <div class="upload-progress" id="uploadProgress">
                <div class="upload-progress-bar" id="uploadProgressBar"></div>
            </div>
        </div>

        <!-- Zona de upload drag-and-drop -->
        <div id="uploadZone"
            class="upload-zone"
            style="<?= $editImgUrl ? 'display:none' : '' ?>"
            ondragover="event.preventDefault();this.classList.add('drag-over')"
            ondragleave="this.classList.remove('drag-over')"
            ondrop="handleDrop(event)">
            <input type="file" name="slide_image" id="slideImageInput"
                accept="image/jpeg,image/png,image/webp,image/gif"
                onchange="handleFileSelect(this)">
            <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
            <div class="upload-label">
                <strong>Clique para seleccionar</strong> ou arraste a imagem aqui
            </div>
            <div class="upload-hint">JPG · PNG · WebP · GIF &nbsp;·&nbsp; Máx. 5 MB &nbsp;·&nbsp; Recomendado: 1200 × 800 px</div>
        </div>

        <!-- Alternativa: URL externa -->
        <div class="or-divider">ou use uma URL externa</div>
        <label>URL de Imagem <small style="font-weight:400;color:#9ca3af">(alternativa ao upload)</small></label>
        <input type="url" name="image_url_ext" id="imageUrlInput"
            placeholder="https://cdn.example.com/imagem.jpg"
            value="<?= (filter_var($editImgStored, FILTER_VALIDATE_URL)) ? htmlspecialchars($editImgStored) : '' ?>"
            oninput="handleUrlInput(this.value)">

        <!-- Ordem e estado -->
        <label>Ordem de Exibição</label>
        <input type="number" name="sort_order" min="0" max="99"
            value="<?= (int)($editing['sort_order'] ?? (count($slides) + 1)) ?>"
            style="max-width:120px">

        <div style="display:flex;align-items:center;gap:9px;margin-top:16px;margin-bottom:20px">
            <input type="checkbox" name="is_active" id="is_active" value="1"
                <?= (!$editing || $editing['is_active']) ? 'checked' : '' ?>
                style="width:auto;margin:0;accent-color:#07c95b;width:16px;height:16px;cursor:pointer">
            <label for="is_active"
                style="margin:0;text-transform:none;font-size:.9rem;font-weight:500;cursor:pointer">
                Slide activo (exibido no carrossel)
            </label>
        </div>

        <!-- Botões -->
        <div style="display:flex;gap:10px;flex-wrap:wrap;border-top:1px solid #f0f0f0;padding-top:18px">
            <button type="submit" class="btn-admin btn-edit" style="min-width:160px">
                <i class="fas fa-save"></i>
                <?= $editing ? 'Actualizar Slide' : 'Criar Slide' ?>
            </button>
            <?php if ($editing): ?>
                <a href="carousel.php" class="btn-admin"
                    style="background:#f1f5f9;color:#374151;text-decoration:none;
                      display:inline-flex;align-items:center;gap:6px;
                      padding:8px 18px;border-radius:7px;font-weight:600">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
    /* ── Handlers de upload ──────────────────────────────────── */

    function handleFileSelect(input) {
        if (!input.files || !input.files[0]) return;
        validateAndPreview(input.files[0]);
        document.getElementById('imageUrlInput').value = '';
        document.getElementById('removeFlag').value = '0';
    }

    function handleDrop(e) {
        e.preventDefault();
        document.getElementById('uploadZone').classList.remove('drag-over');
        var file = e.dataTransfer.files[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type)) {
            showToast('Tipo não suportado. Use JPG, PNG, WebP ou GIF.', 'error');
            return;
        }
        var dt = new DataTransfer();
        dt.items.add(file);
        document.getElementById('slideImageInput').files = dt.files;
        validateAndPreview(file);
        document.getElementById('imageUrlInput').value = '';
        document.getElementById('removeFlag').value = '0';
    }

    function validateAndPreview(file) {
        var maxMB = 5;
        if (file.size > maxMB * 1024 * 1024) {
            showToast('Imagem demasiado grande. Máximo ' + maxMB + ' MB.', 'error');
            return;
        }

        var reader = new FileReader();
        reader.onloadstart = function() {
            // Mostra barra de progresso
            document.getElementById('uploadProgress').style.display = 'block';
        };
        reader.onprogress = function(e) {
            if (e.lengthComputable) {
                var pct = Math.round((e.loaded / e.total) * 100);
                document.getElementById('uploadProgressBar').style.width = pct + '%';
            }
        };
        reader.onload = function(e) {
            document.getElementById('uploadProgressBar').style.width = '100%';
            setTimeout(function() {
                document.getElementById('uploadProgress').style.display = 'none';
                document.getElementById('uploadProgressBar').style.width = '0';
            }, 600);

            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('previewName').textContent = file.name + ' (' + formatBytes(file.size) + ')';
            document.getElementById('previewWrap').style.display = '';
            document.getElementById('uploadZone').style.display = 'none';
        };
        reader.readAsDataURL(file);
    }

    function handleUrlInput(val) {
        val = val.trim();
        if (!val) {
            // Se apagou a URL e não há ficheiro, volta a mostrar a zona de upload
            if (!document.getElementById('slideImageInput').files.length) {
                document.getElementById('previewWrap').style.display = 'none';
                document.getElementById('uploadZone').style.display = '';
            }
            return;
        }
        var img = new Image();
        img.onload = function() {
            document.getElementById('previewImg').src = val;
            document.getElementById('previewName').textContent = 'URL externa';
            document.getElementById('previewWrap').style.display = '';
            document.getElementById('uploadZone').style.display = 'none';
            document.getElementById('slideImageInput').value = '';
            document.getElementById('removeFlag').value = '0';
            document.getElementById('currentImage').value = '';
        };
        img.onerror = function() {
            document.getElementById('previewWrap').style.display = 'none';
            document.getElementById('uploadZone').style.display = '';
        };
        img.src = val;
    }

    function removeImage() {
        document.getElementById('previewImg').src = '';
        document.getElementById('previewWrap').style.display = 'none';
        document.getElementById('uploadZone').style.display = '';
        document.getElementById('slideImageInput').value = '';
        document.getElementById('imageUrlInput').value = '';
        document.getElementById('removeFlag').value = '1';
    }

    /* ── Helpers ─────────────────────────────────────────────── */
    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function showToast(msg, type) {
        var el = document.createElement('div');
        el.textContent = msg;
        el.style.cssText = [
            'position:fixed', 'top:20px', 'right:20px', 'z-index:9999',
            'padding:12px 18px', 'border-radius:10px', 'font-size:.88rem', 'font-weight:600',
            'background:' + (type === 'error' ? '#fee2e2' : '#d1fae5'),
            'color:' + (type === 'error' ? '#b91c1c' : '#065f46'),
            'border:1px solid ' + (type === 'error' ? '#fca5a5' : '#6ee7b7'),
            'box-shadow:0 4px 16px rgba(0,0,0,.12)',
            'transition:opacity .4s'
        ].join(';');
        document.body.appendChild(el);
        setTimeout(function() {
            el.style.opacity = '0';
            setTimeout(function() {
                el.remove();
            }, 400);
        }, 3000);
    }
</script>