import os
from nudenet import NudeDetector

# Inicializa o detector globalmente (melhor performance)
detector = NudeDetector()

# Thresholds específicos por classe para reduzir falsos positivos
# Valores mais altos exigem maior confiança da IA para marcar como sensível
CLASS_THRESHOLDS = {
    "BUTTOCKS_EXPOSED": 0.65,
    "FEMALE_BREAST_EXPOSED": 0.60,
    "FEMALE_GENITALIA_EXPOSED": 0.55,
    "MALE_BREAST_EXPOSED": 0.70, # Peito masculino costuma ter mais falsos positivos
    "MALE_GENITALIA_EXPOSED": 0.55,
    "ANUS_EXPOSED": 0.55
}

# Classes que devem ser ignoradas (não são consideradas NSFW para o contexto)
IGNORED_CLASSES = [
    "FACE_FEMALE",
    "FACE_MALE",
    "FEET_EXPOSED",
    "BELLY_EXPOSED",
    "ARMPITS_EXPOSED",
    "BUTTOCKS_COVERED",
    "FEMALE_BREAST_COVERED",
    "MALE_BREAST_COVERED"
]

def analyze_image(path):
    """
    Analisa uma imagem para detetar conteúdo explícito.
    Retorna um dicionário com is_sensitive, score, triggered_by e detalhes.
    """

    if not os.path.exists(path):
        raise FileNotFoundError(f"Imagem não encontrada: {path}")

    detections = detector.detect(path)

    is_sensitive = False
    max_score = 0.0
    triggered_by = None

    # Loop robusto (compatível com versões diferentes do NudeNet)
    for d in detections:
        label = d.get("label") or d.get("class")
        score = d.get("score", 0)

        # Ignorar classes irrelevantes
        if label in IGNORED_CLASSES:
            continue

        # Verificar se a classe está nas categorias críticas e se o score supera o threshold
        if label in CLASS_THRESHOLDS:
            threshold = CLASS_THRESHOLDS[label]
            if score >= threshold:
                is_sensitive = True
                if score > max_score:
                    max_score = score
                    triggered_by = label

    # Se não for sensível, calcular um score geral baixo baseado em classes não ignoradas
    if not is_sensitive:
        all_scores = [
            d.get("score", 0)
            for d in detections
            if (d.get("label") or d.get("class")) not in IGNORED_CLASSES and d.get("score", 0) > 0.3
        ]
        if all_scores:
            # Score médio reduzido para não ativar falsos positivos
            percent = (sum(all_scores) / len(all_scores)) * 30
        else:
            percent = 0
    else:
        percent = max_score * 100

    return {
        "type": "image",
        "is_sensitive": is_sensitive,
        "score": round(percent, 2),
        "triggered_by": triggered_by,
        "explicit_percentage": round(percent, 2), # Mantido para retrocompatibilidade
        "detections": detections
    }
