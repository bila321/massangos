<?php
/**
 * Partial: Opções de venda para Álbum
 * @var array $rules       Injetado pelo PostController (max_album_price, can_sell_album)
 * @var array $user_stats  ['stars', 'balance', 'is_verified_creator']
 */
?>
<div class="sale-options">
    <label class="checkbox-wrapper">
        <input type="checkbox" name="is_for_sale" value="1" class="toggle-sale"
            <?= !$rules['can_sell_album'] ? 'disabled' : '' ?>>
        <span>Vender álbum <small>(Requer 3 estrelas)</small></span>
    </label>
    <div class="price-input-group" style="display:none; margin-top:10px;">
        <input type="number" name="price" class="form-control"
            placeholder="Preço (MT)" step="0.01"
            max="<?= $rules['max_album_price'] ?>">
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
