@echo off
:: ============================================================
:: start_ai_service.bat
:: Colocar em: C:\Users\Bila\Desktop\massangos\massango\ai\
::
:: Inicia o serviço FastAPI (Massango AI) em background.
:: Duplo-clique para iniciar, ou adicionar ao arranque do Windows.
:: ============================================================

title Massango AI Service

:: Caminho base do projecto — ajustar se necessário
set PROJECT_DIR=%~dp0
set VENV_PYTHON=%PROJECT_DIR%venv\Scripts\python.exe
set LOG_FILE=%PROJECT_DIR%logs\uvicorn.log

:: Criar pasta de logs se não existir
if not exist "%PROJECT_DIR%logs" mkdir "%PROJECT_DIR%logs"

:: Verificar se o venv existe
if not exist "%VENV_PYTHON%" (
    echo [ERRO] Ambiente virtual nao encontrado em: %VENV_PYTHON%
    echo Execute primeiro: python -m venv venv ^&^& venv\Scripts\activate ^&^& pip install -r requirements.txt
    pause
    exit /b 1
)

:: Verificar se já está em execução na porta 8000
netstat -ano | findstr ":8000 " | findstr "LISTENING" >nul 2>&1
if %errorlevel% == 0 (
    echo [INFO] FastAPI ja esta em execucao na porta 8000.
    echo [INFO] Para reiniciar, execute stop_ai_service.bat primeiro.
    pause
    exit /b 0
)

echo [INFO] A iniciar Massango AI Service...
echo [INFO] Log: %LOG_FILE%
echo [INFO] URL: http://127.0.0.1:8000
echo [INFO] Health: http://127.0.0.1:8000/identity/health

:: Iniciar uvicorn em background (sem janela visível)
:: Usar pythonw.exe para não mostrar janela de terminal
start "MassangoAI" /B "%PROJECT_DIR%venv\Scripts\python.exe" -m uvicorn main:app --host 127.0.0.1 --port 8000 --log-level info >> "%LOG_FILE%" 2>&1

:: Aguardar 3 segundos e verificar se iniciou
timeout /t 3 /nobreak >nul

netstat -ano | findstr ":8000 " | findstr "LISTENING" >nul 2>&1
if %errorlevel% == 0 (
    echo [OK] Massango AI Service iniciado com sucesso!
    echo [OK] Acesse: http://127.0.0.1:8000/identity/health
) else (
    echo [ERRO] Servico nao iniciou. Verifique o log: %LOG_FILE%
    echo Ultima linha do log:
    type "%LOG_FILE%" 2>nul | findstr /v "^$" | tail -5
)

exit /b 0
