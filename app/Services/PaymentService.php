<?php

namespace Massango\Services;

use PDO;
use Exception;
use Massango\Models\AlbumPartner;
use Massango\Models\Notification;

class PaymentService
{

    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Cria um novo pedido de venda/pagamento.
     */
    public function createSale(int $buyerId, int $sellerId, string $contentType, int $contentId, float $amount, string $paymentMethod, string $phoneNumber): array
    {
        $split = PricingRuleService::calculateSplit($this->pdo, $amount);

        $stmt = $this->pdo->prepare("
            INSERT INTO sales (buyer_id, seller_id, content_type, content_id, amount, commission_amount, seller_amount, status, payment_method)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
        ");

        $stmt->execute([
            $buyerId,
            $sellerId,
            $contentType,
            $contentId,
            $amount,
            $split['platform_commission'],
            $split['seller_amount'],
            $paymentMethod
        ]);

        $saleId = (int)$this->pdo->lastInsertId();
        $reference = "SALE-" . $saleId . "-" . time();

        try {
            if ($paymentMethod === 'mpesa') {
                $mpesa = new MpesaService();
                $result = $mpesa->initiateC2BPayment($reference, $phoneNumber, $amount, $reference);
                return [
                    'success' => true,
                    'sale_id' => $saleId,
                    'message' => 'Por favor, confirme o pagamento no seu telemóvel.',
                    'api_response' => $result
                ];
            } elseif ($paymentMethod === 'emola') {
                $emola = new EmolaService();
                $result = $emola->initiatePayment($phoneNumber, $amount, $reference);
                return [
                    'success' => true,
                    'sale_id' => $saleId,
                    'message' => 'Por favor, confirme o pagamento no seu telemóvel.',
                    'api_response' => $result
                ];
            }
        } catch (Exception $e) {
            // Se a API falhar, marcamos a venda como falhada
            $this->pdo->prepare("UPDATE sales SET status = 'failed' WHERE id = ?")->execute([$saleId]);
            throw $e;
        }

        return ['success' => false, 'message' => 'Método de pagamento inválido.'];
    }


    /**
     * Cria um pedido de compra de Estrelas (Stars).
     */
    public function createStarsSale(int $buyerId, int $stars, string $duration, float $amount, string $paymentMethod, string $phoneNumber): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO sales (buyer_id, seller_id, content_type, content_id, amount, commission_amount, seller_amount, status, payment_method)
            VALUES (?, 0, 'stars', ?, ?, 0, 0, 'pending', ?)
        ");

        $stmt->execute([$buyerId, $stars, $amount, $paymentMethod]);

        $saleId = (int)$this->pdo->lastInsertId();
        $reference = "STARS-" . $saleId . "-" . $stars . "-" . $duration . "-" . time();

        try {
            if ($paymentMethod === 'mpesa') {
                $mpesa = new MpesaService();
                $result = $mpesa->initiateC2BPayment($reference, $phoneNumber, $amount, $reference);
                return [
                    'success' => true,
                    'sale_id' => $saleId,
                    'message' => 'Por favor, confirme o pagamento no seu M-Pesa.',
                    'api_response' => $result
                ];
            } elseif ($paymentMethod === 'emola') {
                $emola = new EmolaService();
                $result = $emola->initiatePayment($phoneNumber, $amount, $reference);
                return [
                    'success' => true,
                    'sale_id' => $saleId,
                    'message' => 'Por favor, confirme o pagamento no seu e-Mola.',
                    'api_response' => $result
                ];
            }
        } catch (Exception $e) {
            $this->pdo->prepare("UPDATE sales SET status = 'failed' WHERE id = ?")->execute([$saleId]);
            throw $e;
        }

        return ['success' => false, 'message' => 'Método de pagamento inválido.'];
    }

    /**
     * Confirma um pagamento e libera o acesso ao conteúdo.
     */
    public function confirmPayment(int $saleId, string $transactionId, string $reference = ''): bool
    {
        try {
            $this->pdo->beginTransaction();

            // 1. Obter dados da venda
            $stmt = $this->pdo->prepare("SELECT * FROM sales WHERE id = ? AND status = 'pending'");
            $stmt->execute([$saleId]);
            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                throw new Exception("Venda não encontrada ou já processada.");
            }

            // 2. Atualizar status da venda
            $stmt = $this->pdo->prepare("UPDATE sales SET status = 'completed', transaction_id = ? WHERE id = ?");
            $stmt->execute([$transactionId, $saleId]);

            // 3. Lógica específica por tipo de conteúdo
            if ($sale['content_type'] === 'stars') {
                // Compra de Estrelas
                // Referência esperada: STARS-{saleId}-{stars}-{duration}-{timestamp}
                if (preg_match('/STARS-\d+-(\d+)-(\w+)-/', $reference, $matches)) {
                    $stars = (int)$matches[1];
                    $duration = $matches[2];

                    $days = ($duration === 'yearly') ? 365 : 30;
                    $expiration = date('Y-m-d H:i:s', strtotime("+{$days} days"));

                    $stmt = $this->pdo->prepare("UPDATE users SET stars = ?, stars_expiration = ? WHERE id = ?");
                    $stmt->execute([$stars, $expiration, $sale['buyer_id']]);
                }
            } else {
                // Conteúdo normal (Vídeo/Álbum)
                // Verificar se há parceiros (apenas para álbuns)
                if ($sale['content_type'] === 'album') {
                    $this->distributeAlbumSaleRevenue($sale);
                } else {
                    // Vendedor único
                    if ($sale['seller_id'] > 0) {
                        $stmt = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                        $stmt->execute([$sale['seller_amount'], $sale['seller_id']]);
                    }
                }

                // Liberar acesso ao conteúdo
                $stmt = $this->pdo->prepare("
                    INSERT INTO content_access (user_id, content_type, content_id, sale_id)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $sale['buyer_id'],
                    $sale['content_type'],
                    $sale['content_id'],
                    $saleId
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Erro ao confirmar pagamento: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Distribui a receita de venda de um álbum entre o criador e parceiros.
     */
    private function distributeAlbumSaleRevenue(array $sale): void
    {
        $albumId = $sale['content_id'];
        $sellerAmount = $sale['seller_amount'];
        $saleId = $sale['id'];

        // Obter informações do álbum
        $stmt = $this->pdo->prepare("SELECT name FROM albums WHERE id = ?");
        $stmt->execute([$albumId]);
        $album = $stmt->fetch(PDO::FETCH_ASSOC);
        $albumName = $album['name'] ?? 'Álbum';

        // Obter parceiros do álbum
        $stmt = $this->pdo->prepare("
            SELECT ap.user_id, ap.percentage
            FROM album_partners ap
            WHERE ap.album_id = ?
            ORDER BY ap.created_at ASC
        ");
        $stmt->execute([$albumId]);
        $partners = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($partners)) {
            // Sem parceiros: todo o valor vai para o vendedor
            $stmt = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$sellerAmount, $sale['seller_id']]);

            // Registrar no histórico
            $this->recordDistribution($saleId, $albumId, $sale['seller_id'], $sellerAmount, 100, 'creator');
        } else {
            // Com parceiros: distribuir proporcionalmente
            $totalPartnerPercentage = 0;
            foreach ($partners as $partner) {
                $totalPartnerPercentage += (float)$partner['percentage'];
            }

            // Percentagem do criador = 100% - soma das percentagens dos parceiros
            $creatorPercentage = 100 - $totalPartnerPercentage;

            // Distribuir valor ao criador
            if ($creatorPercentage > 0) {
                $creatorAmount = ($sellerAmount * $creatorPercentage) / 100;
                $stmt = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$creatorAmount, $sale['seller_id']]);

                // Registrar no histórico
                $this->recordDistribution($saleId, $albumId, $sale['seller_id'], $creatorAmount, $creatorPercentage, 'creator');
            }

            // Distribuir valor aos parceiros e enviar notificações
            foreach ($partners as $partner) {
                $partnerAmount = ($sellerAmount * $partner['percentage']) / 100;
                $stmt = $this->pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$partnerAmount, $partner['user_id']]);

                // Registrar no histórico
                $this->recordDistribution($saleId, $albumId, $partner['user_id'], $partnerAmount, $partner['percentage'], 'partner');

                // Enviar notificação ao parceiro
                $this->notifyPartner($partner['user_id'], $albumName, $partnerAmount, $partner['percentage'], $albumId);
            }
        }
    }

    /**
     * Registra uma distribuição de receita no histórico.
     */
    private function recordDistribution(int $saleId, int $albumId, int $userId, float $amount, float $percentage, string $role): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO revenue_distributions (sale_id, album_id, user_id, amount, percentage, role)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$saleId, $albumId, $userId, $amount, $percentage, $role]);
        } catch (Exception $e) {
            error_log("Erro ao registrar distribuição: " . $e->getMessage());
        }
    }

    /**
     * Envia notificação ao parceiro sobre a venda.
     */
    private function notifyPartner(int $partnerId, string $albumName, float $amount, float $percentage, int $albumId): void
    {
        try {
            $message = "Você recebeu MZN " . number_format($amount, 2) . " (" . number_format($percentage, 2) . "%) pela venda do álbum '$albumName'";
            $link = BASE_URL . "partner_sales_dashboard.php";

            Notification::createNotification(
                $this->pdo,
                $partnerId,
                $message,
                $link,
                null,
                'album_sale_revenue',
                $albumId
            );
        } catch (Exception $e) {
            error_log("Erro ao enviar notificação ao parceiro: " . $e->getMessage());
        }
    }

    /**
     * Verifica se um usuário tem acesso a um conteúdo.
     */
    public function hasAccess(int $userId, string $contentType, int $contentId): bool
    {
        // 1. Verificar se o usuário é o dono do conteúdo
        $table = '';
        switch ($contentType) {
            case 'video':
                $table = 'videos';
                break;
            case 'album':
                $table = 'albums';
                break;
            case 'post':
                $table = 'posts';
                break;
            default:
                return true; // Tipo desconhecido, permite acesso por segurança ou lança erro
        }

        $stmt = $this->pdo->prepare("SELECT user_id FROM $table WHERE id = ?");
        $stmt->execute([$contentId]);
        $ownerId = $stmt->fetchColumn();

        if ($ownerId == $userId) return true;

        // 1.2 Verificar se o usuário é parceiro do álbum (acesso gratuito para parceiros)
        if ($contentType === 'album' && $userId > 0) {
            if (AlbumPartner::isPartner($this->pdo, $contentId, $userId)) {
                return true;
            }
        }

        // 1.5 Verificar se o conteúdo está aprovado
        try {
            $stmt = $this->pdo->prepare("SELECT is_approved FROM $table WHERE id = ?");
            $stmt->execute([$contentId]);
            $isApproved = $stmt->fetchColumn();
            if ($isApproved === 0) return false; // Se não estiver aprovado, ninguém além do dono tem acesso
        } catch (\PDOException $e) {
            // Se a coluna não existir, ignoramos
        }

        // 2. Verificar se o conteúdo é gratuito
        // Nota: A tabela 'posts' pode não ter a coluna 'is_for_sale' ainda.
        // Vamos verificar se a coluna existe antes de tentar ler.
        $isForSale = false;
        try {
            $stmt = $this->pdo->prepare("SELECT is_for_sale FROM $table WHERE id = ?");
            $stmt->execute([$contentId]);
            $isForSale = $stmt->fetchColumn();
        } catch (\PDOException $e) {
            // Se a coluna não existir (como em 'posts'), assumimos que não está à venda
            $isForSale = false;
        }

        if (!$isForSale) return true;

        // 2.1 Se o conteúdo é um álbum à venda, verificar novamente se é parceiro
        // (parceiros têm acesso gratuito mesmo a álbuns pagos)
        if ($contentType === 'album' && $userId > 0) {
            if (AlbumPartner::isPartner($this->pdo, $contentId, $userId)) {
                return true;
            }
        }

        // 3. Verificar se o usuário comprou o conteúdo
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM content_access 
            WHERE user_id = ? AND content_type = ? AND content_id = ?
        ");
        $stmt->execute([$userId, $contentType, $contentId]);

        if ($stmt->fetchColumn() > 0) {
            return true;
        }

        // 3.1 Última verificação: se é parceiro de um álbum pago, permitir acesso
        if ($contentType === 'album' && $userId > 0) {
            if (AlbumPartner::isPartner($this->pdo, $contentId, $userId)) {
                return true;
            }
        }

        return false;
    }
}
