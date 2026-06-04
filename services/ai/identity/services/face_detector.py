# ai/identity/services/face_detector.py
# Serviço de detecção de rostos usando MediaPipe e DeepFace

import os
from typing import Dict, Any, List, Optional, Tuple

import cv2
import numpy as np

from ..config import DETECTOR_BACKEND, MIN_FACE_WIDTH_PX, MIN_FACE_HEIGHT_PX
from ..utils.logger import get_identity_logger

logger = get_identity_logger("face_detector")


def detect_faces_mediapipe(image_path: str) -> Dict[str, Any]:
    """
    Detecta rostos numa imagem usando MediaPipe Face Detection.
    Mais leve e rápido que Dlib — ideal para Windows/XAMPP.

    Retorna:
        {
            "success": bool,
            "face_count": int,
            "faces": [{"x": int, "y": int, "w": int, "h": int, "confidence": float}, ...],
            "error": str | None
        }
    """
    try:
        import mediapipe as mp

        img = cv2.imread(image_path)
        if img is None:
            return _error_result("Não foi possível carregar a imagem")

        h, w = img.shape[:2]
        img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)

        mp_face = mp.solutions.face_detection
        faces_found: List[Dict] = []

        with mp_face.FaceDetection(model_selection=1, min_detection_confidence=0.5) as detector:
            results = detector.process(img_rgb)

            if results.detections:
                for detection in results.detections:
                    bbox = detection.location_data.relative_bounding_box
                    x = max(0, int(bbox.xmin * w))
                    y = max(0, int(bbox.ymin * h))
                    fw = int(bbox.width * w)
                    fh = int(bbox.height * h)
                    confidence = detection.score[0] if detection.score else 0.0

                    faces_found.append({
                        "x": x, "y": y, "w": fw, "h": fh,
                        "confidence": round(float(confidence), 4)
                    })

        logger.info(f"MediaPipe detectou {len(faces_found)} rosto(s) em {os.path.basename(image_path)}")
        return {
            "success": True,
            "face_count": len(faces_found),
            "faces": faces_found,
            "error": None
        }

    except ImportError:
        logger.warning("MediaPipe não instalado, usando fallback OpenCV Haar")
        return detect_faces_opencv_haar(image_path)
    except Exception as e:
        logger.error(f"Erro MediaPipe em {image_path}: {e}")
        return _error_result(str(e))


def detect_faces_opencv_haar(image_path: str) -> Dict[str, Any]:
    """
    Fallback: detecção de rostos com OpenCV Haar Cascades.
    Não precisa de dependências externas além do opencv-python.
    """
    try:
        cascade_path = cv2.data.haarcascades + "haarcascade_frontalface_default.xml"
        face_cascade = cv2.CascadeClassifier(cascade_path)

        img = cv2.imread(image_path)
        if img is None:
            return _error_result("Não foi possível carregar a imagem (Haar)")

        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        detections = face_cascade.detectMultiScale(
            gray, scaleFactor=1.1, minNeighbors=5,
            minSize=(MIN_FACE_WIDTH_PX, MIN_FACE_HEIGHT_PX)
        )

        faces_found = []
        if len(detections) > 0:
            for (x, y, w, h) in detections:
                faces_found.append({"x": int(x), "y": int(y), "w": int(w), "h": int(h), "confidence": 0.75})

        logger.info(f"Haar detectou {len(faces_found)} rosto(s)")
        return {
            "success": True,
            "face_count": len(faces_found),
            "faces": faces_found,
            "error": None
        }
    except Exception as e:
        logger.error(f"Erro Haar em {image_path}: {e}")
        return _error_result(str(e))


def validate_single_face(image_path: str) -> Tuple[bool, str, Optional[Dict]]:
    """
    Valida que a imagem contém exactamente um rosto legível.

    Retorna:
        (is_valid: bool, reason: str, face_info: dict | None)
    """
    result = detect_faces_mediapipe(image_path)

    if not result["success"]:
        return False, f"Erro de detecção: {result['error']}", None

    if result["face_count"] == 0:
        return False, "Nenhum rosto detectado na imagem", None

    if result["face_count"] > 1:
        return False, f"Múltiplos rostos detectados ({result['face_count']}). Use uma imagem com apenas uma pessoa.", None

    face = result["faces"][0]

    if face["w"] < MIN_FACE_WIDTH_PX or face["h"] < MIN_FACE_HEIGHT_PX:
        return False, f"Rosto demasiado pequeno ({face['w']}x{face['h']}px). Aproxime-se da câmara.", None

    return True, "ok", face


def _error_result(msg: str) -> Dict[str, Any]:
    return {"success": False, "face_count": 0, "faces": [], "error": msg}
