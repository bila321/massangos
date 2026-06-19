<?php
$code = isset($_GET['code']) ? (int)$_GET['code'] : 500;
$messages = [
    403 => 'Acesso Proibido',
    404 => 'Página Não Encontrada',
    500 => 'Erro Interno do Servidor',
];
$msg = $messages[$code] ?? 'Erro Desconhecido';
http_response_code($code);
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Erro <?= $code ?> - Massangos</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            text-align: center;
            padding: 50px;
            background: #f5f5f5;
        }

        .box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            display: inline-block;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 48px;
            margin: 0;
            color: #e74c3c;
        }

        p {
            color: #555;
            font-size: 18px;
        }

        pre {
            text-align: left;
            background: #222;
            color: #0f0;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="box">
        <h1><?= $code ?></h1>
        <p><?= htmlspecialchars($msg) ?></p>
        <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'development'): ?>
            <h3>Detalhes (modo desenvolvimento):</h3>
            <pre><?php
                    $last = error_get_last();
                    if ($last) {
                        print_r($last);
                    } else {
                        echo "Nenhum erro capturado em error_get_last().\nVerifique storage/logs/php_error.log";
                    }
                    ?></pre>
        <?php endif; ?>
    </div>
</body>

</html>