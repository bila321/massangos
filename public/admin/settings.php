<?php
// public/admin/settings.php
define('SECURE_ACCESS', true);
require_once __DIR__ . '/header.php';

if ($_SESSION['admin_role'] !== 'superadmin') {
    $_SESSION['admin_message'] = "Acesso restrito apenas para SuperAdministradores.";
    $_SESSION['admin_message_type'] = "danger";
    header("Location: index.php");
    exit();
}

// Lógica para salvar configurações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
    }

    if (isset($_POST['star_prices'])) {
        foreach ($_POST['star_prices'] as $id => $price) {
            $stmt = $pdo->prepare("UPDATE star_prices SET price = ? WHERE id = ?");
            $stmt->execute([$price, $id]);
        }
    }

    $_SESSION['admin_message'] = "Configurações atualizadas com sucesso.";
    $_SESSION['admin_message_type'] = "success";
    header("Location: settings.php");
    exit();
}

// Carregar configurações
$settings = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $settings = $rows;
} catch (Exception $e) {
    // Tabela pode não existir ainda
}

$site_name = $settings['site_name'] ?? 'massangos';
$commission_rate = $settings['commission_rate'] ?? '12';
$min_withdrawal = $settings['min_withdrawal'] ?? '500';

// Configurações de Estrelas (Visitas Diárias)
$star_1_visits = $settings['star_1_visits'] ?? '25';
$star_2_visits = $settings['star_2_visits'] ?? '100';
$star_3_visits = $settings['star_3_visits'] ?? '400';
$star_4_visits = $settings['star_4_visits'] ?? '1600';
$star_5_visits = $settings['star_5_visits'] ?? '6400';

// Preços Máximos Vídeos
$max_price_video_star_2 = $settings['max_price_video_star_2'] ?? '500';
$max_price_video_star_3 = $settings['max_price_video_star_3'] ?? '1000';
$max_price_video_star_4 = $settings['max_price_video_star_4'] ?? '2000';
$max_price_video_star_5 = $settings['max_price_video_star_5'] ?? '5000';

// Preços Máximos Álbuns
$max_price_album_star_1 = $settings['max_price_album_star_1'] ?? '300';
$max_price_album_star_2 = $settings['max_price_album_star_2'] ?? '600';
$max_price_album_star_3 = $settings['max_price_album_star_3'] ?? '1200';
$max_price_album_star_4 = $settings['max_price_album_star_4'] ?? '2500';
$max_price_album_star_5 = $settings['max_price_album_star_5'] ?? '6000';

// Preços Máximos Posts/Fotos
$max_price_post_star_1 = $settings['max_price_post_star_1'] ?? '150';
$max_price_post_star_2 = $settings['max_price_post_star_2'] ?? '300';
$max_price_post_star_3 = $settings['max_price_post_star_3'] ?? '600';
$max_price_post_star_4 = $settings['max_price_post_star_4'] ?? '1200';
$max_price_post_star_5 = $settings['max_price_post_star_5'] ?? '3000';

// Carregar preços das estrelas
$star_prices = [];
try {
    $star_prices = $pdo->query("SELECT * FROM star_prices ORDER BY stars ASC, duration_type ASC")->fetchAll();
} catch (Exception $e) {
    // Tabela pode não existir ainda
}
?>

<div class="admin-card">
    <h3><i class="fas fa-cog"></i> Configurações do Sistema</h3>
    <p>Ajuste os parâmetros globais da plataforma.</p>

    <form method="POST" style="margin-top: 20px;">
        <div class="settings-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">

            <!-- Configurações Gerais -->
            <div class="settings-section">
                <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px;"><i class="fas fa-info-circle"></i> Geral</h4>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Nome da Plataforma</label>
                    <input type="text" name="settings[site_name]" value="<?= htmlspecialchars($site_name) ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Taxa de Comissão (%)</label>
                    <input type="number" name="settings[commission_rate]" value="<?= htmlspecialchars($commission_rate) ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: bold;">Saque Mínimo (MT)</label>
                    <input type="number" name="settings[min_withdrawal]" value="<?= htmlspecialchars($min_withdrawal) ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
            </div>

            <!-- Sistema de Estrelas -->
            <div class="settings-section">
                <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px;"><i class="fas fa-star"></i> Sistema de Estrelas (Visitas Diárias)</h4>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <label style="width: 80px; font-weight: bold;"><?= $i ?> Estrela<?= $i > 1 ? 's' : '' ?>:</label>
                        <input type="number" name="settings[star_<?= $i ?>_visits]" value="<?= htmlspecialchars($settings["star_{$i}_visits"] ?? ${"star_{$i}_visits"}) ?>" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                        <span style="font-size: 0.8rem; color: #7f8c8d;">visitas</span>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Preços Máximos Vídeos -->
            <div class="settings-section">
                <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px;"><i class="fas fa-video"></i> Preço Máximo: Vídeos</h4>
                <?php for ($i = 2; $i <= 5; $i++): ?>
                    <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <label style="width: 80px; font-weight: bold;"><?= $i ?> Estrelas:</label>
                        <input type="number" name="settings[max_price_video_star_<?= $i ?>]" value="<?= htmlspecialchars($settings["max_price_video_star_{$i}"] ?? ${"max_price_video_star_{$i}"}) ?>" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                        <span style="font-size: 0.8rem; color: #7f8c8d;">MT</span>
                    </div>
                <?php endfor; ?>
                <small style="color: #e74c3c;">* Vídeos requerem no mínimo 2 estrelas para venda.</small>
            </div>

            <!-- Preços Máximos Álbuns -->
            <div class="settings-section">
                <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px;"><i class="fas fa-images"></i> Preço Máximo: Álbuns</h4>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <label style="width: 80px; font-weight: bold;"><?= $i ?> Estrela<?= $i > 1 ? 's' : '' ?>:</label>
                        <input type="number" name="settings[max_price_album_star_<?= $i ?>]" value="<?= htmlspecialchars($settings["max_price_album_star_{$i}"] ?? ${"max_price_album_star_{$i}"}) ?>" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                        <span style="font-size: 0.8rem; color: #7f8c8d;">MT</span>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Preços Máximos Posts/Fotos -->
            <div class="settings-section">
                <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px;"><i class="fas fa-camera"></i> Preço Máximo: Posts/Fotos</h4>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div style="margin-bottom: 10px; display: flex; align-items: center; gap: 10px;">
                        <label style="width: 80px; font-weight: bold;"><?= $i ?> Estrela<?= $i > 1 ? 's' : '' ?>:</label>
                        <input type="number" name="settings[max_price_post_star_<?= $i ?>]" value="<?= htmlspecialchars($settings["max_price_post_star_{$i}"] ?? ${"max_price_post_star_{$i}"}) ?>" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                        <span style="font-size: 0.8rem; color: #7f8c8d;">MT</span>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Gestão de Preços de Estrelas -->
            <?php if (!empty($star_prices)): ?>
                <div class="settings-section" style="grid-column: 1 / -1;">
                    <h4 style="border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px;"><i class="fas fa-shopping-cart"></i> Preços de Compra de Estrelas (M-Pesa / e-Mola)</h4>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
                            <thead>
                                <tr style="background: #f8f9fa; text-align: left;">
                                    <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Nível</th>
                                    <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Duração</th>
                                    <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Preço (MT)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($star_prices as $price_row): ?>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                                            <?= $price_row['stars'] ?> Estrela<?= $price_row['stars'] > 1 ? 's' : '' ?>
                                        </td>
                                        <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                                            <?= $price_row['duration_type'] === 'monthly' ? 'Mensal' : 'Anual' ?>
                                        </td>
                                        <td style="padding: 12px; border-bottom: 1px solid #dee2e6;">
                                            <input type="number" step="0.01" name="star_prices[<?= $price_row['id'] ?>]" value="<?= $price_row['price'] ?>" style="width: 150px; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px; text-align: right;">
            <button type="submit" class="btn-admin btn-edit" style="padding: 12px 30px; font-size: 1rem;"><i class="fas fa-save"></i> Salvar Todas as Configurações</button>
        </div>
    </form>
</div>

<div class="admin-card" style="border-left: 4px solid var(--admin-warning);">
    <h4><i class="fas fa-exclamation-triangle"></i> Zona de Perigo</h4>
    <p>Ações irreversíveis para manutenção do sistema.</p>
    <div style="display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap;">
        <button class="btn-admin btn-delete" onclick="if(confirm('Limpar logs antigos?')) alert('Logs limpos!')">Limpar Logs de Auditoria</button>
        <button class="btn-admin btn-delete" onclick="if(confirm('Limpar cache do sistema?')) alert('Cache limpo!')">Limpar Cache</button>
    </div>
</div>