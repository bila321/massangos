<!-- ── Modal: Editar Percentagem ── -->
<div id="editPartnerModal" class="modal-overlay">
    <div class="modal-content">
        <h3>Editar Percentagem</h3>

        <div class="form-group">
            <label>Nova Percentagem (%)</label>
            <input type="number" id="editPercentage" step="0.01" min="0.01" max="100">
        </div>

        <div class="modal-actions">
            <button class="btn-action btn-edit modal-action-flex" onclick="updatePartner()">Salvar</button>
            <button class="btn-action btn-reject modal-action-flex" onclick="closeEditPartnerModal()">Cancelar</button>
        </div>
    </div>
</div>
