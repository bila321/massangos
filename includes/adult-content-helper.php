<?php

/**
 * Adult Content Helper Component
 * Renders the blur/overlay for 18+ content.
 */

if (!defined('SECURE_ACCESS')) {
    die('Direct access not allowed');
}

function render_adult_content($html, $content_data, $shared_data = [])
{
    if (($content_data['categoria'] ?? 'normal') !== '18+') {
        return $html;
    }

    // Gera ID único para este wrapper
    $wrapperId = 'adult-' . uniqid();

    // Aplicar blur no HTML
    $blurredHtml = $html;

    if (strpos($blurredHtml, 'class=') !== false) {
        $blurredHtml = preg_replace('/class=["\']([^"\']*)["\']/', 'class="$1 adult-blurred"', $blurredHtml);
    } else {
        $blurredHtml = str_replace('<img', '<img class="adult-blurred"', $blurredHtml);
        $blurredHtml = str_replace('<video', '<video class="adult-blurred"', $blurredHtml);
    }

    if (strpos($blurredHtml, 'style=') !== false) {
        $blurredHtml = preg_replace('/style=["\']([^"\']*)["\']/', 'style="$1 filter: blur(25px);"', $blurredHtml);
    } else {
        $blurredHtml = str_replace('>', ' style="filter: blur(25px);">', $blurredHtml);
    }

    $subcat = htmlspecialchars($content_data['subcategoria'] ?? 'Conteúdo Adulto');

    ob_start();
?>
    <div id="<?= $wrapperId ?>"
        class="adult-content-wrapper"
        data-adult-id="<?= $wrapperId ?>"
        style="position: relative; overflow: hidden; display: inline-block; width: 100%;">

        <?= $blurredHtml ?>

        <div class="adult-overlay"
            data-adult-overlay="true"
            style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; 
                    background: rgba(0,0,0,0.6); 
                    display: flex; flex-direction: column; 
                    align-items: center; justify-content: center; 
                    color: #fff; z-index: 9999;">

            <div style="text-align: center; pointer-events: auto;">
                <p style="margin-bottom: 15px; font-size: 0.9rem; font-weight: 500; text-transform: uppercase;">
                    <?= $subcat ?>
                </p>
                <button type="button"
                    class="unblur-btn"
                    data-unblur-target="<?= $wrapperId ?>"
                    data-action="unblur-adult"
                    style="background: rgba(255, 59, 48, 0.9); 
                               color: #fff; 
                               border: none; 
                               padding: 12px 30px; 
                               border-radius: 25px; 
                               font-weight: bold; 
                               cursor: pointer;
                               font-size: 0.9rem;">
                    Ver conteúdo 18+
                </button>
            </div>
        </div>
    </div>
<?php
    return ob_get_clean();
}
?>