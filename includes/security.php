<?php
// includes/security.php

// Garante que o ficheiro não é acedido diretamente
if (!defined('SECURE_ACCESS')) {
    die('Acesso direto a este ficheiro é proibido.');
}

// Classe para gerir funcionalidades de segurança
class SecurityManager {

    /**
     * Inicia a sessão de forma segura e configura cabeçalhos de segurança.
     */
    public static function initSecurity() {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0, // A sessão expira quando o navegador é fechado
                'path' => '/',
                'domain' => '', // Pode ser definido para o teu domínio real
                'secure' => ENVIRONMENT === 'production', // Apenas HTTPS em produção
                'httponly' => true, // Impede acesso via JavaScript
                'samesite' => 'Lax' // Protege contra CSRF
            ]);
            session_start();
            
            // Regenera o ID da sessão para prevenir Session Fixation
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
            }
        }
        
        self::setSecurityHeaders();
        self::cleanupSession(); // Limpa dados antigos da sessão
    }

    /**
     * Define cabeçalhos de segurança HTTP.
     */
   private static function setSecurityHeaders() {
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Permissions-Policy: camera=(self), microphone=(self), geolocation=(), interest-cohort=()');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

}

    /**
     * Gera um token CSRF e armazena-o na sessão.
     * @return string O token CSRF.
     */
    public static function generateCSRFToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verifica um token CSRF.
     * @param string $token O token a ser verificado.
     * @return bool True se o token for válido, False caso contrário.
     */
    public static function verifyCSRFToken(string $token): bool {
        if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
            unset($_SESSION['csrf_token']); // O token é de uso único
            return true;
        }
        return false;
    }

    /**
     * Hashes uma senha para armazenamento seguro.
     * @param string $password A senha em texto claro.
     * @return string O hash da senha.
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Verifica se uma senha corresponde a um hash.
     * @param string $password A senha em texto claro.
     * @param string $hash O hash da senha armazenado.
     * @return bool True se a senha corresponder, False caso contrário.
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * Sanitiza uma string de entrada para prevenir XSS.
     * @param string $input A string de entrada.
     * @return string A string sanitizada.
     */
    public static function sanitizeInput(string $input): string {
        $input = trim($input);
        
        // Detecção básica de tentativas de SQL Injection ou XSS
        $patterns = [
            '/union\s+select/i',
            '/<script/i',
            '/onload=/i',
            '/onerror=/i',
            '/document\.cookie/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                self::logSecurityEvent('malicious_input_detected', "Padrão suspeito detectado: $pattern");
                // Poderíamos bloquear aqui, mas por agora apenas limpamos agressivamente
                $input = preg_replace($pattern, '[REMOVIDO]', $input);
            }
        }
        
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Regista um evento de segurança no log do sistema.
     */
    public static function logSecurityEvent(string $type, string $details): void {
        $logFile = APP_ROOT . '/logs/security.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logEntry = "[$timestamp] [$ip] [$type] $details" . PHP_EOL;
        
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Valida os dados de entrada com base num conjunto de regras.
     * @param array $data O array de dados a ser validado (ex: $_POST, $_GET).
     * @param array $rules Um array associativo onde a chave é o nome do campo
     * e o valor é um array de regras (ex: ['required' => true, 'min_length' => 5]).
     * Pode incluir 'type' => 'email' ou 'type' => 'password' para validações específicas.
     * @return array Um array associativo de erros, onde a chave é o nome do campo e o valor é a mensagem de erro.
     */
    public static function validateInput(array $data, array $rules): array {
        $errors = [];

        foreach ($rules as $field => $field_rules) {
            $value = $data[$field] ?? '';

            // Se o campo for obrigatório e estiver vazio
            if (isset($field_rules['required']) && $field_rules['required'] === true) {
                if (empty($value) && $value !== '0') { // Permite '0' como valor válido se não estiver vazio
                    $errors[$field] = "O campo " . ucwords(str_replace('_', ' ', $field)) . " é obrigatório.";
                    continue; // Pula para a próxima regra para este campo
                }
            }

            // Se o valor não estiver vazio, aplica outras validações
            if (!empty($value) || (isset($field_rules['required']) && $field_rules['required'] === false)) {
                if (isset($field_rules['min_length']) && strlen($value) < $field_rules['min_length']) {
                    $errors[$field] = "O campo " . ucwords(str_replace('_', ' ', $field)) . " deve ter pelo menos " . $field_rules['min_length'] . " caracteres.";
                }

                if (isset($field_rules['max_length']) && strlen($value) > $field_rules['max_length']) {
                    $errors[$field] = "O campo " . ucwords(str_replace('_', ' ', $field)) . " não deve exceder " . $field_rules['max_length'] . " caracteres.";
                }

                // *** AJUSTE CRÍTICO AQUI: Adicionado isset($field_rules['type']) ***
                if (isset($field_rules['type'])) { 
                    switch ($field_rules['type']) {
                        case 'email':
                            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $errors[$field] = "Formato de e-mail inválido.";
                            }
                            break;
                        case 'password':
                            // A linha 215 no teu aviso estava aqui!
                            $minPasswordLength = defined('MIN_PASSWORD_LENGTH') ? MIN_PASSWORD_LENGTH : 8; // Obtém de config.php
                            if (strlen($value) < $minPasswordLength || 
                                !preg_match('/[A-Z]/', $value) || 
                                !preg_match('/[a-z]/', $value) || 
                                !preg_match('/[0-9]/', $value)) {
                                $errors[$field] = "A senha deve ter pelo menos {$minPasswordLength} caracteres, incluindo maiúsculas, minúsculas e números.";
                            }
                            break;
                        case 'url':
                            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                                $errors[$field] = "Formato de URL inválido.";
                            }
                            break;
                        case 'int':
                            if (!filter_var($value, FILTER_VALIDATE_INT)) {
                                $errors[$field] = "O campo " . ucwords(str_replace('_', ' ', $field)) . " deve ser um número inteiro.";
                            }
                            break;
                        case 'float':
                            if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                                $errors[$field] = "O campo " . ucwords(str_replace('_', ' ', $field)) . " deve ser um número decimal.";
                            }
                            break;
                        // Adicionar mais tipos de validação conforme necessário
                    }
                }
            }
        }
        return $errors;
    }

    /**
     * Controla o limite de requisições por um período de tempo (Rate Limiting).
     * @param string $key Uma chave única para o tipo de limite (ex: 'login_192.168.1.1').
     * @param int $max_attempts O número máximo de tentativas permitidas.
     * @param int $time_window O período de tempo em segundos.
     * @return bool True se a requisição for permitida, False caso contrário.
     */
    public static function checkRateLimit(string $key, int $max_attempts, int $time_window): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $attempts_key = 'rate_limit_' . $key;
        $currentTime = time();

        if (!isset($_SESSION[$attempts_key])) {
            $_SESSION[$attempts_key] = ['count' => 1, 'time' => $currentTime];
            return true;
        }

        $attempts = $_SESSION[$attempts_key]['count'];
        $first_attempt_time = $_SESSION[$attempts_key]['time'];

        if (($currentTime - $first_attempt_time) > $time_window) {
            $_SESSION[$attempts_key] = ['count' => 1, 'time' => $currentTime];
            return true;
        } elseif ($attempts < $max_attempts) {
            $_SESSION[$attempts_key]['count']++;
            return true;
        } else {
            return false; // Limite excedido
        }
    }

    /**
     * Regista uma tentativa de login (sucesso ou falha) para controle de bloqueio.
     * @param string $username_email O nome de usuário ou email tentado.
     * @param bool $success True para login bem-sucedido, False para falha.
     */
    public static function logLoginAttempt(string $username_email, bool $success): void {
        $pdo = \Massango\Core\Database::getInstance();

        // Usar um hash do username/email para não expor dados diretamente em logs ou sessão,
        // mas ainda ser capaz de identificar o utilizador para o bloqueio.
        $hashed_identifier = hash('sha256', strtolower($username_email));
        $attempts_key = 'login_attempts_' . $hashed_identifier;
        $currentTime = time();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($success) {
            // Se o login for bem-sucedido, limpa as tentativas falhadas na sessão para este identificador
            unset($_SESSION[$attempts_key]); 
        } else {
            if (!isset($_SESSION[$attempts_key])) {
                $_SESSION[$attempts_key] = ['count' => 1, 'last_attempt' => $currentTime];
            } else {
                $_SESSION[$attempts_key]['count']++;
                $_SESSION[$attempts_key]['last_attempt'] = $currentTime;
            }
        }
    }

    /**
     * Verifica se o login está bloqueado para um determinado utilizador/email.
     * Isto verifica o bloqueio de sessão (se MAX_LOGIN_ATTEMPTS foi atingido recentemente).
     * O bloqueio no banco de dados (`locked_until`) é verificado no processo de login principal.
     * @param string $username_email O nome de usuário ou email a verificar.
     * @return bool True se o login estiver bloqueado, False caso contrário.
     */
    public static function isLoginBlocked(string $username_email): bool {
        $hashed_identifier = hash('sha256', strtolower($username_email));
        $attempts_key = 'login_attempts_' . $hashed_identifier;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION[$attempts_key])) {
            $attempts = $_SESSION[$attempts_key]['count'];
            $last_attempt_time = $_SESSION[$attempts_key]['last_attempt'];

            $loginLockoutTime = defined('LOGIN_LOCKOUT_TIME') ? LOGIN_LOCKOUT_TIME : 900; // 15 minutos padrão
            $maxLoginAttempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5; // 5 tentativas padrão

            if ((time() - $last_attempt_time) < $loginLockoutTime && $attempts >= $maxLoginAttempts) {
                return true; // Bloqueado temporariamente pela sessão
            }
        }
        return false;
    }

    /**
     * Limpa dados antigos da sessão (tentativas de login, rate limits expirados).
     * Deve ser chamada periodicamente, por exemplo, em initSecurity().
     */
    public static function cleanupSession() {
        $currentTime = time();
        
        foreach ($_SESSION as $key => $value) {
            // *** AJUSTE CRÍTICO AQUI: Garante que $value é um array antes de tentar aceder às chaves ***
            if (!is_array($value)) {
                continue; // Pula para o próximo item da sessão se não for um array.
            }

            // Remove rate limits antigos que já expiraram.
            if (strpos($key, 'rate_limit_') === 0 && isset($value['time'])) {
                // Remove após 2x a janela de tempo de rate limit para garantir que não persistam desnecessariamente.
                $rateLimitTimeWindow = defined('RATE_LIMIT_TIME_WINDOW') ? RATE_LIMIT_TIME_WINDOW : 60;
                if ($currentTime - $value['time'] > $rateLimitTimeWindow * 2) { 
                    unset($_SESSION[$key]);
                }
            }
            
            // Remove registos de tentativas de login antigas.
            if (strpos($key, 'login_attempts_') === 0 && isset($value['last_attempt'])) {
                // Remove após 2x o tempo de bloqueio de login para limpar sessões.
                $loginLockoutTime = defined('LOGIN_LOCKOUT_TIME') ? LOGIN_LOCKOUT_TIME : 900;
                if ($currentTime - $value['last_attempt'] > $loginLockoutTime * 2) { 
                    unset($_SESSION[$key]);
                }
            }
        }
    }

    /**
     * Verifica se o utilizador está autenticado.
     * Esta função é a principal para determinar o estado de login.
     * @return bool True se autenticado, False caso contrário.
     */
    public static function isAuthenticated(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Verifica se 'user_id' e 'login_time' estão definidos na sessão e se a sessão não expirou.
        // Assume que SESSION_TIMEOUT é definido em config.php
        $sessionTimeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600; // Padrão 1 hora
        
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['login_time']) && 
               (time() - $_SESSION['login_time'] < $sessionTimeout);
    }
}