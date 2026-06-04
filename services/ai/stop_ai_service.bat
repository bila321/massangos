@echo off
:: stop_ai_service.bat — Para o serviço FastAPI na porta 8000

title Parar Massango AI Service

echo [INFO] A parar Massango AI Service (porta 8000)...

:: Encontrar e terminar o processo na porta 8000
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":8000 " ^| findstr "LISTENING"') do (
    echo [INFO] Terminando processo PID: %%a
    taskkill /PID %%a /F >nul 2>&1
)

timeout /t 1 /nobreak >nul

netstat -ano | findstr ":8000 " | findstr "LISTENING" >nul 2>&1
if %errorlevel% == 0 (
    echo [AVISO] Processo ainda em execucao. Tente manualmente: taskkill /IM python.exe /F
) else (
    echo [OK] Servico parado com sucesso.
)

exit /b 0
