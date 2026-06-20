<?php
// includes/views/checkout/_payment_methods.php
// Partial reutilizado por checkout_content.view.php e checkout_stars.view.php
// Não tem variáveis próprias — pertence ao form do pai.
?>
<div class="mb-3">
    <label class="form-label">Método de Pagamento</label>
    <div class="d-flex gap-3">
        <div class="form-check">
            <input class="form-check-input" type="radio"
                   name="payment_method" id="mpesa" value="mpesa" checked>
            <label class="form-check-label" for="mpesa">M-Pesa</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio"
                   name="payment_method" id="emola" value="emola">
            <label class="form-check-label" for="emola">e-Mola</label>
        </div>
    </div>
</div>

<div class="mb-3">
    <label for="phone_number" class="form-label">
        Número de Telefone
        <small class="text-muted">(84/85 para M-Pesa · 86/87 para e-Mola)</small>
    </label>
    <input type="text" class="form-control" id="phone_number" name="phone_number"
           placeholder="Ex: 841234567" autocomplete="tel" required>
</div>
