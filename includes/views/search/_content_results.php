<?php
/**
 * @var array  $results
 * @var string $type
 */
if (empty($results)) return;
?>

<?php if ($type === 'all'): ?>
    <h3 class="section-title"><i class="fas fa-stream"></i> Publicações e Mídia</h3>
<?php endif; ?>

<?php foreach ($results as $item): ?>
    <?php require __DIR__ . '/_content_card.php'; ?>
<?php endforeach; ?>
