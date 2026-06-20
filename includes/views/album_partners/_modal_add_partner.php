<?php /** @var float $available_percentage */ ?>
<!-- ── Modal: Adicionar Parceiro ── -->
<div id="addPartnerModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Adicionar Novo Parceiro</h3>

        <div class="form-group">
            <label>Buscar Usuário</label>
            <input type="text" id="userSearch" placeholder="Digite o username..." autocomplete="off">
            <div id="userList" class="user-list"></div>
        </div>

        <div class="form-group">
            <label>Percentagem (%)</label>
            <input type="number" id="partnerPercentage" step="0.01" min="0.01"
                max="<?= $available_percentage ?>">
        </div>

        <div class="modal-actions">
            <button class="btn-action btn-edit modal-action-flex" onclick="addPartner()">Convidar</button>
            <button class="btn-action btn-reject modal-action-flex" onclick="closeAddPartnerModal()">Cancelar</button>
        </div>
    </div>
</div>
