# ai/identity/utils/video_utils.py
#
# FIX CRÍTICO:
#  - Vídeos .webm gravados pelo browser reportam fps=1000.0 (valor falso).
#    Com frame_step = fps * interval = 1000 * 0.5 = 500, só extrai 1 frame
#    num vídeo de 10s com ~300 frames reais.
#  - Solução: se fps > 60 (claramente falso), ignorar e calcular step
#    directamente a partir de total_frames / n_frames desejados.
#  - Fallback adicional: se total_frames também for suspeito (>10000),
#    ler frame a frame e amostrar pelos primeiros n_frames*step frames.

import cv2
import os
from typing import List, Optional

from ..config import VIDEO_FRAMES_TO_EXTRACT, VIDEO_FRAME_INTERVAL_SEC, TEMP_DIR
from .logger import get_identity_logger

logger = get_identity_logger("video_utils")

# FPS máximo considerado fiável — acima disso é valor falso do webm
MAX_RELIABLE_FPS = 60.0


def extract_frames_from_video(
    video_path: str,
    n_frames: int = VIDEO_FRAMES_TO_EXTRACT,
    interval_sec: float = VIDEO_FRAME_INTERVAL_SEC,
    output_dir: Optional[str] = None
) -> List[str]:
    """
    Extrai frames de um vídeo em intervalos regulares.

    FIX: Vídeos .webm do browser reportam fps=1000 (falso).
    Se fps > 60, calcula step a partir de total_frames directamente.

    Parâmetros:
        video_path:   Caminho para o ficheiro .webm / .mp4
        n_frames:     Número máximo de frames a extrair
        interval_sec: Intervalo em segundos entre frames (ignorado se fps falso)
        output_dir:   Diretório de saída (usa TEMP_DIR se None)

    Retorna:
        Lista de caminhos para os frames extraídos (.jpg)
    """
    out_dir = output_dir or TEMP_DIR
    os.makedirs(out_dir, exist_ok=True)

    if not os.path.exists(video_path):
        logger.error(f"Vídeo não encontrado: {video_path}")
        return []

    cap = cv2.VideoCapture(video_path)
    if not cap.isOpened():
        logger.error(f"Não foi possível abrir o vídeo: {video_path}")
        return []

    fps          = cap.get(cv2.CAP_PROP_FPS) or 25.0
    total_frames = int(cap.get(cv2.CAP_PROP_FRAME_COUNT))

    # FIX: fps falso em webm (browser reporta 1000.0)
    if fps > MAX_RELIABLE_FPS or total_frames > 5000:
        # Calcular step directamente: distribuir n_frames pelo total
        if total_frames > 0:
            frame_step = max(1, total_frames // n_frames)
        else:
            frame_step = 30  # fallback conservador

        logger.warning(
            f"FPS suspeito ({fps:.1f}) ou total_frames suspeito ({total_frames}) — "
            f"usando step fixo={frame_step} baseado em total_frames"
        )
    else:
        frame_step = max(1, int(fps * interval_sec))

    logger.info(
        f"Vídeo: fps={fps:.1f}, total_frames={total_frames}, "
        f"frame_step={frame_step}, alvo={n_frames} frames"
    )

    extracted: List[str] = []
    frame_index = 0

    while len(extracted) < n_frames:
        cap.set(cv2.CAP_PROP_POS_FRAMES, frame_index)
        ret, frame = cap.read()

        if not ret:
            # FIX: se seek falhou, tentar ler sequencialmente
            logger.debug(f"Seek falhou em frame {frame_index}, a tentar leitura sequencial")
            break

        frame_path = os.path.join(out_dir, f"frame_{os.getpid()}_{frame_index}.jpg")
        if cv2.imwrite(frame_path, frame):
            extracted.append(frame_path)
            logger.debug(f"Frame extraído: {frame_path}")
        else:
            logger.warning(f"Falha ao escrever frame: {frame_path}")

        frame_index += frame_step
        if total_frames > 0 and frame_index >= total_frames:
            break

    cap.release()

    # FIX: se seek não funcionou (webm sem índice), ler sequencialmente
    if len(extracted) < 2 and total_frames > 0:
        logger.warning("Seek falhou — a tentar extracção sequencial...")
        extracted = _extract_sequential(video_path, n_frames, out_dir)

    logger.info(f"Total de frames extraídos: {len(extracted)}")
    return extracted


def _extract_sequential(video_path: str, n_frames: int, out_dir: str) -> List[str]:
    """
    Extracção sequencial para webm sem índice de frames.
    Lê todos os frames e guarda 1 a cada N.
    """
    cap = cv2.VideoCapture(video_path)
    if not cap.isOpened():
        return []

    all_frames: List[str] = []
    idx = 0

    while True:
        ret, frame = cap.read()
        if not ret:
            break

        frame_path = os.path.join(out_dir, f"frame_{os.getpid()}_seq_{idx}.jpg")
        if cv2.imwrite(frame_path, frame):
            all_frames.append(frame_path)
        idx += 1

    cap.release()

    if not all_frames:
        return []

    # Amostrar n_frames distribuídos pelo total
    if len(all_frames) <= n_frames:
        return all_frames

    step = len(all_frames) // n_frames
    sampled = all_frames[::step][:n_frames]

    # Apagar frames não usados
    used = set(sampled)
    for fp in all_frames:
        if fp not in used:
            try:
                os.remove(fp)
            except Exception:
                pass

    logger.info(f"Extracção sequencial: {len(all_frames)} frames lidos → {len(sampled)} amostrados")
    return sampled


def cleanup_temp_frames(frame_paths: List[str]) -> None:
    """Remove ficheiros temporários de frames."""
    for path in frame_paths:
        try:
            if os.path.exists(path):
                os.remove(path)
        except Exception as e:
            logger.warning(f"Não foi possível remover frame temp {path}: {e}")
