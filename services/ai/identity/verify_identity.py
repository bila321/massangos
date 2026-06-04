# ai/identity/verify_identity.py
#
# FIXES:
#  - users.verification_status usa enum('none','pending','approved','rejected')
#    O código anterior gravava 'ai_approved'/'ai_rejected' — valores inválidos
#    no enum, MariaDB silenciava e deixava o utilizador em 'pending' para sempre.
#  - Quando IA rejeita com similarity < 40%, users.verification_status = 'rejected'
#    imediatamente (não fica em 'pending').
#  - Quando IA aprova, users.verification_status = 'approved' (aguarda admin).
#  - manual_review mantém 'pending' correctamente.
#  - user_verifications.status também actualizado correctamente.

import os
import time
from typing import Dict, Any, Optional

from .config import (
    VERIFICATIONS_DIR, SIMILARITY_THRESHOLD,
    FACE_MODEL, FRAUD_SCORE_THRESHOLD
)
from .services.face_detector import validate_single_face
from .services.face_matcher import compare_faces_best_of_n
from .services.liveness_detector import check_liveness_motion
from .utils.image_quality import run_full_quality_check
from .utils.video_utils import extract_frames_from_video, cleanup_temp_frames
from .utils.logger import get_identity_logger, log_verification_event

logger = get_identity_logger("orchestrator")

# Threshold abaixo do qual a rejeição é imediata (sem pending)
HARD_REJECT_THRESHOLD = 0.40


def _build_result(
    status: str,
    reason: str,
    similarity_score: float = 0.0,
    liveness_passed: bool = False,
    details: Optional[Dict] = None
) -> Dict[str, Any]:
    return {
        "status": status,
        "reason": reason,
        "similarity_score": round(similarity_score, 4),
        "liveness_passed": liveness_passed,
        "model_used": FACE_MODEL,
        "requires_manual_review": status == "manual_review",
        "details": details or {}
    }


def run_identity_verification(
    user_id: int,
    verification_id: int,
    id_front_path: str,
    id_back_path: str,
    video_path: str
) -> Dict[str, Any]:

    start = time.time()
    logger.info(f"=== INÍCIO DA VERIFICAÇÃO | user={user_id} | ver_id={verification_id} ===")
    log_verification_event(user_id, verification_id, "verification_started")

    # ── PASSO 1: Ficheiros existem ───────────────────────────────────────────
    for label, path in [("id_front", id_front_path), ("id_back", id_back_path), ("video", video_path)]:
        if not os.path.exists(path):
            msg = f"Ficheiro {label} não encontrado: {path}"
            logger.error(msg)
            return _build_result("error", msg)

    # ── PASSO 2: Qualidade das imagens ───────────────────────────────────────
    logger.info("PASSO 2 — Verificação de qualidade das imagens...")
    front_quality = run_full_quality_check(id_front_path)
    if not front_quality["passed"]:
        issues = "; ".join(front_quality["issues"])
        logger.warning(f"Qualidade insuficiente na frente do BI: {issues}")
        log_verification_event(user_id, verification_id, "low_quality_id_front",
                               {"issues": front_quality["issues"]}, "warning")

    # ── PASSO 3: Rosto no BI ────────────────────────────────────────────────
    logger.info("PASSO 3 — Validação de rosto no documento de identidade...")
    face_valid, face_reason, face_info = validate_single_face(id_front_path)
    if not face_valid:
        msg = f"BI frente: {face_reason}"
        logger.warning(msg)
        log_verification_event(user_id, verification_id, "id_face_invalid",
                               {"reason": face_reason}, "warning")
        return _build_result("manual_review", msg, details={"step": "id_face_validation"})

    logger.info(f"Rosto no BI detectado: {face_info}")

    # ── PASSO 4: Liveness ───────────────────────────────────────────────────
    logger.info("PASSO 4 — Análise de liveness no vídeo...")
    liveness_result = check_liveness_motion(video_path, user_id, verification_id)

    if not liveness_result["liveness_passed"]:
        msg = f"Liveness falhou: {liveness_result['reason']}"
        logger.warning(msg)
        if liveness_result.get("error"):
            return _build_result("manual_review", msg,
                                 details={"step": "liveness", **liveness_result})
        return _build_result(
            "rejected",
            "Prova de vida não passou: " + liveness_result["reason"],
            liveness_passed=False,
            details={"step": "liveness", **liveness_result}
        )

    logger.info(f"Liveness OK — motion_score={liveness_result['motion_score']}")

    # ── PASSO 5: Extrair frames ──────────────────────────────────────────────
    logger.info("PASSO 5 — Extracção de frames do vídeo para comparação facial...")
    temp_frames = extract_frames_from_video(video_path, n_frames=5)
    if not temp_frames:
        msg = "Não foi possível extrair frames do vídeo"
        logger.error(msg)
        return _build_result("manual_review", msg, liveness_passed=True,
                             details={"step": "frame_extraction"})

    # ── PASSO 6: Comparação facial ───────────────────────────────────────────
    logger.info("PASSO 6 — Comparação facial (ArcFace)...")
    try:
        match_result = compare_faces_best_of_n(
            id_image_path=id_front_path,
            candidate_images=temp_frames,
            user_id=user_id,
            verification_id=verification_id
        )
    finally:
        cleanup_temp_frames(temp_frames)

    similarity  = match_result.get("similarity_score", 0.0)
    match_error = match_result.get("error")

    if match_error:
        logger.warning(f"Erro na comparação facial: {match_error}")
        return _build_result(
            "manual_review",
            f"Erro na comparação facial: {match_error}",
            similarity_score=similarity,
            liveness_passed=True,
            details={"step": "face_match", **match_result}
        )

    # ── PASSO 7: Decisão final ───────────────────────────────────────────────
    logger.info(f"PASSO 7 — Decisão final | similarity={similarity:.4f} | threshold={SIMILARITY_THRESHOLD}")

    elapsed = round(time.time() - start, 2)
    details = {
        "similarity_score": similarity,
        "liveness_motion": liveness_result["motion_score"],
        "face_ratio": liveness_result["face_ratio"],
        "blink_count": liveness_result["blink_count"],
        "frames_analyzed": match_result.get("frames_analyzed", 0),
        "elapsed_sec": elapsed,
        **match_result
    }

    if similarity >= SIMILARITY_THRESHOLD:
        log_verification_event(user_id, verification_id, "auto_approved",
                               {"similarity": similarity}, "info")
        logger.info(f"✅ APROVADO | similarity={similarity:.4f} | elapsed={elapsed}s")
        return _build_result(
            "approved",
            f"Verificação aprovada automaticamente (similaridade={similarity:.1%})",
            similarity_score=similarity,
            liveness_passed=True,
            details=details
        )

    elif similarity >= SIMILARITY_THRESHOLD * 0.75:
        # Score limítrofe — revisão manual
        log_verification_event(user_id, verification_id, "borderline_manual_review",
                               {"similarity": similarity}, "warning")
        logger.warning(f"⚠️ REVISÃO MANUAL | similarity={similarity:.4f}")
        return _build_result(
            "manual_review",
            f"Score limítrofe ({similarity:.1%}) — requer revisão manual",
            similarity_score=similarity,
            liveness_passed=True,
            details=details
        )

    else:
        # FIX: Rejeição clara — similarity abaixo de 40% não activa pending
        log_verification_event(user_id, verification_id, "auto_rejected",
                               {"similarity": similarity}, "warning")
        logger.warning(f"❌ REJEITADO | similarity={similarity:.4f} | elapsed={elapsed}s")
        return _build_result(
            "rejected",
            f"Rosto no documento não corresponde ao vídeo (similaridade={similarity:.1%})",
            similarity_score=similarity,
            liveness_passed=True,
            details=details
        )


def update_verification_result_in_db(
    verification_id: int,
    user_id: int,
    result: Dict[str, Any],
    db_config: Optional[Dict] = None
) -> bool:
    """
    Actualiza o resultado na base de dados.

    FIX: users.verification_status usa enum('none','pending','approved','rejected')
    Mapeamento correcto:
        'approved'      → user_verifications.ai_status = 'ai_approved'
                        → user_verifications.status    = 'pending'  (aguarda admin)
                        → users.verification_status    = 'pending'  (aguarda admin)
        'rejected'      → user_verifications.ai_status = 'ai_rejected'
                        → user_verifications.status    = 'rejected'
                        → users.verification_status    = 'rejected'  ← FIX (era 'ai_rejected')
        'manual_review' → user_verifications.ai_status = 'manual_review'
                        → user_verifications.status    = 'pending'
                        → users.verification_status    = 'pending'
        'error'         → user_verifications.ai_status = 'ai_error'
                        → users.verification_status    = 'pending'  (não penalizar por erro técnico)
    """
    try:
        import mysql.connector
        import json

        if db_config is None:
            from ..config import DB_CONFIG
            cfg = DB_CONFIG
        else:
            cfg = db_config

        # Mapear status IA → ai_status na tabela user_verifications
        ai_status_map = {
            "approved":      "ai_approved",
            "rejected":      "ai_rejected",
            "manual_review": "manual_review",
            "error":         "ai_error"
        }
        db_ai_status = ai_status_map.get(result["status"], "ai_error")

        # FIX: Mapear status IA → status na tabela user_verifications
        verif_status_map = {
            "approved":      "pending",   # aguarda confirmação admin
            "rejected":      "rejected",  # rejeição definitiva
            "manual_review": "pending",   # aguarda revisão admin
            "error":         "pending"    # erro técnico — não penalizar
        }
        db_verif_status = verif_status_map.get(result["status"], "pending")

        # FIX: Mapear status IA → users.verification_status (enum correcto)
        user_status_map = {
            "approved":      "pending",   # aprovado pela IA, aguarda admin
            "rejected":      "rejected",  # FIX: era 'ai_rejected' — valor inválido no enum
            "manual_review": "pending",
            "error":         "pending"
        }
        db_user_status = user_status_map.get(result["status"], "pending")

        ai_notes = (
            f"[AI {FACE_MODEL}] {result['reason']} | "
            f"Score: {result['similarity_score']:.1%} | "
            f"Liveness: {'✓' if result['liveness_passed'] else '✗'} | "
            f"Blinks: {result.get('details', {}).get('blink_count', 0)}"
        )

        conn = mysql.connector.connect(**cfg)
        cur  = conn.cursor()

        # Actualizar user_verifications
        try:
            cur.execute("""
                UPDATE user_verifications
                SET ai_status       = %s,
                    status          = %s,
                    ai_similarity   = %s,
                    ai_liveness     = %s,
                    ai_notes        = %s,
                    ai_result_json  = %s,
                    ai_processed_at = NOW()
                WHERE id = %s
            """, (
                db_ai_status,
                db_verif_status,
                result["similarity_score"],
                1 if result["liveness_passed"] else 0,
                ai_notes,
                json.dumps(result.get("details", {})),
                verification_id
            ))
        except mysql.connector.Error as e:
            logger.warning(f"Erro ao actualizar user_verifications (fallback): {e}")
            cur.execute("""
                UPDATE user_verifications
                SET admin_notes = %s, status = %s
                WHERE id = %s
            """, (ai_notes, db_verif_status, verification_id))

        # FIX: Actualizar users.verification_status com valor válido no enum
        cur.execute("""
            UPDATE users
            SET verification_status = %s
            WHERE id = %s
        """, (db_user_status, user_id))

        conn.commit()
        cur.close()
        conn.close()

        logger.info(
            f"DB actualizado | ver_id={verification_id} | "
            f"ai_status={db_ai_status} | verif_status={db_verif_status} | "
            f"user_status={db_user_status}"
        )
        return True

    except Exception as e:
        logger.error(f"Erro ao actualizar DB | ver_id={verification_id}: {e}")
        return False