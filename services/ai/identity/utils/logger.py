# ai/identity/utils/logger.py
# Sistema de logs centralizado para o módulo de identidade

import logging
import os
from datetime import datetime
from typing import Optional

from ..config import IDENTITY_LOGS_DIR


def get_identity_logger(name: str = "identity") -> logging.Logger:
    """
    Retorna um logger configurado para o módulo de identidade.
    Grava em ficheiro diário + console.
    """
    logger = logging.getLogger(f"massango.identity.{name}")

    if logger.handlers:
        return logger  # Já configurado

    logger.setLevel(logging.DEBUG)

    # ── Formatter ──────────────────────────────────────────────────
    fmt = logging.Formatter(
        "[%(asctime)s] [%(levelname)-8s] [%(name)s] %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S"
    )

    # ── Handler: Ficheiro diário ────────────────────────────────────
    today = datetime.now().strftime("%Y-%m-%d")
    log_file = os.path.join(IDENTITY_LOGS_DIR, f"identity_{today}.log")

    fh = logging.FileHandler(log_file, encoding="utf-8")
    fh.setLevel(logging.DEBUG)
    fh.setFormatter(fmt)
    logger.addHandler(fh)

    # ── Handler: Console ───────────────────────────────────────────
    ch = logging.StreamHandler()
    ch.setLevel(logging.INFO)
    ch.setFormatter(fmt)
    logger.addHandler(ch)

    return logger


def log_verification_event(
    user_id: int,
    verification_id: int,
    event: str,
    details: Optional[dict] = None,
    level: str = "info"
) -> None:
    """
    Regista um evento de verificação de forma estruturada.

    Parâmetros:
        user_id:         ID do utilizador
        verification_id: ID do registo na tabela user_verifications
        event:           Nome do evento (ex: 'face_match', 'liveness_fail')
        details:         Dicionário de dados adicionais
        level:           Nível de log ('info', 'warning', 'error', 'debug')
    """
    logger = get_identity_logger("events")
    msg = (
        f"USER={user_id} | VER_ID={verification_id} | "
        f"EVENT={event} | DETAILS={details or {}}"
    )
    getattr(logger, level)(msg)
