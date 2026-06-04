from fastapi import FastAPI, Form
from fastapi.responses import JSONResponse
from threading import Thread
import os

from .worker import process_queue

# ── Módulo de Identidade ──────────────────────────────────────────────────────
try:
    from .identity.api_endpoint import identity_router
    _IDENTITY_LOADED = True
except Exception as _e:
    print(f"[AVISO] Módulo de identidade não carregado: {_e}")
    _IDENTITY_LOADED = False

# ── Detector NudeNet (para análise síncrona) ──────────────────────────────────
try:
    from .detector import analyze_image
    from .video_processor import analyze_video
    _DETECTOR_LOADED = True
except Exception as _e:
    print(f"[AVISO] Detector NudeNet não carregado: {_e}")
    _DETECTOR_LOADED = False

# ─────────────────────────────────────────────────────────────────────────────
app = FastAPI(
    title="Massango AI Services",
    version="2.1.0",
    description="NudeNet Content Moderation + Identity Verification"
)

# Caminho base do projecto
BASE_PATH = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# ── Endpoint NudeNet assíncrono (posts/álbuns/vídeos) — INALTERADO ────────────
@app.post("/analyze")
def analyze(file_path: str = Form(...), post_id: int = Form(...)):
    Thread(target=process_queue).start()
    return {"status": "processing"}


# ── Endpoint NudeNet SÍNCRONO (fotos de perfil) — NOVO ───────────────────────
@app.post("/analyze-sync")
def analyze_sync(file_path: str = Form(...), post_id: int = Form(0)):
    """
    Análise NudeNet síncrona — retorna o resultado imediatamente.
    Usado para fotos de perfil onde precisamos rejeitar antes de guardar.

    file_path: caminho relativo a partir da raiz do projecto
               ex: 'uploads/profiles/profile_abc123.jpg'
    post_id:   0 para fotos de perfil (não regista em media_analysis)
    """
    if not _DETECTOR_LOADED:
        # Se o detector não carregou, aprovar por omissão (não bloquear o utilizador)
        return JSONResponse({
            "is_sensitive": False,
            "score": 0.0,
            "triggered_by": None,
            "error": "Detector não disponível — aprovado por omissão"
        })

    # Resolver caminho absoluto
    abs_path = os.path.abspath(os.path.join(BASE_PATH, file_path.replace("\\", "/")))

    if not os.path.exists(abs_path):
        return JSONResponse({
            "is_sensitive": False,
            "score": 0.0,
            "triggered_by": None,
            "error": f"Ficheiro não encontrado: {file_path}"
        }, status_code=200)  # 200 para não bloquear o PHP

    try:
        # Determinar tipo de ficheiro
        ext = os.path.splitext(abs_path)[1].lower()
        video_exts = {'.mp4', '.webm', '.mov', '.avi', '.mkv'}

        if ext in video_exts:
            result = analyze_video(abs_path)
        else:
            result = analyze_image(abs_path)

        if not result:
            return JSONResponse({
                "is_sensitive": False,
                "score": 0.0,
                "triggered_by": None,
                "error": "Análise retornou resultado vazio"
            })

        is_sensitive = bool(result.get("is_sensitive", False))
        score        = float(result.get("score", 0.0))
        triggered_by = result.get("triggered_by", None)

        return JSONResponse({
            "is_sensitive": is_sensitive,
            "score":        score,
            "triggered_by": triggered_by,
            "error":        None
        })

    except Exception as e:
        print(f"[ERRO /analyze-sync] {e}")
        # Em caso de erro técnico, aprovar por omissão (não bloquear o utilizador)
        return JSONResponse({
            "is_sensitive": False,
            "score": 0.0,
            "triggered_by": None,
            "error": str(e)
        })


# ── Endpoints de identidade ───────────────────────────────────────────────────
if _IDENTITY_LOADED:
    app.include_router(identity_router, prefix="/identity")
    print("[OK] Módulo de verificação de identidade activo em /identity")

# ── Health global ─────────────────────────────────────────────────────────────
@app.get("/")
def root():
    return {
        "service":  "Massango AI",
        "nudenet":  "active" if _DETECTOR_LOADED else "unavailable",
        "identity": "active" if _IDENTITY_LOADED else "unavailable"
    }