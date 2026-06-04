# ai/identity/utils/image_quality.py
# Utilitários de qualidade de imagem para verificação de identidade

import cv2
import numpy as np
from typing import Tuple, Dict, Any

from ..config import MIN_FACE_WIDTH_PX, MIN_FACE_HEIGHT_PX, BLUR_THRESHOLD
from .logger import get_identity_logger

logger = get_identity_logger("image_quality")


def check_blur(image_bgr: np.ndarray) -> Tuple[bool, float]:
    """
    Verifica se uma imagem está desfocada usando a variância do Laplaciano.

    Retorna:
        (is_blurry: bool, variance: float)
        is_blurry=True significa que a imagem provavelmente está desfocada.
    """
    gray = cv2.cvtColor(image_bgr, cv2.COLOR_BGR2GRAY)
    variance = cv2.Laplacian(gray, cv2.CV_64F).var()
    is_blurry = variance < BLUR_THRESHOLD
    logger.debug(f"Blur check — variância={variance:.2f}, desfocado={is_blurry}")
    return is_blurry, round(variance, 2)


def check_brightness(image_bgr: np.ndarray) -> Tuple[bool, float]:
    """
    Verifica se a imagem tem brilho aceitável.

    Retorna:
        (is_acceptable: bool, mean_brightness: float)
    """
    gray = cv2.cvtColor(image_bgr, cv2.COLOR_BGR2GRAY)
    mean_brightness = float(np.mean(gray))
    # Rejeitar imagens muito escuras (<30) ou muito claras (>230)
    is_acceptable = 30 <= mean_brightness <= 230
    logger.debug(f"Brightness check — mean={mean_brightness:.1f}, ok={is_acceptable}")
    return is_acceptable, round(mean_brightness, 2)


def check_face_size(
    face_box: Dict[str, int],
    image_shape: Tuple[int, int]
) -> Tuple[bool, str]:
    """
    Verifica se o rosto detectado tem tamanho suficiente para análise.

    Parâmetros:
        face_box:    Dicionário com 'x', 'y', 'w', 'h' (bounding box do rosto)
        image_shape: (height, width) da imagem

    Retorna:
        (is_valid: bool, reason: str)
    """
    w = face_box.get("w", 0)
    h = face_box.get("h", 0)

    if w < MIN_FACE_WIDTH_PX or h < MIN_FACE_HEIGHT_PX:
        msg = f"Rosto demasiado pequeno: {w}x{h}px (mínimo {MIN_FACE_WIDTH_PX}x{MIN_FACE_HEIGHT_PX}px)"
        logger.debug(msg)
        return False, msg

    # Verificar se o rosto ocupa pelo menos 5% da imagem (evitar rostos minúsculos)
    img_area = image_shape[0] * image_shape[1]
    face_area = w * h
    face_ratio = face_area / img_area if img_area > 0 else 0

    if face_ratio < 0.01:
        msg = f"Rosto ocupa apenas {face_ratio*100:.1f}% da imagem (mínimo 1%)"
        logger.debug(msg)
        return False, msg

    return True, "ok"


def run_full_quality_check(image_path: str) -> Dict[str, Any]:
    """
    Executa todas as verificações de qualidade numa imagem.

    Retorna um dicionário com:
        passed: bool
        issues: list[str]
        metrics: dict
    """
    issues = []
    metrics = {}

    try:
        img = cv2.imread(image_path)
        if img is None:
            return {"passed": False, "issues": ["Não foi possível carregar a imagem"], "metrics": {}}

        # Blur
        is_blurry, blur_var = check_blur(img)
        metrics["blur_variance"] = blur_var
        if is_blurry:
            issues.append(f"Imagem desfocada (variância={blur_var})")

        # Brightness
        bright_ok, brightness = check_brightness(img)
        metrics["brightness"] = brightness
        if not bright_ok:
            issues.append(f"Iluminação inadequada (brilho={brightness:.0f})")

        passed = len(issues) == 0
        return {"passed": passed, "issues": issues, "metrics": metrics}

    except Exception as e:
        logger.error(f"Erro ao verificar qualidade de {image_path}: {e}")
        return {"passed": False, "issues": [str(e)], "metrics": {}}
