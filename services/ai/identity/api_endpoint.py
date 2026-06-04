# ai/identity/api_endpoint.py
# Endpoint FastAPI para verificação de identidade
#
# FIXES APLICADOS:
#  - BUG: importava `router` de routes/face_routes.py mas esse módulo exporta `face_router`
#    → corrigido para `from .routes.face_routes import face_router as face_verification_router`
#  - DB_CONFIG importado de forma robusta via caminho relativo correcto
#  - JSONResponse com datetime serializado para string (mysql.connector retorna datetime objects)

import os
import json
from datetime import datetime, date
from fastapi import APIRouter, Form, HTTPException, BackgroundTasks
from fastapi.responses import JSONResponse
from typing import Optional, Dict, Any

from .verify_identity import run_identity_verification, update_verification_result_in_db
from .utils.logger import get_identity_logger

# FIX: era `from .routes.face_routes import router as face_verification_router`
# routes/face_routes.py define `face_router`, não `router`
from .routes.face_routes import face_router as face_verification_router

logger = get_identity_logger("api")

identity_router = APIRouter(tags=["identity"])
identity_router.include_router(face_verification_router)


# ─────────────────────────────────────────────────────────────────────────────
#  HELPER: serializar tipos MySQL (datetime, date, Decimal) para JSON
# ─────────────────────────────────────────────────────────────────────────────

def _json_safe(obj: Any) -> Any:
    """Converte tipos não-serializáveis do mysql.connector para tipos JSON-safe."""
    if isinstance(obj, dict):
        return {k: _json_safe(v) for k, v in obj.items()}
    if isinstance(obj, (datetime, date)):
        return obj.isoformat()
    return obj


# ─────────────────────────────────────────────────────────────────────────────
#  ENDPOINTS
# ─────────────────────────────────────────────────────────────────────────────

@identity_router.post("/verify")
async def verify_identity_endpoint(
    background_tasks: BackgroundTasks,
    user_id: int         = Form(..., description="ID do utilizador"),
    verification_id: int = Form(..., description="ID em user_verifications"),
    id_front_path: str   = Form(..., description="Caminho absoluto da frente do BI"),
    id_back_path: str    = Form(..., description="Caminho absoluto do verso do BI"),
    video_path: str      = Form(..., description="Caminho absoluto do vídeo .webm"),
    async_mode: bool     = Form(False, description="Se True, processa em background")
):
    """
    Executa o pipeline completo de verificação de identidade.

    async_mode=True (recomendado para produção): retorna imediatamente,
    processa em background. PHP faz polling via GET /identity/status/{id}.

    async_mode=False: aguarda resultado (pode demorar 30-60s na 1.ª execução).
    """
    logger.info(f"POST /identity/verify | user={user_id} | async={async_mode}")

    if async_mode:
        background_tasks.add_task(
            _process_and_save,
            user_id, verification_id,
            id_front_path, id_back_path, video_path
        )
        return JSONResponse({
            "status":          "processing",
            "message":         "Verificação iniciada em background",
            "user_id":         user_id,
            "verification_id": verification_id
        })

    # Modo síncrono
    result = run_identity_verification(
        user_id=user_id,
        verification_id=verification_id,
        id_front_path=id_front_path,
        id_back_path=id_back_path,
        video_path=video_path
    )
    update_verification_result_in_db(verification_id, user_id, result)
    return JSONResponse(result)


@identity_router.get("/status/{verification_id}")
async def get_verification_status(verification_id: int):
    """
    Consulta o status de uma verificação na base de dados.
    Usado pelo PHP para polling em modo assíncrono.
    """
    try:
        import mysql.connector

        # FIX: caminho correcto para o config da pasta ai/
        _ai_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        import importlib.util
        _spec = importlib.util.spec_from_file_location("ai_config", os.path.join(_ai_dir, "config.py"))
        _cfg_mod = importlib.util.module_from_spec(_spec)
        _spec.loader.exec_module(_cfg_mod)
        DB_CONFIG = _cfg_mod.DB_CONFIG

        conn = mysql.connector.connect(**DB_CONFIG)
        cur  = conn.cursor(dictionary=True)
        cur.execute(
            "SELECT id, user_id, status, ai_status, ai_similarity, ai_liveness, "
            "ai_notes, created_at, updated_at "
            "FROM user_verifications WHERE id = %s",
            (verification_id,)
        )
        row = cur.fetchone()
        cur.close()
        conn.close()

        if not row:
            raise HTTPException(status_code=404, detail="Verificação não encontrada")

        # FIX: serializar datetime antes de enviar como JSON
        return JSONResponse(_json_safe(row))

    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Erro ao consultar status ver_id={verification_id}: {e}")
        raise HTTPException(status_code=500, detail=str(e))


@identity_router.get("/health")
async def health_check():
    """Verifica se o módulo de identidade está operacional."""
    checks = {}

    try:
        from deepface import DeepFace  # noqa
        checks["deepface"] = "ok"
    except ImportError:
        checks["deepface"] = "not_installed"

    try:
        import mediapipe  # noqa
        checks["mediapipe"] = "ok"
    except ImportError:
        checks["mediapipe"] = "not_installed"

    try:
        import cv2
        checks["opencv"] = cv2.__version__
    except ImportError:
        checks["opencv"] = "not_installed"

    try:
        import mysql.connector  # noqa
        checks["mysql_connector"] = "ok"
    except ImportError:
        checks["mysql_connector"] = "not_installed"

    all_ok = all(v != "not_installed" for v in checks.values())

    return JSONResponse({
        "status":  "healthy" if all_ok else "degraded",
        "checks":  checks,
        "module":  "identity_verification",
        "version": "1.1.0"
    })


# ─────────────────────────────────────────────────────────────────────────────
#  HELPER INTERNO
# ─────────────────────────────────────────────────────────────────────────────

def _process_and_save(
    user_id: int,
    verification_id: int,
    id_front_path: str,
    id_back_path: str,
    video_path: str,
    result: Optional[Dict[str, Any]] = None
) -> None:
    """Executa pipeline + guarda resultado no DB. Usado por background_tasks."""
    try:
        if result is None:
            result = run_identity_verification(
                user_id=user_id,
                verification_id=verification_id,
                id_front_path=id_front_path,
                id_back_path=id_back_path,
                video_path=video_path
            )
        update_verification_result_in_db(verification_id, user_id, result)
    except Exception as e:
        logger.error(f"Erro em _process_and_save | user={user_id} | ver={verification_id}: {e}", exc_info=True)
