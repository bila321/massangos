<?php
/**
 * Partial: Opções de venda para Vídeo
 * @var array $rules  Injetado pelo PostController (max_video_price, can_sell_video)
 */
?>
<div class="sale-options">
    <label class="checkbox-wrapper">
        <input type="checkbox" name="is_for_sale" value="1" class="toggle-sale"
            <?= !$rules['can_sell_video'] ? 'disabled' : '' ?>>
        <span>Vender vídeo <small>(Requer 2 estrelas)</small></span>
    </label>
    <div class="price-input-group" style="display:none; margin-top:10px;">
        <input type="number" name="price" class="form-control"
            placeholder="Preço (MT)" step="0.01"
            max="<?= $rules['max_video_price'] ?>">
        <small class="form-help">Máximo: <?= number_format($rules['max_video_price'], 2) ?> MT</small>
    </div>
</div>
