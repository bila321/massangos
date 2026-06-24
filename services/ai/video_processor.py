# IMPORTANTE: definir antes do "import cv2" para o OpenCV ler na inicialização
import os

os.environ.setdefault(
    "OPENCV_FFMPEG_LOGLEVEL", "8"
)  # 8 = AV_LOG_FATAL: silencia spam do swscaler

import cv2
import tempfile
import subprocess
from detector import detector, CLASS_THRESHOLDS, IGNORED_CLASSES


def _normalizar_video(src):
    """
    Cria uma cópia temporária com flag de campo progressiva explícita.

    O vídeo original vem com field_order=unknown, o que faz o swscaler do
    FFmpeg 8 recusar a conversão yuv420p -> bgr24 ('Cannot convert interlaced
    to progressive...'). 'setfield=prog' resolve a ambiguidade.

    Retorna o caminho do ficheiro temporário (que deve ser apagado depois).
    """
    fd, tmp = tempfile.mkstemp(suffix=".mp4")
    os.close(fd)
    cmd = [
        "ffmpeg",
        "-y",
        "-loglevel",
        "error",
        "-i",
        src,
        # yadif=0:-1:0 -> desentrelaça SE houver entrelaçamento real (defensivo
        # para outros uploads); setfield=prog -> limpa a flag ambígua.
        "-vf",
        "setfield=prog,format=yuv420p",
        "-c:v",
        "libx264",
        "-preset",
        "ultrafast",
        "-an",  # descarta áudio: não é necessário para a detecção
        tmp,
    ]
    subprocess.run(cmd, check=True)
    return tmp


def analyze_video(video_path):
    """
    Analisa um vídeo extraindo frames em intervalos e processando-os com NudeNet.
    Retorna um dicionário com is_sensitive, score, triggered_by e detalhes.
    """
    if not os.path.exists(video_path):
        raise FileNotFoundError(f"Vídeo não encontrado: {video_path}")

    # --- Normalização: evita o erro do swscaler com field_order=unknown ---
    normalized_path = None
    try:
        normalized_path = _normalizar_video(video_path)
        path_to_open = normalized_path
    except Exception as e:
        print(f"[AVISO] Falha ao normalizar vídeo ({e}). A usar o original.")
        path_to_open = video_path

    cap = cv2.VideoCapture(path_to_open)
    if not cap.isOpened():
        if normalized_path and os.path.exists(normalized_path):
            os.remove(normalized_path)
        raise Exception(
            f"Não foi possível abrir o vídeo: {video_path}. Verifique o caminho ou codecs."
        )

    frame_sample_rate = 10

    is_sensitive = False
    max_score = 0.0
    triggered_by = None
    frames_analyzed_count = 0
    total_frames_read = 0

    temp_fd, temp_image_path = tempfile.mkstemp(suffix=".jpg")
    os.close(temp_fd)

    detections = []

    try:
        while True:
            ret, frame = cap.read()
            if not ret:
                break

            # Proteção: ignora frames vazios/corrompidos
            if frame is None or frame.size == 0:
                continue

            total_frames_read += 1

            if total_frames_read % frame_sample_rate == 0:
                try:
                    cv2.imwrite(temp_image_path, frame)
                    detections = detector.detect(temp_image_path)
                    frames_analyzed_count += 1

                    for d in detections:
                        label = d.get("label") or d.get("class")
                        score = d.get("score", 0)
                        if label in IGNORED_CLASSES:
                            continue
                        if label in CLASS_THRESHOLDS:
                            threshold = CLASS_THRESHOLDS[label]
                            if score >= threshold:
                                is_sensitive = True
                                if score > max_score:
                                    max_score = score
                                    triggered_by = label
                except Exception as e:
                    print(f"Erro ao processar frame {total_frames_read}: {e}")
                    continue
    except Exception as e:
        print(f"Erro crítico durante o processamento do vídeo: {e}")
        raise e
    finally:
        cap.release()
        if os.path.exists(temp_image_path):
            os.remove(temp_image_path)
        if normalized_path and os.path.exists(normalized_path):
            os.remove(normalized_path)

    # Se não for sensível, calcular um score geral baixo baseado em classes não ignoradas
    if not is_sensitive:
        all_scores = [
            d.get("score", 0)
            for d in detections
            if (d.get("label") or d.get("class")) not in IGNORED_CLASSES
            and d.get("score", 0) > 0.3
        ]
        if all_scores:
            # Score médio reduzido para não ativar falsos positivos
            percent = (sum(all_scores) / len(all_scores)) * 30
        else:
            percent = 0
    else:
        percent = max_score * 100

    return {
        "type": "video",
        "is_sensitive": is_sensitive,
        "score": round(percent, 2),
        "triggered_by": triggered_by,
        "explicit_percentage": round(percent, 2),  # Mantido para retrocompatibilidade
        "frames_analyzed": frames_analyzed_count,
    }
