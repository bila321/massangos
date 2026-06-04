<?php

namespace Massango\Services;

use PDO;

class PricingRuleService
{

    /**
     * Obtém uma configuração do sistema.
     */
    public static function getSetting(PDO $pdo, string $key, $default = null)
    {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value !== false ? $value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Define as regras de preço baseadas nas estrelas.
     */
    public static function getRules(PDO $pdo, int $stars): array
    {
        return [
            'can_sell_video' => $stars >= 2,
            'can_sell_album' => $stars >= 0,
            'can_sell_post' => $stars >= 1,
            'max_video_price' => self::getMaxPrice($pdo, 'video', $stars),
            'max_album_price' => self::getMaxPrice($pdo, 'album', $stars),
            'max_post_price' => self::getMaxPrice($pdo, 'post', $stars),
            'min_album_photos' => 10,
            'min_video_duration' => 60 // segundos
        ];
    }

    /**
     * Retorna o preço máximo permitido para um tipo de conteúdo e nível de estrelas.
     */
    public static function getMaxPrice(PDO $pdo, string $type, int $stars): float
    {
        if ($type === 'video') {
            if ($stars < 2) return 0;
            $key = "max_price_video_star_" . min($stars, 5);
            $default = [2 => 500, 3 => 1000, 4 => 2000, 5 => 5000];
            return (float)self::getSetting($pdo, $key, $default[min($stars, 5)] ?? 5000);
        } else if ($type === 'album') {
            if ($stars < 0) return 0;
            $key = "max_price_album_star_" . min($stars, 5);
            $default = [0 => 100, 1 => 300, 2 => 600, 3 => 1200, 4 => 2500, 5 => 6000];
            return (float)self::getSetting($pdo, $key, $default[min($stars, 5)] ?? 6000);
        } else if ($type === 'post') {
            if ($stars < 1) return 0;
            $key = "max_price_post_star_" . min($stars, 5);
            $default = [1 => 150, 2 => 300, 3 => 600, 4 => 1200, 5 => 3000];
            return (float)self::getSetting($pdo, $key, $default[min($stars, 5)] ?? 3000);
        }
        return 0;
    }

    /**
     * Valida se um conteúdo pode ser colocado à venda.
     */
    public static function validateForSale(PDO $pdo, string $type, int $stars, float $price, array $contentData): array
    {
        $rules = self::getRules($pdo, $stars);
        $errors = [];

        if ($type === 'video') {
            if (!$rules['can_sell_video']) {
                $errors[] = "Você precisa de pelo menos 2 estrelas para vender vídeos.";
            }
            if ($price > $rules['max_video_price']) {
                $errors[] = "O preço máximo para o seu nível de estrelas é " . number_format($rules['max_video_price'], 2) . " MT.";
            }
            if (isset($contentData['duration']) && $contentData['duration'] < $rules['min_video_duration']) {
                $errors[] = "O vídeo deve ter pelo menos 1 minuto de duração.";
            }
        } elseif ($type === 'album') {
            if (!$rules['can_sell_album']) {
                $errors[] = "Você não tem permissão para vender álbuns.";
            }
            if ($price > $rules['max_album_price']) {
                $errors[] = "O preço máximo para o seu nível de estrelas é " . number_format($rules['max_album_price'], 2) . " MT.";
            }
            if (isset($contentData['photo_count']) && $contentData['photo_count'] < $rules['min_album_photos']) {
                $errors[] = "O álbum deve ter pelo menos 10 fotos.";
            }
        } elseif ($type === 'post') {
            if (!$rules['can_sell_post']) {
                $errors[] = "Você precisa de pelo menos 1 estrela para vender fotos/posts.";
            }
            if ($price > $rules['max_post_price']) {
                $errors[] = "O preço máximo para o seu nível de estrelas é " . number_format($rules['max_post_price'], 2) . " MT.";
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Calcula a divisão do valor entre plataforma e vendedor.
     */
    public static function calculateSplit(PDO $pdo, float $totalAmount): array
    {
        $commission_rate = (float)self::getSetting($pdo, 'commission_rate', 12) / 100;
        $platform_commission = round($totalAmount * $commission_rate, 2);
        return [
            'platform_commission' => $platform_commission,
            'seller_amount' => $totalAmount - $platform_commission
        ];
    }
}

