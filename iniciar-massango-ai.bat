@echo off
title Massango AI Services
color 0A

echo ============================================================
echo   MASSANGO AI SERVICES — Iniciando...
echo ============================================================
echo.

:: Verificar se o venv existe
if not exist "C:\xampp\htdocs\massangos\ai\venv\Scripts\activate.bat" (
    echo [ERRO] Ambiente virtual nao encontrado em:
    echo        C:\xampp\htdocs\massangos\ai\venv\
    echo.
    echo Execute primeiro: python -m venv C:\xampp\htdocs\massangos\ai\venv
    pause
    exit /b 1
)

:: Verificar se o XAMPP Apache está a correr
echo [*] A verificar XAMPP...
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I "httpd.exe" >NUL
if errorlevel 1 (
    echo [AVISO] Apache nao esta a correr. Inicie o XAMPP manualmente.
    echo          A continuar na mesma...
    echo.
) else (
    echo [OK] Apache esta a correr.
)

echo.
echo [*] A iniciar FastAPI ^(identificacao de identidade^)...
start "Massango FastAPI" cmd /k "cd /d C:\xampp\htdocs\massangos && .\ai\venv\Scripts\activate.bat && python -m uvicorn ai.main:app --host 127.0.0.1 --port 8000 --reload && pause"

:: Aguardar 5 segundos para o FastAPI iniciar antes do worker
echo [*] A aguardar FastAPI iniciar ^(5 segundos^)...
timeout /t 5 /nobreak >NUL

echo [*] A iniciar Worker NudeNet ^(processamento de media^)...
start "Massango Worker" cmd /k "cd /d C:\xampp\htdocs\massangos\ai && .\venv\Scripts\activate.bat && python worker.py && pause"

echo.
echo ============================================================
echo   SERVICOS INICIADOS:
echo   - FastAPI:  http://127.0.0.1:8000
echo   - Health:   http://127.0.0.1:8000/identity/health
echo   - Docs:     http://127.0.0.1:8000/docs
echo   - Worker:   A processar media_queue em background
echo ============================================================
echo.
echo   Para parar os servicos, feche as janelas "Massango FastAPI"
echo   e "Massango Worker".
echo.
echo   Esta janela pode ser fechada.
echo ============================================================
pause
