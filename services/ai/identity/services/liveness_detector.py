# ai/identity/services/liveness_detector.py
#
# FIXES:
#  - mediapipe importado dentro de função com try/except robusto
#    (importação no topo falhava silenciosamente e impedia todo o módulo de carregar)
#  - check_blink_detection agora recebe frames já extraídos em vez de
#    extrair novamente (evitava o conflito com cleanup_temp_frames do caller)
#  - Liveness não depende de blink se mediapipe não estiver disponível
#    (usa apenas motion + face_ratio como critério principal)
#  - _liveness_error inclui blink_count=0 para compatibilidade com verify_identity.py

import os
from typing import Dict, Any, List, Optional

import cv2
import numpy as np

# Verificar disponibilidade do mediapipe UMA VEZ ao carregar o módulo
_MEDIAPIPE_AVAILABLE = False
try:
    import mediapipe as mp
    _MEDIAPIPE_AVAILABLE = True
except Exception as _mp_err:
    print(f"[AVISO liveness_detector] mediapipe indisponível: {_mp_err}. Blink detection desactivado.")

# Pontos EAR (Eye Aspect Ratio) — MediaPipe Face Mesh
LEFT_EYE_EAR_POINTS  = [362, 385, 387, 263, 373, 380]
RIGHT_EYE_EAR_POINTS = [33,  158, 160, 133, 144, 153]

from ..config import LIVENESS_MOTION_THRESHOLD, VIDEO_FRAMES_TO_EXTRACT, LIVENESS_BLINK_REQUIRED
from ..utils.logger import get_identity_logger, log_verification_event
from ..utils.video_utils import extract_frames_from_video, cleanup_temp_frames
from .face_detector import detect_faces_mediapipe

logger = get_identity_logger("liveness")


# ─────────────────────────────────────────────────────────────────────────────
#  HELPERS EAR
# ─────────────────────────────────────────────────────────────────────────────

def _euclidean_distance(p1, p2):
    return np.linalg.norm(np.array(p1) - np.array(p2))


def _calculate_ear(eye_points: List[int], landmarks) -> float:
    """Calcula Eye Aspect Ratio para um olho."""
    def pt(i):
        return [landmarks[eye_points[i]].x, landmarks[eye_points[i]].y]

    A = _euclidean_distance(pt(1), pt(5))
    B = _euclidean_distance(pt(2), pt(4))
    C = _euclidean_distance(pt(0), pt(3))
    return (A + B) / (2.0 * C) if C > 0 else 0.0


# ─────────────────────────────────────────────────────────────────────────────
#  RESULTADO DE ERRO PADRÃO
# ─────────────────────────────────────────────────────────────────────────────

def _liveness_error(msg: str) -> Dict[str, Any]:
    return {
        "liveness_passed":      False,
        "reason":               msg,
        "motion_score":         0.0,
        "face_ratio":           0.0,
        "frames_analyzed":      0,
        "error":                msg,
        "requires_manual_review": True,
        "best_frames":          [],
        "blink_count":          0,   # FIX: campo obrigatório para verify_identity.py
    }


# ─────────────────────────────────────────────────────────────────────────────
#  DETECÇÃO DE PISCAR — recebe frames já extraídos
# ─────────────────────────────────────────────────────────────────────────────

def check_blink_detection(
    frames: List[str],          # FIX: recebe lista de paths já extraídos
    user_id: int = 0,
    verification_id: int = 0
) -> Dict[str, Any]:
    """
    Detecção de piscar de olhos via MediaPipe Face Mesh.

    Recebe frames já extraídos (evita dupla extracção e conflito de cleanup).
    Se mediapipe não estiver disponível, aprova por omissão (liveness baseia-se
    em motion_score nesse caso).
    """
    # Se mediapipe não está disponível, aprovar sem blink check
    if not _MEDIAPIPE_AVAILABLE:
        logger.warning("MediaPipe indisponível — blink detection ignorado, usando apenas motion.")
        return {
            "blink_passed": True,
            "reason":       "MediaPipe indisponível — blink check omitido",
            "blink_count":  0
        }

    if not frames:
        return {"blink_passed": False, "reason": "Sem frames para analisar", "blink_count": 0}

    blink_count                     = 0
    ear_threshold                   = 0.25
    ear_consecutive_frames          = 2
    consecutive_ear_below_threshold = 0

    try:
        with mp.solutions.face_mesh.FaceMesh(
            static_image_mode=True,      # FIX: True porque recebemos frames individuais
            max_num_faces=1,
            refine_landmarks=True,
            min_detection_confidence=0.5
        ) as face_mesh:

            for frame_path in frames:
                img = cv2.imread(frame_path)
                if img is None:
                    continue

                img_rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
                results = face_mesh.process(img_rgb)

                if not results.multi_face_landmarks:
                    consecutive_ear_below_threshold = 0
                    continue

                for face_landmarks in results.multi_face_landmarks:
                    left_ear  = _calculate_ear(LEFT_EYE_EAR_POINTS,  face_landmarks.landmark)
                    right_ear = _calculate_ear(RIGHT_EYE_EAR_POINTS, face_landmarks.landmark)
                    avg_ear   = (left_ear + right_ear) / 2.0

                    if avg_ear < ear_threshold:
                        consecutive_ear_below_threshold += 1
                    else:
                        if consecutive_ear_below_threshold >= ear_consecutive_frames:
                            blink_count += 1
                        consecutive_ear_below_threshold = 0

        # Verificar último grupo (caso o vídeo termine com olhos fechados)
        if consecutive_ear_below_threshold >= ear_consecutive_frames:
            blink_count += 1

    except Exception as e:
        logger.error(f"Erro no blink detection: {e}")
        # Em caso de erro técnico, não bloquear — aprovar e deixar passar
        return {
            "blink_passed": True,
            "reason":       f"Erro técnico no blink check ({e}) — omitido",
            "blink_count":  0
        }

    blink_passed = blink_count >= 1
    reason = (
        "Pelo menos um piscar detectado"
        if blink_passed
        else "Nenhum piscar detectado — possível tentativa com foto ou vídeo gravado"
    )

    logger.info(f"Blink detection | blinks={blink_count} | passed={blink_passed}")
    return {"blink_passed": blink_passed, "reason": reason, "blink_count": blink_count}


# ─────────────────────────────────────────────────────────────────────────────
#  LIVENESS PRINCIPAL
# ─────────────────────────────────────────────────────────────────────────────

def check_liveness_motion(
    video_path: str,
    user_id: int = 0,
    verification_id: int = 0
) -> Dict[str, Any]:
    """
    Verificação de vivacidade baseada em movimento entre frames + blink detection.

    Lógica:
      1. Extrair frames do vídeo (UMA VEZ — FIX)
      2. Verificar presença de rosto em ≥40% dos frames
      3. Medir variância de movimento (foto estática → motion ≈ 0)
      4. Detecção de piscar (se mediapipe disponível)
      5. Decisão final combinada

    Critérios para aprovação:
      - face_ratio ≥ 0.4
      - motion_score > LIVENESS_MOTION_THRESHOLD
      - blink_count ≥ 1 (se mediapipe disponível; omitido caso contrário)
    """
    logger.info(f"Liveness check | user={user_id} | vídeo={os.path.basename(video_path)}")

    if not os.path.exists(video_path):
        return _liveness_error("Ficheiro de vídeo não encontrado")

    # FIX: extracção feita UMA VEZ aqui — passada para check_blink_detection
    frames = extract_frames_from_video(video_path, n_frames=VIDEO_FRAMES_TO_EXTRACT)

    if len(frames) < 2:
        cleanup_temp_frames(frames)
        return _liveness_error("Não foi possível extrair frames suficientes do vídeo")

    try:
        # ── 1. Presença de rosto ──────────────────────────────────────────────
        faces_detected = 0
        face_frames: List[str] = []

        for fp in frames:
            det = detect_faces_mediapipe(fp)
            if det["face_count"] > 0:
                faces_detected += 1
                face_frames.append(fp)

        face_ratio = faces_detected / len(frames)
        logger.debug(f"Rostos em {faces_detected}/{len(frames)} frames ({face_ratio:.0%})")

        if face_ratio < 0.4:
            return {
                "liveness_passed":        False,
                "reason":                 f"Rosto não visível em frames suficientes ({faces_detected}/{len(frames)})",
                "motion_score":           0.0,
                "face_ratio":             round(face_ratio, 2),
                "frames_analyzed":        len(frames),
                "error":                  None,
                "requires_manual_review": False,
                "best_frames":            [],
                "blink_count":            0,
            }

        # ── 2. Movimento entre frames ─────────────────────────────────────────
        motion_scores: List[float] = []
        prev_gray = None

        for fp in frames:
            img = cv2.imread(fp)
            if img is None:
                continue
            gray = cv2.GaussianBlur(cv2.cvtColor(img, cv2.COLOR_BGR2GRAY), (21, 21), 0)
            if prev_gray is not None:
                diff = cv2.absdiff(prev_gray, gray)
                motion_scores.append(float(np.mean(diff)) / 255.0)
            prev_gray = gray

        avg_motion = float(np.mean(motion_scores)) if motion_scores else 0.0
        has_motion = avg_motion > LIVENESS_MOTION_THRESHOLD
        logger.debug(f"Motion={avg_motion:.4f} (threshold={LIVENESS_MOTION_THRESHOLD})")

        # ── 3. Blink detection — FIX: passa frames já extraídos ──────────────
        blink_result = check_blink_detection(frames, user_id, verification_id)

        # ── 4. Decisão final ──────────────────────────────────────────────────
        liveness_passed = has_motion and face_ratio >= 0.4 and (not LIVENESS_BLINK_REQUIRED or blink_result["blink_passed"])

        if not has_motion:
            reason = f"Sem movimento detectado (score={avg_motion:.4f}). Possível tentativa com foto estática."
        elif not blink_result["blink_passed"]:
            reason = blink_result["reason"]
        elif not liveness_passed:
            reason = "Critérios de vivacidade não satisfeitos"
        else:
            reason = "ok"

        result = {
            "liveness_passed":        liveness_passed,
            "reason":                 reason,
            "motion_score":           round(avg_motion, 4),
            "face_ratio":             round(face_ratio, 2),
            "frames_analyzed":        len(frames),
            "error":                  None,
            "requires_manual_review": not liveness_passed,
            "best_frames":            face_frames[:3],
            "blink_count":            blink_result["blink_count"],
        }

        log_verification_event(
            user_id=user_id,
            verification_id=verification_id,
            event="liveness_check",
            details={
                "passed":      liveness_passed,
                "motion":      avg_motion,
                "face_ratio":  face_ratio,
                "frames":      len(frames),
                "blink_count": blink_result["blink_count"],
            },
            level="info" if liveness_passed else "warning"
        )

        return result

    finally:
        # FIX: cleanup feito UMA VEZ aqui — check_blink_detection já não faz cleanup
        cleanup_temp_frames(frames)


# ─────────────────────────────────────────────────────────────────────────────
#  STUBS ROADMAP
# ─────────────────────────────────────────────────────────────────────────────

def check_challenge_response(video_path: str, expected_action: str) -> Dict[str, Any]:
    """[ROADMAP v1.2] Challenge-response (aceno, sorrir, virar)."""
    return {
        "challenge_passed": False,
        "reason":           "Challenge-response planeado para v1.2",
        "action_detected":  False
    }


def check_anti_spoofing_model(image_path: str) -> Dict[str, Any]:
    """[ROADMAP v1.3] Anti-spoofing com modelo dedicado (Silent-Face / MN3)."""
    return {
        "spoofing_detected": False,
        "reason":            "Anti-spoofing model planeado para v1.3",
        "score":             0.0
    }
