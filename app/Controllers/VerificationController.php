<?php
// app/Controllers/VerificationController.php
// Stub - integracao com face_api_helper sera migrada em iteracao futura.
namespace Massango\Controllers;

class VerificationController
{
    private \PDO $pdo;
    private int  $userId;

    public function __construct(\PDO $pdo, int $userId)
    {
        $this->pdo    = $pdo;
        $this->userId = $userId;
    }

    public function submit(array $post): array
    {
        // Delega ao entry point que inclui o ficheiro original
        return ['_delegate' => true];
    }
}
