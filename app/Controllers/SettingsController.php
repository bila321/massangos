<?php
// app/Controllers/SettingsController.php
// Stub - logica complexa (NudeNet, crop, upload) sera migrada em iteracao futura.
namespace Massango\Controllers;

use Massango\Models\User;

class SettingsController
{
    private \PDO $pdo;
    private int  $userId;

    public function __construct(\PDO $pdo, int $userId)
    {
        $this->pdo    = $pdo;
        $this->userId = $userId;
    }

    public function handle(array $post, array $files, array &$session): array
    {
        // Delega ao entry point que inclui o ficheiro original
        return ['_delegate' => true];
    }
}
