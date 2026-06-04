# ai/identity/face_verify.py
#
# FIXES APLICADOS:
#  - BUG CRÍTICO: face_verification_service.py importava verify_faces,
#    extract_face_from_document e process_video_for_selfie deste ficheiro,
#    mas nenhuma dessas funções existia aqui → ImportError na inicialização.
#
#  - Adicionadas as 3 funções em falta, implementadas sobre os serviços existentes:
#      verify_faces()                → usa DeepFace.verify (face_matcher logic)
#      extract_face_from_document()  → usa face_detector.validate_single_face + crop
#      process_video_for_selfie()    → usa video_utils.extract_frames_from_video
#
#  - Mantida compatibilidade com face_router e FaceVerificationService exports.

import os
from typing import Optional

import cv2

from .routes.face_routes import face_router
from .services.face_service import FaceVerificationService
from .services.face_detector import detect_faces_mediapipe
from .utils.video_utils import extract_frames_from_video, cleanup_temp_frames
from .config import FACE_MODEL, DETECTOR_BACKEND, SIMILARITY_THRESHOLD, TEMP_DIR
from .utils.logger import get_identity_logger

logger = get_identity_logger("face_verify")

__all__ = [
    "face_router",
    "FaceVerificationService",
    "verify_faces",
    "extract_face_from_document",
    "process_video_for_selfie",
]


# ─────────────────────────────────────────────────────────────────────────────
#  verify_faces
# ─────────────────────────────────────────────────────────────────────────────

def verify_faces(img1_path: str, img2_path: str) -> dict:
    """
    Compara dois rostos usando DeepFace + ArcFace.

    Retorna:
        {
            "success": bool,
            "match": bool,
            "score": float,      # 0.0 – 1.0 (similaridade)
            "status": str,       # "approved" | "manual_review" | "rejected"
            "error": str | None
        }
    """
    try:
        from deepface import DeepFace

        for path in [img1_path, img2_path]:
            if not os.path.exists(path):
                return {"success": False, "match": False, "score": 0.0,
                        "status": "rejected", "error": f"Ficheiro não encontrado: {path}"}

        result   = DeepFace.verify(
            img1_path=img1_path,
            img2_path=img2_path,
            model_name=FACE_MODEL,
            detector_backend=DETECTOR_BACKEND,
            enforce_detection=True,
            distance_metric="cosine"
        )

        distance   = float(result.get("distance", 1.0))
        similarity = round(max(0.0, 1.0 - distance), 4)

        if similarity >= SIMILARITY_THRESHOLD:
            status = "approved"
            match  = True
        elif similarity >= SIMILARITY_THRESHOLD * 0.75:
            status = "manual_review"
            match  = True
        else:
            status = "rejected"
            match  = False

        logger.info(f"verify_faces | similarity={similarity:.4f} | status={status}")
        return {"success": True, "match": match, "score": similarity, "status": status, "error": None}

    except ValueError as e:
        msg = str(e)
        clean = "Rosto não detectado numa das imagens." if "Face could not be detected" in msg else msg
        logger.warning(f"verify_faces ValueError: {msg}")
        return {"success": False, "match": False, "score": 0.0, "status": "rejected", "error": clean}

    except Exception as e:
        logger.error(f"verify_faces erro inesperado: {e}", exc_info=True)
        return {"success": False, "match": False, "score": 0.0, "status": "rejected", "error": str(e)}


# ─────────────────────────────────────────────────────────────────────────────
#  extract_face_from_document
# ─────────────────────────────────────────────────────────────────────────────

def extract_face_from_document(document_path: str, output_path: str) -> Optional[str]:
    """
    Extrai o rosto de uma imagem de documento (BI/passaporte) e guarda em output_path.

    Retorna o output_path se sucesso, None se falhar.
    """
    try:
        detection = detect_faces_mediapipe(document_path)

        if not detection["success"] or detection["face_count"] == 0:
            logger.warning(f"extract_face_from_document: nenhum rosto em {document_path}")
            return None

        face = detection["faces"][0]
        img  = cv2.imread(document_path)
        if img is None:
            return None

        # Crop com margem de 20%
        h, w    = img.shape[:2]
        margin  = 0.20
        x1 = max(0, int(face["x"] - face["w"] * margin))
        y1 = max(0, int(face["y"] - face["h"] * margin))
        x2 = min(w, int(face["x"] + face["w"] * (1 + margin)))
        y2 = min(h, int(face["y"] + face["h"] * (1 + margin)))

        face_crop = img[y1:y2, x1:x2]
        if face_crop.size == 0:
            return None

        os.makedirs(os.path.dirname(output_path) if os.path.dirname(output_path) else TEMP_DIR, exist_ok=True)
        cv2.imwrite(output_path, face_crop)
        logger.info(f"extract_face_from_document: rosto extraído → {output_path}")
        return output_path

    except Exception as e:
        logger.error(f"extract_face_from_document erro: {e}", exc_info=True)
        return None


# ─────────────────────────────────────────────────────────────────────────────
#  process_video_for_selfie
# ─────────────────────────────────────────────────────────────────────────────

def process_video_for_selfie(video_path: str, output_path: str) -> Optional[str]:
    """
    Extrai frames do vídeo de selfie, encontra o frame com melhor rosto
    e guarda em output_path.

    Retorna output_path se sucesso, None se falhar.
    """
    frames = []
    try:
        frames = extract_frames_from_video(video_path, n_frames=8)

        if not frames:
            logger.warning(f"process_video_for_selfie: sem frames em {video_path}")
            return None

        # Escolher o frame com rosto mais confiante
        best_frame    = None
        best_conf     = 0.0

        for frame_path in frames:
            det = detect_faces_mediapipe(frame_path)
            if det["success"] and det["face_count"] == 1:
                conf = det["faces"][0].get("confidence", 0.5)
                if conf > best_conf:
                    best_conf  = conf
                    best_frame = frame_path

        if best_frame is None:
            logger.warning("process_video_for_selfie: nenhum frame com rosto único detectado")
            return None

        # Copiar melhor frame para output_path
        import shutil
        os.makedirs(os.path.dirname(output_path) if os.path.dirname(output_path) else TEMP_DIR, exist_ok=True)
        shutil.copy2(best_frame, output_path)
        logger.info(f"process_video_for_selfie: melhor frame → {output_path} (conf={best_conf:.3f})")
        return output_path

    except Exception as e:
        logger.error(f"process_video_for_selfie erro: {e}", exc_info=True)
        return None

    finally:
        cleanup_temp_frames(frames)
