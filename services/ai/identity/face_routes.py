
import os
from fastapi import APIRouter, Form, HTTPException, UploadFile, File
from fastapi.responses import JSONResponse
from typing import Dict, Any

from .services.face_verification_service import perform_face_verification
from .utils.logger import get_identity_logger
from .config import TEMP_DIR

logger = get_identity_logger("face_routes")

face_router = APIRouter(tags=["face_verification"])

@face_router.post("/verify-face")
async def verify_face_endpoint(
    selfie_file: UploadFile = File(..., description="Arquivo de imagem ou vídeo da selfie do usuário"),
    document_file: UploadFile = File(..., description="Arquivo de imagem do documento (BI/passaporte)")
) -> Dict[str, Any]:
    """
    Endpoint para verificação facial de identidade.
    Recebe uma selfie (imagem ou vídeo) e uma foto de documento,
    compara os rostos e retorna um score de similaridade e status.
    """
    logger.info("Recebida requisição para POST /verify-face")

    # Salvar arquivos temporariamente
    selfie_path = os.path.join(TEMP_DIR, selfie_file.filename)
    document_path = os.path.join(TEMP_DIR, document_file.filename)

    try:
        with open(selfie_path, "wb") as f:
            f.write(await selfie_file.read())
        with open(document_path, "wb") as f:
            f.write(await document_file.read())

        result = await perform_face_verification(selfie_path, document_path)
        return JSONResponse(result)

    except Exception as e:
        logger.error(f"Erro no endpoint /verify-face: {e}")
        raise HTTPException(status_code=500, detail=f"Erro interno do servidor: {e}")
    finally:
        # Limpar arquivos temporários
        if os.path.exists(selfie_path): os.remove(selfie_path)
        if os.path.exists(document_path): os.remove(document_path)
