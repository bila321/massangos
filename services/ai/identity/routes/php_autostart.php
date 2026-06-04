<?php
/**
 * ai/identity/php_autostart.php
 *
 * Tenta iniciar o serviço FastAPI automaticamente via PHP se estiver offline.
 * Incluir no topo de php_trigger.php ou chamar antes de trigger_ai_identity_verification().
 *
 * COMO FUNCIONA:
 *  1. Verifica se o FastAPI responde em http://127.0.0.1:8000/identity/health
 *  2. Se não responde, tenta iniciar via start_ai_service.bat (Windows) ou script shell
 *  3. Aguarda até 10 segundos pelo serviço ficar disponível
 *  4. Retorna true se disponível, false se falhou
 *
 * USO no php_trigger.php:
 *  require_once __DIR__ . '/php_autostart.php';
 *  ensure_ai_service_running(); // chama antes do trigger
 */

define('AI_AUTOSTART_BAT',     __DIR__ . '/../../start_ai_service.bat');
define('AI_AUTOSTART_TIMEOUT', 12); // segundos para aguardar o serviço iniciar
define('AI_AUTOSTART_LOCK',    sys_get_temp_dir() . '/massango_ai_starting.lock');

/**
 * Garante que o serviço FastAPI está em execução.
 * Se não estiver, tenta iniciá-lo e aguarda.
 *
 * @return bool true se o serviço está disponível
 */
function ensure_ai_service_running(): bool {
    // Já está online — nada a fazer
    if (is_ai_service_available()) {
        return true;
    }

    // Evitar múltiplos arranques simultâneos (lock file)
    if (file_exists(AI_AUTOSTART_LOCK)) {
        $lock_age = time() - filemtime(AI_AUTOSTART_LOCK);
        if ($lock_age < AI_AUTOSTART_TIMEOUT) {
            // Outro pedido já está a tentar iniciar — aguardar
            error_log('[AI Autostart] Outro processo já está a iniciar o serviço. Aguardando...');
            return wait_for_ai_service(AI_AUTOSTART_TIMEOUT - $lock_age);
        }
        // Lock expirado — remover e tentar de novo
        @unlink(AI_AUTOSTART_LOCK);
    }

    // Criar lock
    file_put_contents(AI_AUTOSTART_LOCK, getmypid());

    error_log('[AI Autostart] FastAPI offline — a tentar iniciar automaticamente...');

    $started = start_ai_service();

    if (!$started) {
        @unlink(AI_AUTOSTART_LOCK);
        error_log('[AI Autostart] Falha ao iniciar o serviço. Inicie manualmente: start_ai_service.bat');
        return false;
    }

    // Aguardar o serviço ficar disponível
    $available = wait_for_ai_service(AI_AUTOSTART_TIMEOUT);

    @unlink(AI_AUTOSTART_LOCK);

    if ($available) {
        error_log('[AI Autostart] Serviço iniciado com sucesso!');
    } else {
        error_log('[AI Autostart] Serviço não ficou disponível a tempo. Verifique os logs em ai/logs/uvicorn.log');
    }

    return $available;
}

/**
 * Tenta iniciar o processo FastAPI em background.
 */
function start_ai_service(): bool {
    $ai_dir = realpath(__DIR__ . '/../../');

    if (!$ai_dir || !is_dir($ai_dir)) {
        error_log('[AI Autostart] Diretório AI não encontrado: ' . $ai_dir);
        return false;
    }

    // Windows — usar start_ai_service.bat
    if (PHP_OS_FAMILY === 'Windows') {
        $bat = realpath(AI_AUTOSTART_BAT);
        if ($bat && file_exists($bat)) {
            // Executar em background sem janela
            $cmd = 'start /B "" "' . $bat . '" > NUL 2>&1';
            pclose(popen($cmd, 'r'));
            error_log('[AI Autostart] Iniciado via BAT: ' . $bat);
            return true;
        }

        // Fallback: iniciar uvicorn directamente
        $venv_python = $ai_dir . '\venv\Scripts\python.exe';
        if (file_exists($venv_python)) {
            $cmd = 'start /B "" "' . $venv_python . '" -m uvicorn main:app '
                 . '--host 127.0.0.1 --port 8000 --log-level warning '
                 . '>> "' . $ai_dir . '\logs\uvicorn.log" 2>&1';
            // Mudar para o directório correcto
            $full_cmd = 'cd /d "' . $ai_dir . '" && ' . $cmd;
            pclose(popen($full_cmd, 'r'));
            error_log('[AI Autostart] Iniciado via venv Python directamente');
            return true;
        }

        // Último fallback: python do sistema
        $cmd = 'cd /d "' . $ai_dir . '" && start /B "" python -m uvicorn main:app '
             . '--host 127.0.0.1 --port 8000 > NUL 2>&1';
        pclose(popen($cmd, 'r'));
        error_log('[AI Autostart] Iniciado via python do sistema');
        return true;
    }

    // Linux/Mac — para ambiente de produção
    $venv_python = $ai_dir . '/venv/bin/python';
    $python      = file_exists($venv_python) ? $venv_python : 'python3';
    $log_file    = $ai_dir . '/logs/uvicorn.log';

    @mkdir(dirname($log_file), 0755, true);

    $cmd = 'cd ' . escapeshellarg($ai_dir)
         . ' && nohup ' . escapeshellarg($python)
         . ' -m uvicorn main:app --host 127.0.0.1 --port 8000 --log-level warning'
         . ' >> ' . escapeshellarg($log_file) . ' 2>&1 &';

    exec($cmd);
    error_log('[AI Autostart] Iniciado via nohup (Linux/Mac)');
    return true;
}

/**
 * Aguarda o serviço ficar disponível durante $timeout segundos.
 */
function wait_for_ai_service(int $timeout): bool {
    $start = time();
    while ((time() - $start) < $timeout) {
        sleep(1);
        if (is_ai_service_available()) {
            return true;
        }
    }
    return false;
}
