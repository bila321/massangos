# ai/identity/services/face_matcher.py
#
# FIXES:
#  - enforce_detection=False: DeepFace não rejeita imagens de documentos onde
#    o rosto é pequeno ou está numa área com fundo complexo.
#  - Tentativa com múltiplos detector_backends (mediapipe → opencv → skip)
#    para maximizar compatibilidade.
#  - Pre-crop do rosto do BI antes de passar ao DeepFace, usando o MediaPipe
#    já disponível no projecto — aumenta a taxa de sucesso em documentos.

import os
from typing import Dict, Any, List, Optional

import cv2
import numpy as np

from ..config import FACE_MODEL, DETECTOR_BACKEND, SIMILARITY_THRESHOLD
from ..utils.logger import get_identity_logger, log_verification_event

logger = get_identity_logger("face_matcher")

# Backends a tentar em sequência se o principal falhar
FALLBACK_BACKENDS = ["mediapipe", "opencv", "ssd", "skip"]


def compute_similarity_from_distance(distance: float, metric: str = "cosine") -> float:
    if metric == "cosine":
        return round(max(0.0, 1.0 - distance), 4)
    elif metric == "euclidean_l2":
        return round(max(0.0, 1.0 - (distance / 2.0)), 4)
    else:
        return round(max(0.0, 1.0 / (1.0 + distance)), 4)


def _crop_face_from_image(image_path: str) -> Optional[str]:
    """
    Usa MediaPipe para detectar e recortar o rosto da imagem.
    Retorna o caminho do crop temporário, ou None se não detectar rosto.
    Útil para imagens de documentos onde o rosto é pequeno.
    """
    try:
        import mediapipe as mp

        img = cv2.imread(image_path)
        if img is None:
            return None

        h, w = img.shape[:2]
        img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)

        with mp.solutions.face_detection.FaceDetection(
            model_selection=1, min_detection_confidence=0.3  # threshold mais baixo para documentos
        ) as detector:
            results = detector.process(img_rgb)
            if not results.detections:
                return None

            # Usar a detecção com maior confiança
            best = max(results.detections, key=lambda d: d.score[0] if d.score else 0)
            bbox = best.location_data.relative_bounding_box

            # Margem de 20% para não cortar o rosto demasiado justo
            margin = 0.20
            x1 = max(0, int((bbox.xmin - margin * bbox.width) * w))
            y1 = max(0, int((bbox.ymin - margin * bbox.height) * h))
            x2 = min(w, int((bbox.xmin + bbox.width * (1 + margin)) * w))
            y2 = min(h, int((bbox.ymin + bbox.height * (1 + margin)) * h))

            crop = img[y1:y2, x1:x2]
            if crop.size == 0:
                return None

            # Guardar crop temporário
            crop_path = image_path.replace('.jpg', '_face_crop.jpg').replace('.png', '_face_crop.jpg')
            cv2.imwrite(crop_path, crop)
            return crop_path

    except Exception as e:
        logger.debug(f"Crop de rosto falhou para {os.path.basename(image_path)}: {e}")
        return None


def compare_faces(
    image_path_1: str,
    image_path_2: str,
    user_id: int = 0,
    verification_id: int = 0
) -> Dict[str, Any]:
    """
    Compara dois rostos usando DeepFace + ArcFace.

    FIXES:
    - enforce_detection=False para não rejeitar imagens de documentos
    - Tenta múltiplos backends se o principal falhar
    - Pre-crop do rosto do BI para melhorar detecção
    """
    try:
        from deepface import DeepFace

        for path in [image_path_1, image_path_2]:
            if not os.path.exists(path):
                msg = f"Ficheiro não encontrado: {path}"
                logger.error(msg)
                return _error_match(msg)

        logger.info(
            f"Comparando rostos | user={user_id} | "
            f"BI={os.path.basename(image_path_1)} | "
            f"selfie={os.path.basename(image_path_2)}"
        )

        # FIX: tentar crop do rosto do documento primeiro
        crop_path = _crop_face_from_image(image_path_1)
        img1 = crop_path if crop_path and os.path.exists(crop_path) else image_path_1
        if crop_path:
            logger.debug(f"Usando crop do rosto do BI: {os.path.basename(crop_path)}")

        last_error = None

        # FIX: tentar múltiplos backends em sequência
        backends_to_try = [DETECTOR_BACKEND] + [b for b in FALLBACK_BACKENDS if b != DETECTOR_BACKEND]

        for backend in backends_to_try:
            try:
                result = DeepFace.verify(
                    img1_path=img1,
                    img2_path=image_path_2,
                    model_name=FACE_MODEL,
                    detector_backend=backend,
                    enforce_detection=False,  # FIX: não rejeitar se não detectar rosto
                    distance_metric="cosine"
                )

                distance       = float(result.get("distance", 1.0))
                deepface_match = bool(result.get("verified", False))
                threshold      = float(result.get("threshold", 0.68))
                similarity     = compute_similarity_from_distance(distance, "cosine")
                our_match      = similarity >= SIMILARITY_THRESHOLD

                logger.info(f"Backend={backend} | similarity={similarity:.4f} | distance={distance:.4f}")

                log_verification_event(
                    user_id=user_id,
                    verification_id=verification_id,
                    event="face_compare",
                    details={
                        "similarity": similarity,
                        "distance": distance,
                        "deepface_verified": deepface_match,
                        "our_match": our_match,
                        "model": FACE_MODEL,
                        "backend": backend,
                        "used_crop": crop_path is not None
                    },
                    level="info" if our_match else "warning"
                )

                # Limpar crop temporário
                if crop_path and os.path.exists(crop_path):
                    try:
                        os.remove(crop_path)
                    except Exception:
                        pass

                return {
                    "match": our_match,
                    "similarity_score": similarity,
                    "distance": round(distance, 4),
                    "threshold_used": SIMILARITY_THRESHOLD,
                    "deepface_threshold": threshold,
                    "model": FACE_MODEL,
                    "backend_used": backend,
                    "verified": our_match,
                    "error": None
                }

            except ValueError as ve:
                last_error = str(ve)
                logger.warning(f"ValueError com backend={backend} user={user_id}: {last_error}")
                continue  # tentar próximo backend

            except Exception as e:
                last_error = str(e)
                logger.warning(f"Erro com backend={backend} user={user_id}: {last_error}")
                continue

        # Todos os backends falharam
        if crop_path and os.path.exists(crop_path):
            try:
                os.remove(crop_path)
            except Exception:
                pass

        clean = "Rosto não detectado nas imagens. Verifique a qualidade das fotos e iluminação."
        logger.warning(f"Todos os backends falharam | user={user_id} | last_error={last_error}")
        return _error_match(clean)

    except Exception as e:
        logger.error(f"Erro inesperado em compare_faces user={user_id}: {e}", exc_info=True)
        return _error_match(f"Erro interno: {str(e)}")


def compare_faces_best_of_n(
    id_image_path: str,
    candidate_images: List[str],
    user_id: int = 0,
    verification_id: int = 0
) -> Dict[str, Any]:
    """
    Compara um documento de BI com uma lista de frames de vídeo
    e retorna o melhor resultado (maior similaridade).
    """
    if not candidate_images:
        return _error_match("Nenhuma imagem candidata fornecida")

    best_result = None
    best_score  = -1.0
    all_scores  = []

    for i, candidate in enumerate(candidate_images):
        result = compare_faces(id_image_path, candidate, user_id, verification_id)
        score  = result.get("similarity_score", 0.0)
        all_scores.append(score)

        logger.debug(f"Frame {i+1}/{len(candidate_images)} → score={score:.4f}")

        if not result.get("error") and score > best_score:
            best_score  = score
            best_result = result

    if best_result is None:
        return _error_match("Nenhum frame pôde ser analisado")

    best_result["all_frame_scores"] = all_scores
    best_result["frames_analyzed"]  = len(candidate_images)
    best_result["avg_score"]        = round(float(np.mean(all_scores)), 4)

    logger.info(
        f"best_of_n | user={user_id} | "
        f"best={best_score:.4f} | avg={best_result['avg_score']:.4f} | "
        f"frames={len(candidate_images)}"
    )

    return best_result


def _error_match(msg: str) -> Dict[str, Any]:
    return {
        "match": False,
        "similarity_score": 0.0,
        "distance": 1.0,
        "threshold_used": SIMILARITY_THRESHOLD,
        "deepface_threshold": None,
        "model": FACE_MODEL,
        "verified": False,
        "error": msg
    }