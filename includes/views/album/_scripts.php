<?php

/**
 * @var int    $album_id
 * @var int    $feed_item_id
 * @var int    $current_user_id
 * @var bool   $should_blur
 * @var float  $album_explicit_pct
 * @var string $album_risk_level
 * @var array  $photos
 * @var array  $photo_likes_map
 * @var array  $photo_comments_map
 * @var array  $photo_saves_map
 * @var array  $photo_saves_count
 * @var bool   $has_feed
 * @var mixed  $user_vote
 * @var array  $like_info
 * @var array  $author
 * @var array  $feed_item
 * @var array  $content_data
 * @var string $me_pic
 * @var bool   $is_owner
 * @var array  $comment_tree
 */

// Construir array VA_PHOTOS para JS
$va_photos_js = array_map(
    function ($p) use ($photo_likes_map, $photo_comments_map, $photo_saves_map, $photo_saves_count) {
        $lk = $photo_likes_map[$p['id']] ?? ['likes_count' => 0, 'user_liked' => false];
        return [
            'id'             => $p['id'],
            'src'            => get_protected_media_url($p['photo_path']),
            'thumb'          => get_protected_media_url('albums/thumbnails/' . basename($p['photo_path'])),
            'caption'        => $p['caption'] ?? '',
            'show_blur'      => !empty($p['show_blur']),
            'explicit_pct'   => (int)round($p['ai_explicit_pct'] ?? 0),
            'risk_level'     => $p['ai_risk_level'] ?? 'low',
            'likes_count'    => $lk['likes_count'],
            'user_liked'     => $lk['user_liked'],
            'comments_count' => $photo_comments_map[$p['id']] ?? 0,
            'user_saved'     => !empty($photo_saves_map[$p['id']]),
            'saves_count'    => $photo_saves_count[$p['id']] ?? 0,
        ];
    },
    $photos
);

$page_title = htmlspecialchars($content_data['album_name'] ?? 'Álbum')
    . ' · Álbum de @'
    . htmlspecialchars($author['username']);
?>
<script>
    const BASE_URL = <?= json_encode(BASE_URL) ?>;
    const UPLOAD_URL = <?= json_encode(UPLOAD_URL) ?>;
    const CURRENT_USER_ID = <?= is_logged_in() ? (int)get_current_user_id() : 'null' ?>;
    const FEED_ITEM_ID = <?= (int)($feed_item_id ?? 0) ?>;
    const ALBUM_ID = <?= (int)$album_id ?>;
    const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

    // Blur / conteúdo explícito
    const VA_SHOW_BLUR = <?= $should_blur ? 'true' : 'false' ?>;
    const VA_EXPLICIT_PCT = <?= round($album_explicit_pct) ?>;
    const VA_RISK_LEVEL = <?= json_encode($album_risk_level) ?>;

    // Estado de reveal (var → acessível pelo ficheiro JS externo)
    var vaLbBlurRevealed = false;
    var vaRevealedThumbIdx = null;

    // ── Sistema de dois cliques (blur) ────────────────────────────────────
    function vaThumbClick(idx, thumbEl) {
        const hasBlur = VA_PHOTOS[idx] && VA_PHOTOS[idx].show_blur;
        if (hasBlur && vaRevealedThumbIdx !== idx) {
            vaRestoreAllThumbs();
            vaRevealThumb(idx, thumbEl);
        } else {
            vaOpenLightbox(idx);
        }
    }

    function vaRevealThumb(idx, thumbEl) {
        vaRevealedThumbIdx = idx;
        thumbEl.removeAttribute('data-blur');
        const img = thumbEl.querySelector('img');
        if (img) img.classList.remove('va-explicit-blur');
        const bo = thumbEl.querySelector('.va-thumb-blur-overlay');
        if (bo) bo.remove();
        const ov = document.createElement('div');
        ov.className = 'va-thumb-overlay va-thumb-overlay--revealed';
        ov.innerHTML = '<i class="fa-solid fa-expand"></i>';
        thumbEl.appendChild(ov);
    }

    function vaRestoreAllThumbs() {
        VA_PHOTOS.forEach(function(photo, i) {
            if (!photo.show_blur) return;
            const thumb = document.querySelector('.va-thumb[data-index="' + i + '"]');
            if (!thumb) return;
            const tempOv = thumb.querySelector('.va-thumb-overlay--revealed');
            if (tempOv) tempOv.remove();
            thumb.setAttribute('data-blur', '1');
            const img = thumb.querySelector('img');
            if (img) img.classList.add('va-explicit-blur');
            if (!thumb.querySelector('.va-thumb-blur-overlay')) {
                const bo = document.createElement('div');
                bo.className = 'va-thumb-blur-overlay';
                bo.innerHTML = '<i class="fa-solid fa-eye-slash"></i><span>Clique para ver</span>';
                thumb.appendChild(bo);
            }
        });
        vaRevealedThumbIdx = null;
    }

    // Título do documento
    document.title = <?= json_encode($page_title) ?>;

    // Dados das fotos para o lightbox JS
    const VA_PHOTOS = <?= json_encode($va_photos_js) ?>;

    // Constantes do lightbox
    const VA_LB_HAS_FEED = <?= $has_feed ? 'true' : 'false' ?>;
    const VA_LB_USER_VOTE = <?= json_encode($user_vote) ?>;
    const VA_LB_LIKES = <?= (int)$like_info['likes'] ?>;
    const VA_LB_AUTHOR_AVATAR = <?= json_encode(UPLOAD_URL . ($author['profile_picture'] ?? 'profiles/default_profile.png')) ?>;
    const VA_LB_AUTHOR_NAME = <?= json_encode($author['username']) ?>;
    const VA_LB_AUTHOR_URL = <?= json_encode(BASE_URL . 'profile.php?id=' . (int)$author['id']) ?>;
    const VA_LB_DATE = <?= json_encode(format_datetime_ago($feed_item['created_at'] ?? date('Y-m-d H:i:s'))) ?>;
    const VA_LB_ALBUM_TITLE = <?= json_encode($content_data['album_name'] ?? 'Álbum') ?>;
    const VA_LB_ME_PIC = <?= json_encode(UPLOAD_URL . $me_pic) ?>;
    const VA_ME_USERNAME = "<?= htmlspecialchars($_SESSION['username'] ?? 'Tu') ?>";
    const VA_LB_IS_OWNER = <?= ($is_owner || isset($_SESSION['admin_id'])) ? 'true' : 'false' ?>;
    const VA_LB_EDIT_URL = <?= json_encode(BASE_URL . 'edit_album.php?id=' . (int)$album_id) ?>;
    const VA_LB_COMMENTS = <?= json_encode($has_feed ? ($comment_tree ? 'has_comments' : 'empty') : 'unavailable') ?>;
</script>

<script src="<?= BASE_URL ?>assets/js/pages/view_album.js"></script>

<!-- Auto-open foto por query-string ou hash (#photo-N) -->
<script>
    (function() {
        function getTargetPhotoId() {
            const params = new URLSearchParams(window.location.search);
            const qPhotoId = parseInt(params.get('photo_id'), 10);
            if (!Number.isNaN(qPhotoId) && qPhotoId > 0) return qPhotoId;
            const m = window.location.hash.match(/^#photo-(\d+)$/);
            return m ? parseInt(m[1], 10) : 0;
        }

        function tryOpenTargetPhoto() {
            const photoId = getTargetPhotoId();
            if (!photoId) return true;
            if (typeof VA_PHOTOS === 'undefined' || !Array.isArray(VA_PHOTOS)) return false;
            if (typeof vaOpenLightbox !== 'function') return false;
            const idx = VA_PHOTOS.findIndex(p => parseInt(p.id, 10) === photoId);
            if (idx < 0) return true;
            vaOpenLightbox(idx);
            return true;
        }

        let tries = 0;

        function bootOpenPhoto() {
            if (tryOpenTargetPhoto()) return;
            if (++tries < 20) setTimeout(bootOpenPhoto, 250);
        }

        window.addEventListener('load', () => setTimeout(bootOpenPhoto, 150));
    })();
</script>

<script src="<?= BASE_URL ?>assets/js/core/common_notifications.js"></script>