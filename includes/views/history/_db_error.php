<?php
/**
 * @var bool        $db_error
 * @var string|null $db_error_detail
 */
if (empty($db_error)) return;
?>
<!-- ── Erro de BD ── -->
<div class="history-error">
    <i class="fa-solid fa-circle-exclamation"></i>
    Não foi possível carregar o histórico. Tenta novamente mais tarde.
    <?php if (!empty($db_error_detail)): ?>
        <br><small style="opacity:.7;font-family:monospace">
            <?= htmlspecialchars($db_error_detail) ?>
        </small>
    <?php endif; ?>
</div>
