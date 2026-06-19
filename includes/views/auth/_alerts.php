<?php
/**
 * @var string $success_message
 * @var array  $errors
 */
?>
<?php if (!empty($success_message)): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success_message) ?>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <p><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
