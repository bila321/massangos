<?php
/* ══════════════════════════════════════════════════════════════
   WIDGET 2 — Álbuns Premium / À Venda
   Mostra: capa, preço, contagem de mídias, preview borrado,
           badge VIP, botão "Desbloquear"
   Objetivo: Monetização do creator no perfil público
   Ficheiro: public/components/profile/widget-album-premium.php
   ══════════════════════════════════════════════════════════════ */

// Segurança: garante que as variáveis essenciais existem
if (!isset($profile_data) || !isset($pdo)) return;

$w2_profile_user_id = (int)($profile_data['id'] ?? 0);
if (!$w2_profile_user_id) return;

// ── Busca álbuns premium (is_for_sale = 1, is_approved = 1) ──────────────────
$w2_stmt = $pdo->prepare("
    SELECT
        a.id,
        a.name,
        a.description,
        a.cover_photo_url,
        a.thumbnail_path,
        a.price,
        a.views_count,
        a.subcategoria,
        (SELECT COUNT(*) FROM album_photos ap WHERE ap.album_id = a.id) AS media_count
    FROM albums a
    WHERE a.user_id        = :uid
      AND a.is_for_sale    = 1
      AND a.is_approved    = 1
      AND a.show_in_feed   = 1
    ORDER BY a.created_at DESC
    LIMIT 6
");
$w2_stmt->execute([':uid' => $w2_profile_user_id]);
$w2_albums = $w2_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sem álbuns: mostra estado vazio apenas ao dono
if (empty($w2_albums) && !$is_owner) return;

// ── Verifica quais álbuns o utilizador atual já desbloqueou ─────────────────
$w2_unlocked_ids = [];
if (isset($current_user_id) && $current_user_id) {
    if (!empty($w2_albums)) {
        $w2_album_ids   = array_column($w2_albums, 'id');
        $w2_placeholders = implode(',', array_fill(0, count($w2_album_ids), '?'));
        $w2_access_stmt = $pdo->prepare("
            SELECT content_id
            FROM content_access
            WHERE user_id      = ?
              AND content_type = 'album'
              AND content_id IN ({$w2_placeholders})
        ");
        $w2_access_stmt->execute(array_merge([$current_user_id], $w2_album_ids));
        $w2_unlocked_ids = $w2_access_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// ── Helper: formata preço em KZ ─────────────────────────────────────────────
function w2_format_price(float $price): string
{
    return number_format($price, 0, ',', '.') . ' KZ';
}

// ── Helper: URL segura da capa ──────────────────────────────────────────────
function w2_cover_url(array $album): string
{
    $path = $album['thumbnail_path'] ?: $album['cover_photo_url'];
    if (!$path) return '';
    // Se já for URL absoluta devolve directo
    if (preg_match('#^https?://#i', $path)) return $path;
    return BASE_URL . 'uploads/' . ltrim($path, '/');
}
?>

<!-- ══ WIDGET 2 — ÁLBUNS PREMIUM / À VENDA ══ -->
<aside class="w2-card" aria-label="Álbuns premium à venda">

    <div class="w2-header">
        <span class="w2-title">
            <i class="fa-solid fa-crown w2-crown-icon" aria-hidden="true"></i>
            Premium
        </span>
        <?php if ($is_owner): ?>
            <a href="<?= BASE_URL ?>albums/create.php?premium=1"
                class="w2-new-btn"
                title="Criar álbum premium"
                aria-label="Criar novo álbum premium">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($w2_albums)): ?>
        <!-- Grade de álbuns -->
        <div class="w2-grid" role="list">
            <?php foreach ($w2_albums as $w2_album):
                $w2_id         = (int)$w2_album['id'];
                $w2_unlocked   = in_array($w2_id, $w2_unlocked_ids, true) || ($is_owner);
                $w2_cover      = w2_cover_url($w2_album);
                $w2_price      = (float)$w2_album['price'];
                $w2_media      = (int)$w2_album['media_count'];
                $w2_name       = htmlspecialchars($w2_album['name'],        ENT_QUOTES, 'UTF-8');
                $w2_desc       = htmlspecialchars($w2_album['description'] ?? '', ENT_QUOTES, 'UTF-8');
                $w2_album_url  = BASE_URL . 'album.php?id=' . $w2_id;
            ?>
                <article class="w2-album<?= $w2_unlocked ? ' w2-album--unlocked' : '' ?>"
                    role="listitem"
                    aria-label="<?= $w2_name ?>">

                    <!-- ── Capa ────────────────────────────── -->
                    <div class="w2-cover">
                        <?php if ($w2_cover): ?>
                            <img src="<?= htmlspecialchars($w2_cover, ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= $w2_name ?>"
                                class="w2-cover-img<?= !$w2_unlocked ? ' w2-blur' : '' ?>"
                                loading="lazy">
                        <?php else: ?>
                            <div class="w2-cover-placeholder<?= !$w2_unlocked ? ' w2-blur' : '' ?>">
                                <i class="fa-solid fa-images"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Overlay escuro só quando bloqueado -->
                        <?php if (!$w2_unlocked): ?>
                            <div class="w2-overlay" aria-hidden="true">
                                <i class="fa-solid fa-lock w2-lock-icon"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Badge VIP -->
                        <span class="w2-badge" aria-label="Conteúdo premium">
                            <i class="fa-solid fa-crown" aria-hidden="true"></i> VIP
                        </span>

                        <!-- Contagem de mídias -->
                        <span class="w2-media-count" aria-label="<?= $w2_media ?> ficheiros">
                            <i class="fa-solid fa-photo-film" aria-hidden="true"></i>
                            <?= $w2_media ?>
                        </span>
                    </div>

                    <!-- ── Info & acção ─────────────────────── -->
                    <div class="w2-info">
                        <p class="w2-name" title="<?= $w2_name ?>"><?= $w2_name ?></p>

                        <?php if ($w2_unlocked): ?>
                            <a href="<?= htmlspecialchars($w2_album_url, ENT_QUOTES, 'UTF-8') ?>"
                                class="w2-btn w2-btn--open">
                                <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                                Ver álbum
                            </a>
                        <?php else: ?>
                            <div class="w2-price-row">
                                <span class="w2-price"><?= w2_format_price($w2_price) ?></span>
                            </div>
                            <a href="<?= htmlspecialchars($w2_album_url, ENT_QUOTES, 'UTF-8') ?>"
                                class="w2-btn w2-btn--unlock"
                                aria-label="Desbloquear <?= $w2_name ?> por <?= w2_format_price($w2_price) ?>">
                                <i class="fa-solid fa-unlock-keyhole" aria-hidden="true"></i>
                                Desbloquear
                            </a>
                        <?php endif; ?>
                    </div>

                </article>
            <?php endforeach; ?>
        </div>

        <!-- Link "Ver todos" -->
        <a href="<?= BASE_URL ?>profile.php?id=<?= $w2_profile_user_id ?>&tab=premium"
            class="w2-all-link">
            Ver todos os álbuns premium
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </a>

    <?php else: ?>
        <!-- ── Estado vazio — só dono vê ───────────────────── -->
        <p class="w2-empty">
            Ainda não tens álbuns à venda. Cria o teu primeiro álbum premium e começa a monetizar o teu conteúdo.
        </p>
        <a href="<?= BASE_URL ?>albums/create.php?premium=1" class="w2-cta">
            <i class="fa-solid fa-crown" aria-hidden="true"></i>
            Criar álbum premium
        </a>
    <?php endif; ?>

</aside>

<style>
    /* ══ Widget 2 — Álbuns Premium / À Venda ════════════════════ */

    /* ── Card container ── */
    .w2-card {
        background: var(--bg-card, #1e1e2e);
        border-radius: var(--radius-lg, 14px);
        padding: 18px 16px 14px;
        margin-bottom: 12px;
        box-sizing: border-box;
        width: 100%;
    }

    /* ── Header ── */
    .w2-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }

    .w2-title {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: .92rem;
        font-weight: 700;
        color: var(--text-main, #fff);
        line-height: 1;
    }

    .w2-crown-icon {
        color: #f5c542;
        font-size: .82rem;
    }

    .w2-new-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--bg-surface, rgba(255, 255, 255, .07));
        color: var(--text-muted, #888);
        text-decoration: none;
        font-size: .72rem;
        transition: background .18s, color .18s;
        flex-shrink: 0;
    }

    .w2-new-btn:hover {
        background: var(--primary, #07c95b);
        color: #fff;
    }

    /* ── Grade de álbuns (2 colunas fixas) ── */
    .w2-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-bottom: 12px;
    }

    /* ── Cada álbum ── */
    .w2-album {
        display: flex;
        flex-direction: column;
        border-radius: var(--radius-md, 10px);
        overflow: hidden;
        background: var(--bg-surface, rgba(255, 255, 255, .04));
        transition: transform .18s, box-shadow .18s;
    }

    .w2-album:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, .35);
    }

    /* ── Área da capa ── */
    .w2-cover {
        position: relative;
        width: 100%;
        aspect-ratio: 1 / 1;
        overflow: hidden;
        background: var(--bg-surface, #2a2a3e);
    }

    .w2-cover-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform .3s;
    }

    .w2-album:hover .w2-cover-img {
        transform: scale(1.04);
    }

    /* Placeholder sem capa */
    .w2-cover-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        color: var(--text-muted, #555);
    }

    /* ── Efeito blur (preview bloqueado) ── */
    .w2-blur {
        filter: blur(8px) brightness(.7);
        transform: scale(1.05);
        /* evita bordas brancas do blur */
    }

    .w2-album:hover .w2-blur {
        transform: scale(1.09);
    }

    /* ── Overlay com cadeado ── */
    .w2-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, .30);
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }

    .w2-lock-icon {
        font-size: 1.6rem;
        color: rgba(255, 255, 255, .85);
        filter: drop-shadow(0 2px 6px rgba(0, 0, 0, .5));
    }

    /* ── Badge VIP ── */
    .w2-badge {
        position: absolute;
        top: 7px;
        left: 7px;
        background: linear-gradient(135deg, #f5c542 0%, #e09b1e 100%);
        color: #1a1000;
        font-size: .6rem;
        font-weight: 800;
        letter-spacing: .04em;
        padding: 2px 7px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        box-shadow: 0 2px 6px rgba(245, 197, 66, .45);
        pointer-events: none;
        text-transform: uppercase;
    }

    /* ── Contagem de mídias ── */
    .w2-media-count {
        position: absolute;
        bottom: 6px;
        right: 6px;
        background: rgba(0, 0, 0, .6);
        backdrop-filter: blur(4px);
        color: #fff;
        font-size: .65rem;
        font-weight: 600;
        padding: 2px 7px;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        pointer-events: none;
    }

    /* ── Info & botão ── */
    .w2-info {
        padding: 8px 8px 9px;
        display: flex;
        flex-direction: column;
        gap: 6px;
        flex: 1;
    }

    .w2-name {
        font-size: .78rem;
        font-weight: 600;
        color: var(--text-main, #e4e6ea);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.3;
    }

    /* Linha de preço */
    .w2-price-row {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .w2-price {
        font-size: .8rem;
        font-weight: 700;
        color: #f5c542;
    }

    /* ── Botões ── */
    .w2-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: .72rem;
        font-weight: 700;
        text-decoration: none;
        padding: 5px 0;
        border-radius: var(--radius-sm, 8px);
        width: 100%;
        transition: opacity .18s, background .18s;
        letter-spacing: .02em;
    }

    /* Botão "Desbloquear" — dourado */
    .w2-btn--unlock {
        background: linear-gradient(135deg, #f5c542 0%, #d4881a 100%);
        color: #1a1000;
        box-shadow: 0 2px 10px rgba(245, 197, 66, .35);
    }

    .w2-btn--unlock:hover {
        opacity: .88;
    }

    /* Botão "Ver álbum" — verde primário */
    .w2-btn--open {
        background: var(--primary-soft, rgba(7, 201, 91, .12));
        color: var(--primary, #07c95b);
        border: 1px solid rgba(7, 201, 91, .25);
    }

    .w2-btn--open:hover {
        background: var(--primary-soft, rgba(7, 201, 91, .22));
    }

    /* ── Link "ver todos" ── */
    .w2-all-link {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: .78rem;
        font-weight: 600;
        color: var(--text-muted, #888);
        text-decoration: none;
        padding: 6px 0 2px;
        transition: color .18s;
    }

    .w2-all-link:hover {
        color: var(--primary, #07c95b);
    }

    /* ── Estado vazio ── */
    .w2-empty {
        font-size: .82rem;
        color: var(--text-muted, #888);
        line-height: 1.5;
        margin: 0 0 12px;
    }

    .w2-cta {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: .82rem;
        font-weight: 600;
        color: #1a1000;
        text-decoration: none;
        padding: 7px 14px;
        border-radius: var(--radius-sm, 8px);
        background: linear-gradient(135deg, #f5c542 0%, #d4881a 100%);
        box-shadow: 0 2px 10px rgba(245, 197, 66, .35);
        transition: opacity .18s;
    }

    .w2-cta:hover {
        opacity: .88;
    }
</style>
<!-- ══ /WIDGET 2 ══ -->