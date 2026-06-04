# ai/identity/services/face_verification_service.py
#
# FIXES APLICADOS:
#  - BUG: importava verify_faces, extract_face_from_document, process_video_for_selfie
#    de face_verify.py, mas essas funções não existiam → ImportError.
#    Agora essas funções existem em face_verify.py (corrigido) e a importação funciona.
#  - Import path corrigido para relativo correcto (..face_verify em vez de ..face_verify)

import os
from typing import Dict, Any

from ..face_verify import verify_faces, extract_face_from_document, process_video_for_selfie
from ..utils.logger import get_identity_logger
from ..config import TEMP_DIR

logger = get_identity_logger("face_verification_service")


async def perform_face_verification(
    selfie_video_path: str,
    document_photo_path: str
) -> Dict[str, Any]:
    """
    Orquestra o processo de verificação facial.

    Args:
        selfie_video_path:    Caminho para o vídeo da selfie do utilizador.
        document_photo_path:  Caminho para a foto do documento (BI/passaporte).

    Returns:
        {
            "success": bool,
            "match": bool,
            "score": float,
            "status": "approved" | "manual_review" | "rejected",
            "error": str | None
        }
    """
    logger.info(
        f"Iniciando verificação facial | selfie={selfie_video_path} | doc={document_photo_path}"
    )

    processed_selfie_path      = None
    extracted_document_face_path = None

    try:
        # 1. Processar vídeo da selfie para extrair frame com rosto
        selfie_frame_filename  = f"selfie_frame_{os.getpid()}_{os.path.basename(selfie_video_path)}.jpg"
        processed_selfie_path  = os.path.join(TEMP_DIR, selfie_frame_filename)

        result_selfie = process_video_for_selfie(selfie_video_path, processed_selfie_path)

        if not result_selfie:
            logger.error("Falha ao processar vídeo da selfie — nenhum rosto detectado.")
            return {
                "success": False,
                "match":   False,
                "score":   0.0,
                "status":  "rejected",
                "error":   "Não foi possível detectar rosto no vídeo da selfie."
            }

        # 2. Extrair rosto do documento
        doc_face_filename          = f"document_face_{os.getpid()}_{os.path.basename(document_photo_path)}.jpg"
        extracted_document_face_path = os.path.join(TEMP_DIR, doc_face_filename)

        result_doc = extract_face_from_document(document_photo_path, extracted_document_face_path)

        if not result_doc:
            logger.error("Falha ao extrair rosto do documento.")
            return {
                "success": False,
                "match":   False,
                "score":   0.0,
                "status":  "rejected",
                "error":   "Não foi possível detectar rosto no documento."
            }

        # 3. Comparar rosto da selfie com rosto do documento
        verification_result = verify_faces(
            img1_path=processed_selfie_path,
            img2_path=extracted_document_face_path
        )

        logger.info(f"Verificação facial concluída: {verification_result}")
        return verification_result

    finally:
        # Limpeza garantida independentemente de erros
        for path in [processed_selfie_path, extracted_document_face_path]:
            if path and os.path.exists(path):
                try:
                    os.remove(path)
                except Exception:
                    pass
