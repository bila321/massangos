<?php


use Massango\Services\PricingRuleService;

function testRules()
{
    echo "Testando Regras de Preços e Estrelas...\n";

    // Teste 1: Usuário com 0 estrelas
    $rules0 = PricingRuleService::getRules(0);
    assert($rules0['can_sell_video'] === false);
    assert($rules0['can_sell_album'] === false);
    echo "Passou: Usuário com 0 estrelas não pode vender nada.\n";

    // Teste 2: Usuário com 1 estrela
    $rules1 = PricingRuleService::getRules(1);
    assert($rules1['can_sell_video'] === false);
    assert($rules1['can_sell_album'] === true);
    assert($rules1['max_album_price'] == 300.00);
    echo "Passou: Usuário com 1 estrela pode vender álbuns (max 300 MT).\n";

    // Teste 3: Usuário com 2 estrelas
    $rules2 = PricingRuleService::getRules(2);
    assert($rules2['can_sell_video'] === true);
    assert($rules2['max_video_price'] == 500.00);
    echo "Passou: Usuário com 2 estrelas pode vender vídeos (max 500 MT).\n";

    // Teste 4: Validação de Vídeo (Duração curta)
    $validation = PricingRuleService::validateForSale('video', 2, 100.00, ['duration' => 30]);
    assert($validation['is_valid'] === false);
    echo "Passou: Vídeo com menos de 1 minuto rejeitado.\n";

    // Teste 5: Validação de Álbum (Poucas fotos)
    $validation = PricingRuleService::validateForSale('album', 1, 100.00, ['photo_count' => 5]);
    assert($validation['is_valid'] === false);
    echo "Passou: Álbum com menos de 10 fotos rejeitado.\n";

    // Teste 6: Cálculo de Divisão
    $split = PricingRuleService::calculateSplit(100.00);
    assert($split['platform_commission'] == 12.00);
    assert($split['seller_amount'] == 88.00);
    echo "Passou: Cálculo de comissão (12/88) correto.\n";

    echo "Todos os testes de regras passaram!\n";
}

try {
    testRules();
} catch (Exception $e) {
    echo "Erro nos testes: " . $e->getMessage() . "\n";
}