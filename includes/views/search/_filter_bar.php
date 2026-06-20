<?php
/**
 * @var string $query
 * @var string $type
 * @var string $price_filter
 */
?>
<!-- ── Filtros ── -->
<form action="" method="GET" class="filter-bar">
    <input type="hidden" name="q" value="<?= htmlspecialchars($query) ?>">

    <div class="filter-group">
        <label>Tipo de Conteúdo</label>
        <select name="type" class="filter-select" onchange="this.form.submit()">
            <option value="all"     <?= $type === 'all'     ? 'selected' : '' ?>>Todos</option>
            <option value="profile" <?= $type === 'profile' ? 'selected' : '' ?>>Perfis (Usuários)</option>
            <option value="album"   <?= $type === 'album'   ? 'selected' : '' ?>>Álbuns</option>
            <option value="video"   <?= $type === 'video'   ? 'selected' : '' ?>>Vídeos</option>
            <option value="photo"  <?= $type === 'photo'   ? 'selected' : '' ?>>Fotos</option>
        </select>
    </div>

    <div class="filter-group">
        <label>Preço</label>
        <select name="price" class="filter-select" onchange="this.form.submit()"
            <?= $type === 'profile' ? 'disabled' : '' ?>>
            <option value="all"  <?= $price_filter === 'all'  ? 'selected' : '' ?>>Todos</option>
            <option value="free" <?= $price_filter === 'free' ? 'selected' : '' ?>>Grátis</option>
            <option value="paid" <?= $price_filter === 'paid' ? 'selected' : '' ?>>Prémio (Pago)</option>
        </select>
    </div>

    <div class="filter-group" style="margin-left:auto;">
        <button type="submit" class="btn btn-primary">Filtrar</button>
    </div>
</form>
