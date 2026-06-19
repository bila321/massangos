<?php
/**
 * Partial: Opções de venda para Post/Foto
 * Requer: $rules (array) — injetado pelo PostController
 */
?>
<div class="sale-options">
    <label class="checkbox-wrapper">
        <input type="checkbox" name="is_for_sale" value="1" class="toggle-sale"
               <?= !$rules['can_sell_post'] ? 'disabled' : '' ?>>
        <span>Colocar à venda <small>(Requer 1 estrela)</small></span>
    </label>
    <div class="price-input-group" style="display:none; margin-top:10px;">
        <input type="number" name="price" class="form-control"
               placeholder="Preço (MT)" step="0.01"
               max="<?= $rules['max_post_price'] ?>">
        <small class="form-help">Máximo: <?= number_format($rules['max_post_price'], 2) ?> MT</small>
    </div>
</div>
