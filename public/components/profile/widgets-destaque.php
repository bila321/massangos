<?php
/* ══════════════════════════════════════════════════════════
   WIDGET: POSTS EM DESTAQUE — carrossel dos mais vistos
   Visível apenas no filtro "Feed" (all). Mínimo 2 itens.
   Schema: posts (photo/video) + videos + albums via feed_items
   ══════════════════════════════════════════════════════════ */

$is_admin = isset($_SESSION['admin_id']);

if ($can_view_content) {
    // Query unificada: junta posts (foto), vídeos e álbuns pelos seus contadores de visitas
    $fw_stmt = $pdo->prepare("
        SELECT
            fi.id          AS feed_item_id,
            fi.item_type   AS type,
            fi.item_id     AS item_id,

            /* Posts (foto) */
            CASE WHEN fi.item_type = 'post'
                THEN p.content    ELSE NULL END AS post_title,
            CASE WHEN fi.item_type = 'post'
                THEN COALESCE(p.thumbnail_path, p.image_path) ELSE NULL END AS post_thumb,
            CASE WHEN fi.item_type = 'post'
                THEN p.is_for_sale ELSE NULL END AS post_is_premium,
            CASE WHEN fi.item_type = 'post'
                THEN p.price       ELSE NULL END AS post_price,

            /* Vídeos */
            CASE WHEN fi.item_type = 'video'
                THEN v.caption     ELSE NULL END AS video_title,
            CASE WHEN fi.item_type = 'video'
                THEN v.thumbnail_path ELSE NULL END AS video_thumb,
            CASE WHEN fi.item_type = 'video'
                THEN v.views_count ELSE NULL END AS video_views,
            CASE WHEN fi.item_type = 'video'
                THEN v.is_for_sale ELSE NULL END AS video_is_premium,
            CASE WHEN fi.item_type = 'video'
                THEN v.price       ELSE NULL END AS video_price,

            /* Álbuns */
            CASE WHEN fi.item_type = 'album'
                THEN a.name        ELSE NULL END AS album_title,
            CASE WHEN fi.item_type = 'album'
                THEN COALESCE(a.thumbnail_path, a.cover_photo_url) ELSE NULL END AS album_thumb,
            CASE WHEN fi.item_type = 'album'
                THEN a.views_count ELSE NULL END AS album_views,
            CASE WHEN fi.item_type = 'album'
                THEN a.is_for_sale ELSE NULL END AS album_is_premium,
            CASE WHEN fi.item_type = 'album'
                THEN a.price       ELSE NULL END AS album_price,

            /* Likes do feed_item */
            (SELECT COUNT(*) FROM feed_item_likes fil
             WHERE fil.feed_item_id = fi.id AND fil.type = 'like') AS like_count,

            /* Score unificado para ordenação */
            CASE
                WHEN fi.item_type = 'video' THEN COALESCE(v.views_count, 0)
                WHEN fi.item_type = 'album' THEN COALESCE(a.views_count, 0)
                ELSE 0
            END AS view_count,

            /* Risk level da análise AI */
            ma.risk_level AS ai_risk_level

        FROM feed_items fi
        LEFT JOIN posts  p ON fi.item_type = 'post'  AND fi.item_id = p.id  AND p.is_approved = 1 AND p.show_in_feed = 1 AND p.is_repost = 0
        LEFT JOIN videos v ON fi.item_type = 'video' AND fi.item_id = v.id  AND v.is_approved = 1 AND v.show_in_feed = 1
        LEFT JOIN albums a ON fi.item_type = 'album' AND fi.item_id = a.id  AND a.is_approved = 1 AND a.show_in_feed = 1
        LEFT JOIN media_analysis ma ON ma.post_id = fi.item_id AND fi.item_type IN ('post', 'video')

        WHERE fi.user_id = :uid
          AND fi.show_in_feed = 1
          AND (
              (fi.item_type = 'post'  AND p.id IS NOT NULL AND p.post_type IN ('photo') AND p.image_path IS NOT NULL)
           OR (fi.item_type = 'video' AND v.id IS NOT NULL)
           OR (fi.item_type = 'album' AND a.id IS NOT NULL)
          )

        ORDER BY view_count DESC, like_count DESC, fi.created_at DESC
        LIMIT 8
    ");
    $fw_stmt->execute([':uid' => $profile_user_id]);
    $fw_posts = $fw_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($fw_posts) >= 2):
?>
        <!-- ══ WIDGET DESTAQUE ══ -->
        <section class="fw-section" id="featuredWidget" aria-label="Posts em Destaque">

            <div class="fw-header">
                <div class="fw-title-group">
                    <span class="fw-fire-icon" aria-hidden="true">🔥</span>
                    <h2 class="fw-title">Em Destaque</h2>
                    <span class="fw-subtitle">mais vistos</span>
                </div>
                <div class="fw-controls" role="group" aria-label="Controles do carrossel">
                    <button class="fw-ctrl-btn" id="fwPrev" aria-label="Anterior">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                            <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <button class="fw-ctrl-btn" id="fwNext" aria-label="Próximo">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                            <path d="M6 3l5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="fw-track-outer" id="fwTrackOuter">
                <div class="fw-track" id="fwTrack" role="list">
                    <?php foreach ($fw_posts as $fi => $fw): ?>
                        <?php
                        // ── Normaliza campos por tipo ──────────────────────────────
                        $fw_type    = $fw['type'];
                        $fw_item_id = (int)$fw['item_id'];
                        $fw_fi_id   = (int)$fw['feed_item_id'];

                        switch ($fw_type) {
                            case 'video':
                                $fw_label     = $fw['video_title'] ?: 'Sem título';
                                $fw_raw_thumb = $fw['video_thumb'];
                                $fw_views     = (int)$fw['video_views'];
                                $fw_prem      = (bool)$fw['video_is_premium'];
                                $fw_price     = (float)$fw['video_price'];
                                $fw_url       = BASE_URL . 'post.php?id=' . $fw_fi_id;
                                break;
                            case 'album':
                                $fw_label     = $fw['album_title'] ?: 'Sem título';
                                $fw_raw_thumb = $fw['album_thumb'];
                                $fw_views     = (int)$fw['album_views'];
                                $fw_prem      = (bool)$fw['album_is_premium'];
                                $fw_price     = (float)$fw['album_price'];
                                $fw_url       = BASE_URL . 'view_album.php?id=' . $fw_item_id;
                                break;
                            default: // post/foto
                                $fw_label     = $fw['post_title'] ?: 'Sem título';
                                $fw_raw_thumb = $fw['post_thumb'];
                                $fw_views     = 0;
                                $fw_prem      = (bool)$fw['post_is_premium'];
                                $fw_price     = (float)$fw['post_price'];
                                $fw_url       = BASE_URL . 'post.php?id=' . $fw_fi_id;
                        }

                        $fw_likes      = (int)$fw['like_count'];
                        $fw_thumb      = $fw_raw_thumb
                            ? UPLOAD_URL . htmlspecialchars($fw_raw_thumb, ENT_QUOTES, 'UTF-8')
                            : UPLOAD_URL . 'profiles/default_profile.png';
                        $fw_label_safe = htmlspecialchars($fw_label, ENT_QUOTES, 'UTF-8');

                        // ── Blur / acesso ──────────────────────────────────────────
                        $fw_ai_risk     = $fw['ai_risk_level'] ?? null;
                        $fw_should_blur = !$is_admin && in_array($fw_ai_risk, ['medium', 'high'], true);
                        $fw_has_access  = true;
                        if ($fw_prem && !$is_admin) {
                            // $paymentService já instanciado pelo profile.php que inclui este widget
                            $fw_has_access = $paymentService->hasAccess(
                                $current_user_id ?? 0,
                                $fw_type,
                                $fw_item_id
                            );
                        }
                        ?>

                        <article class="fw-card" role="listitem" data-index="<?= $fi ?>">
                            <a href="<?= $fw_url ?>" class="fw-card-link"
                                aria-label="<?= $fw_label_safe ?><?= $fw_views > 0 ? ', ' . number_format($fw_views) . ' visualizações' : '' ?>">

                                <div class="fw-thumb-wrap">
                                    <div class="fw-thumb-img <?= ($fw_should_blur || !$fw_has_access) ? 'fw-thumb-blurred' : '' ?>"
                                        style="background-image:url('<?= $fw_thumb ?>')"
                                        role="img" aria-label="Miniatura de <?= $fw_label_safe ?>"></div>

                                    <?php if ($fw_should_blur): ?>
                                        <!-- Overlay conteúdo sensível -->
                                        <div class="fw-sensitive-overlay" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20" style="opacity:.9">
                                                <path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.82l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.74-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z" />
                                            </svg>
                                            <span>Conteúdo sensível</span>
                                        </div>
                                    <?php elseif (!$fw_has_access): ?>
                                        <!-- Overlay conteúdo pago sem acesso -->
                                        <div class="fw-locked-overlay" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20" style="opacity:.9">
                                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z" />
                                            </svg>
                                            <span class="fw-locked-price"><?= $fw_price > 0 ? number_format($fw_price, 0) . ' MT' : 'Premium' ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($fw_type === 'video'): ?>
                                        <div class="fw-play-icon" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
                                                <path d="M8 5v14l11-7z" />
                                            </svg>
                                        </div>
                                    <?php elseif ($fw_type === 'album'): ?>
                                        <div class="fw-album-badge" aria-hidden="true">
                                            <svg viewBox="0 0 24 24" fill="currentColor" width="13" height="13">
                                                <path d="M22 16V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2zM2 6v14a2 2 0 0 0 2 2h14v-2H4V6H2z" />
                                            </svg>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Hover overlay com stats -->
                                    <div class="fw-thumb-overlay" aria-hidden="true">
                                        <div class="fw-overlay-stats">
                                            <?php if ($fw_views > 0): ?>
                                                <span>
                                                    <svg viewBox="0 0 24 24" fill="currentColor" width="11" height="11">
                                                        <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5z" />
                                                    </svg>
                                                    <?= number_format($fw_views) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($fw_likes > 0): ?>
                                                <span>
                                                    <svg viewBox="0 0 24 24" fill="currentColor" width="11" height="11">
                                                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
                                                    </svg>
                                                    <?= number_format($fw_likes) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($fw_prem): ?>
                                        <span class="fw-premium-badge">
                                            <svg viewBox="0 0 24 24" fill="currentColor" width="9" height="9">
                                                <path d="M11.99 2L2 7l4 14h12L22 7z" />
                                            </svg>
                                            <?= $fw_price > 0 ? 'MZN ' . number_format($fw_price, 0) : 'Premium' ?>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($fw_type === 'video'): ?>
                                        <span class="fw-type-badge" aria-hidden="true">▶ Vídeo</span>
                                    <?php elseif ($fw_type === 'album'): ?>
                                        <span class="fw-type-badge" aria-hidden="true">📁 Álbum</span>
                                    <?php endif; ?>
                                </div>

                                <div class="fw-card-meta">
                                    <p class="fw-card-title"><?= $fw_label_safe ?></p>
                                    <div class="fw-card-stats">
                                        <?php if ($fw_views > 0): ?>
                                            <span class="fw-stat-pill">
                                                <svg viewBox="0 0 24 24" fill="currentColor" width="10" height="10">
                                                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5z" />
                                                </svg>
                                                <?= number_format($fw_views) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="fw-stat-pill">
                                                <svg viewBox="0 0 24 24" fill="currentColor" width="10" height="10">
                                                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
                                                </svg>
                                                <?= number_format($fw_likes) ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="fw-rank-badge">#<?= $fi + 1 ?></span>
                                    </div>
                                </div>

                            </a>
                        </article>

                    <?php endforeach; ?>
                </div>
            </div>



        </section>

        <style>
            /* ══ Secção principal ══════════════════════════════════════════ */
            .fw-section {
                margin: 0 auto var(--space-md, 12px);
                background: var(--bg-main, #1e1e2e);
                /* sem overflow:hidden para não cortar o primeiro card */
                padding: 16px 0 16px 0;
                box-sizing: border-box;
                width: 100%;
                border-top: 1px solid var(--border, rgba(255, 255, 255, 0.08));
            }

            /* ══ Header ════════════════════════════════════════════════════ */
            .fw-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 14px;
                gap: 8px;
                padding: 0 16px;
            }

            .fw-title-group {
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .fw-fire-icon {
                font-size: 1.1rem;
                line-height: 1;
                filter: drop-shadow(0 0 6px rgba(255, 120, 0, .6));
            }

            .fw-title {
                font-size: .95rem;
                font-weight: 700;
                color: var(--text-main, #fff);
                margin: 0;
                line-height: 1;
            }

            .fw-subtitle {
                font-size: .68rem;
                color: var(--text-muted, #888);
                text-transform: uppercase;
                letter-spacing: .06em;
                font-weight: 500;
            }

            /* ══ Botões prev/next ══════════════════════════════════════════ */
            .fw-controls {
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .fw-ctrl-btn {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 28px;
                height: 28px;
                border-radius: 50%;
                border: 1.5px solid var(--border, #333);
                background: var(--bg-surface, #2a2a3e);
                color: var(--text-muted, #888);
                cursor: pointer;
                transition: background .18s, color .18s, transform .15s;
                flex-shrink: 0;
            }

            .fw-ctrl-btn:hover:not(:disabled) {
                background: var(--primary, #07c95b);
                border-color: var(--primary, #07c95b);
                color: #fff;
                transform: scale(1.08);
            }

            .fw-ctrl-btn:disabled {
                opacity: .35;
                cursor: not-allowed;
            }

            /* ══ Track / Scroll ════════════════════════════════════════════ */
            .fw-track-outer {
                overflow-x: auto;
                /* scroll-behavior gerido 100% pelo JS para evitar conflito no loop */
                scrollbar-width: none;
                -ms-overflow-style: none;
                margin: 0 auto;
                cursor: grab;
                -webkit-user-select: none;
                user-select: none;
                max-width: 600px;
                /* garante que o primeiro card nunca é cortado em nenhuma resolução */
                box-sizing: border-box;
                width: 100%;
            }

            .fw-track-outer::-webkit-scrollbar {
                display: none;
            }

            .fw-track-outer:active {
                cursor: grabbing;
            }

            .fw-track {
                display: flex;
                gap: 10px;
                /* scroll-snap gerido pelo JS (goTo) */
            }

            /* ══ Card ══════════════════════════════════════════════════════ */
            .fw-card {
                flex: 0 0 150px;
                width: 150px;
                border-radius: 12px;
                overflow: hidden;
                background: var(--bg-surface, #2a2a3e);
                transition: transform .22s, box-shadow .22s;
            }

            .fw-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 32px rgba(0, 0, 0, .45);
            }

            .fw-card-link {
                display: block;
                text-decoration: none;
                color: inherit;
            }

            /* ══ Thumbnail ═════════════════════════════════════════════════ */
            .fw-thumb-wrap {
                position: relative;
                width: 100%;
                aspect-ratio: 3/4;
                overflow: hidden;
                background: #111;
            }

            .fw-thumb-img {
                position: absolute;
                inset: 0;
                background-size: cover;
                background-position: center;
                transition: transform .38s;
            }

            .fw-card:hover .fw-thumb-img {
                transform: scale(1.06);
            }

            .fw-play-icon {
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                z-index: 2;
                filter: drop-shadow(0 2px 8px rgba(0, 0, 0, .7));
                opacity: .9;
            }

            .fw-album-badge {
                position: absolute;
                top: 7px;
                right: 7px;
                width: 22px;
                height: 22px;
                background: rgba(0, 0, 0, .55);
                backdrop-filter: blur(4px);
                border-radius: 5px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                z-index: 3;
            }

            .fw-thumb-overlay {
                position: absolute;
                inset: 0;
                background: linear-gradient(to top, rgba(0, 0, 0, .75) 0%, transparent 55%);
                opacity: 0;
                transition: opacity .22s;
                z-index: 4;
                display: flex;
                align-items: flex-end;
                padding: 10px;
            }

            .fw-card:hover .fw-thumb-overlay {
                opacity: 1;
            }

            .fw-overlay-stats {
                display: flex;
                gap: 8px;
                color: #fff;
                font-size: .7rem;
                font-weight: 600;
            }

            .fw-overlay-stats span {
                display: flex;
                align-items: center;
                gap: 3px;
            }

            .fw-premium-badge {
                position: absolute;
                top: 7px;
                left: 7px;
                display: flex;
                align-items: center;
                gap: 3px;
                padding: 3px 7px;
                background: linear-gradient(135deg, #f59e0b, #ef4444);
                color: #fff;
                font-size: .6rem;
                font-weight: 700;
                border-radius: 20px;
                z-index: 5;
                white-space: nowrap;
                box-shadow: 0 2px 8px rgba(0, 0, 0, .3);
            }

            .fw-type-badge {
                position: absolute;
                bottom: 7px;
                left: 7px;
                padding: 2px 6px;
                border-radius: 20px;
                font-size: .58rem;
                font-weight: 700;
                z-index: 5;
                background: rgba(0, 0, 0, .62);
                color: #fff;
                backdrop-filter: blur(4px);
            }

            /* ══ Meta / Rodapé do card ═════════════════════════════════════ */
            .fw-card-meta {
                padding: 7px 9px 9px;
                display: flex;
                flex-direction: column;
                gap: 5px;
            }

            .fw-card-title {
                margin: 0;
                font-size: .72rem;
                font-weight: 600;
                color: var(--text-main, #fff);
                line-height: 1.3;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                word-break: break-word;
            }

            .fw-card-stats {
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .fw-stat-pill {
                display: inline-flex;
                align-items: center;
                gap: 3px;
                font-size: .62rem;
                color: var(--text-muted, #888);
                font-weight: 500;
            }

            .fw-rank-badge {
                font-size: .6rem;
                font-weight: 800;
                color: var(--primary, #07c95b);
                background: var(--primary-soft, rgba(7, 201, 91, .12));
                padding: 2px 6px;
                border-radius: 20px;
            }



            /* ══ Visibilidade por filtro ═══════════════════════════════════ */
            .fw-section.fw-hidden {
                display: none;
            }

            /* ══ Blur — conteúdo sensível / pago sem acesso ════════════════ */
            .fw-thumb-blurred {
                filter: blur(14px);
                transform: scale(1.08);
            }

            /* Overlay base (sensível + locked partilham estrutura) */
            .fw-sensitive-overlay,
            .fw-locked-overlay {
                position: absolute;
                inset: 0;
                z-index: 6;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 5px;
                color: #fff;
                text-align: center;
                padding: 8px;
                pointer-events: none;
            }

            .fw-sensitive-overlay {
                background: rgba(0, 0, 0, 0.42);
            }

            .fw-locked-overlay {
                background: rgba(0, 0, 0, 0.58);
            }

            .fw-sensitive-overlay span,
            .fw-locked-overlay span {
                font-size: .58rem;
                font-weight: 600;
                line-height: 1.2;
                text-shadow: 0 1px 4px rgba(0, 0, 0, .7);
            }

            .fw-locked-price {
                font-size: .65rem !important;
                font-weight: 700 !important;
                color: #f6c90e !important;
            }

            /* ══ Responsive ════════════════════════════════════════════════ */
            @media (max-width: 600px) {
                .fw-card {
                    flex: 0 0 128px;
                    width: 128px;
                }

                .fw-subtitle {
                    display: none;
                }
            }

            @media (max-width: 380px) {
                .fw-card {
                    flex: 0 0 112px;
                    width: 112px;
                }
            }
        </style>

        <script>
            (function() {
                'use strict';

                /* ── Referências DOM ──────────────────────────────────────── */
                var outer = document.getElementById('fwTrackOuter');
                var track = document.getElementById('fwTrack');
                var btnP = document.getElementById('fwPrev');
                var btnN = document.getElementById('fwNext');

                if (!outer || !track || !btnP || !btnN) return;

                var cards = Array.from(track.querySelectorAll('.fw-card'));
                var total = cards.length;
                if (total < 1) return;

                var cur = 0; /* índice actual (0 = card #1) */
                var isDrag = false;
                var isAnim = false;
                var sx = 0;
                var startScroll = 0;

                /* ── Geometria ───────────────────────────────────────────── */
                function offsetOf(idx) {
                    /* posição do card relativa ao outer, levando em conta o scrollLeft actual */
                    var cardRect = cards[idx].getBoundingClientRect();
                    var outerRect = outer.getBoundingClientRect();
                    var pos = outer.scrollLeft + (cardRect.left - outerRect.left);
                    /* card #1 (idx=0) deve sempre ir a 0 — evita ficar a 1-2px */
                    return idx === 0 ? 0 : Math.max(0, pos);
                }

                function stepSize() {
                    if (total < 2) return cards[0].offsetWidth;
                    var r0 = cards[0].getBoundingClientRect();
                    var r1 = cards[1].getBoundingClientRect();
                    return r1.left - r0.left;
                }

                /* ── Scroll ──────────────────────────────────────────────── */
                function doScroll(left, smooth) {
                    outer.scrollTo({
                        left: left,
                        behavior: smooth ? 'smooth' : 'instant'
                    });
                }

                /* ── Navega para o índice idx ────────────────────────────── */
                function goTo(idx) {
                    idx = Math.max(0, Math.min(total - 1, idx));
                    cur = idx;
                    isAnim = true;
                    doScroll(offsetOf(idx), true);
                    updateButtons();
                    /* liberta o lock depois da transição (~350 ms) */
                    clearTimeout(goTo._t);
                    goTo._t = setTimeout(function() {
                        isAnim = false;
                    }, 380);
                }

                /* ── Actualiza estado dos botões ─────────────────────────── */
                function updateButtons() {
                    btnP.disabled = (cur <= 0);
                    btnN.disabled = (cur >= total - 1);
                }

                /* ── Botões prev / next ──────────────────────────────────── */
                btnP.addEventListener('click', function() {
                    if (!isAnim) goTo(cur - 1);
                });
                btnN.addEventListener('click', function() {
                    if (!isAnim) goTo(cur + 1);
                });

                /* ── Drag / Swipe ────────────────────────────────────────── */
                function onDragStart(clientX) {
                    isDrag = true;
                    sx = clientX;
                    startScroll = outer.scrollLeft;
                    clearTimeout(goTo._t);
                    isAnim = false;
                }

                function onDragMove(clientX) {
                    if (!isDrag) return;
                    outer.scrollTo({
                        left: startScroll - (clientX - sx),
                        behavior: 'instant'
                    });
                }

                function nearestCard() {
                    var outerLeft = outer.getBoundingClientRect().left;
                    var best = 0,
                        bestDist = Infinity;
                    for (var i = 0; i < total; i++) {
                        var d = Math.abs(cards[i].getBoundingClientRect().left - outerLeft);
                        if (d < bestDist) {
                            bestDist = d;
                            best = i;
                        }
                    }
                    return best;
                }

                function onDragEnd(clientX) {
                    if (!isDrag) return;
                    isDrag = false;
                    var dx = clientX - sx;
                    if (Math.abs(dx) > 40) {
                        goTo(cur + (dx < 0 ? 1 : -1));
                    } else {
                        goTo(nearestCard());
                    }
                }

                /* Mouse */
                outer.addEventListener('mousedown', function(e) {
                    onDragStart(e.clientX);
                });
                window.addEventListener('mousemove', function(e) {
                    onDragMove(e.clientX);
                });
                window.addEventListener('mouseup', function(e) {
                    onDragEnd(e.clientX);
                });

                /* Touch */
                outer.addEventListener('touchstart', function(e) {
                    onDragStart(e.touches[0].clientX);
                }, {
                    passive: true
                });
                window.addEventListener('touchmove', function(e) {
                    onDragMove(e.touches[0].clientX);
                }, {
                    passive: true
                });
                window.addEventListener('touchend', function(e) {
                    onDragEnd(e.changedTouches[0].clientX);
                });

                /* ── Integração com filtro de tabs ───────────────────────── */
                document.addEventListener('click', function(e) {
                    var btn = e.target.closest('[data-filter]');
                    if (!btn) return;
                    var section = document.getElementById('featuredWidget');
                    if (!section) return;
                    section.classList.toggle('fw-hidden', btn.dataset.filter !== 'all');
                });

                /* ── Init: card #1 visível imediatamente ─────────────────── */
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        doScroll(0, false);
                        cur = 0;
                        updateButtons();
                    });
                });

            })();
        </script>

<?php endif;
} ?>
<!-- ══ /WIDGET DESTAQUE ══ -->