# ai/identity/config.py
# Configurações do módulo de verificação de identidade

import os

# ─────────────────────────────────────────────
#  MODELO DEEPFACE / ARCFACE
# ─────────────────────────────────────────────
FACE_MODEL = "ArcFace"          # Modelo principal para embeddings
DETECTOR_BACKEND = "mediapipe"  # Backend de detecção de rosto (mais rápido no Windows)

# Score mínimo de similaridade para considerar o rosto como correspondente (0.0 – 1.0)
# ArcFace usa distância de cosseno: quanto MENOR, mais similar
# Convertemos para score de similaridade: 1 - distância
SIMILARITY_THRESHOLD = 0.50     # 50% de similaridade mínima

# ─────────────────────────────────────────────
#  MEDIAPIPE LIVENESS (preparação futura)
# ─────────────────────────────────────────────
LIVENESS_BLINK_REQUIRED = False         # Ainda não implementado — reservado
LIVENESS_MOTION_THRESHOLD = 0.005       # Movimento mínimo de pixels entre frames

# ─────────────────────────────────────────────
#  QUALIDADE DE IMAGEM
# ─────────────────────────────────────────────
MIN_FACE_WIDTH_PX   = 80    # Rosto mínimo em pixels (largura)
MIN_FACE_HEIGHT_PX  = 80    # Rosto mínimo em pixels (altura)
BLUR_THRESHOLD      = 80.0  # Laplacian variance — abaixo disso é considerado desfocado

# ─────────────────────────────────────────────
#  EXTRACÇÃO DE FRAME DO VÍDEO
# ─────────────────────────────────────────────
VIDEO_FRAMES_TO_EXTRACT  = 10    # Número de frames a amostrar do vídeo de selfie
VIDEO_FRAME_INTERVAL_SEC = 0.5  # Intervalo entre frames amostrados (segundos)

# ─────────────────────────────────────────────
#  CAMINHOS
# ─────────────────────────────────────────────
BASE_PATH = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
UPLOAD_BASE       = os.path.join(BASE_PATH, "uploads")
VERIFICATIONS_DIR = os.path.join(UPLOAD_BASE, "verifications")
IDENTITY_LOGS_DIR = os.path.join(BASE_PATH, "ai", "identity", "logs")
TEMP_DIR          = os.path.join(BASE_PATH, "ai", "identity", "_temp")

# Garantir que os diretórios existem ao importar o módulo
for _d in [IDENTITY_LOGS_DIR, TEMP_DIR]:
    os.makedirs(_d, exist_ok=True)

# ─────────────────────────────────────────────
#  ANTI-FRAUDE (preparação futura)
# ─────────────────────────────────────────────
MAX_VERIFICATION_ATTEMPTS = 3   # Máximo de tentativas antes de bloquear
FRAUD_SCORE_THRESHOLD     = 0.8 # Score acima disso → sinalizar revisão manual
