<?php

/** @var string $slides_json */ ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
<meta name="theme-color" content="#07c95b">
<title><?= $page_title ?? 'Criar Conta — Massangos' ?></title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modules/variables.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/modules/base.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/pages/login-register.css">

<script>
    window.BASE_URL = <?= json_encode(BASE_URL) ?>;
    window.CAROUSEL_SLIDES = <?= $slides_json ?>;
    window.PASSWORD_POLICY = { minLength: <?= MIN_PASSWORD_LENGTH ?>, requireUppercase: true, requireLowercase: true, requireNumber: true, requireSymbol: false };
</script>