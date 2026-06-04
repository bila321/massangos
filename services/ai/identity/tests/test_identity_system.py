#!/usr/bin/env python3
# ai/identity/tests/test_identity_system.py
#
# Script de testes para o sistema de verificação de identidade
#
# EXECUTAR: python -m pytest ai/identity/tests/test_identity_system.py -v
# OU directamente: python ai/identity/tests/test_identity_system.py

import os
import sys
import json
import tempfile
import traceback

# Ajustar path para imports locais
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))

SEPARATOR = "=" * 60


def test_imports():
    """Teste 1: Verificar que todas as dependências estão instaladas."""
    print(f"\n{SEPARATOR}")
    print("TESTE 1: Verificação de imports")
    print(SEPARATOR)

    modules = {
        "cv2":       "opencv-python",
        "numpy":     "numpy",
        "deepface":  "deepface",
        "mediapipe": "mediapipe",
        "fastapi":   "fastapi",
    }

    all_ok = True
    for module, package in modules.items():
        try:
            __import__(module)
            version = getattr(sys.modules[module], "__version__", "?")
            print(f"  ✅ {module} ({package}) — v{version}")
        except ImportError:
            print(f"  ❌ {module} ({package}) — NÃO INSTALADO")
            all_ok = False

    return all_ok


def test_image_quality(image_path: str = None):
    """Teste 2: Qualidade de imagem."""
    print(f"\n{SEPARATOR}")
    print("TESTE 2: Verificação de qualidade de imagem")
    print(SEPARATOR)

    from identity.utils.image_quality import run_full_quality_check
    import cv2
    import numpy as np

    # Criar imagem de teste se não fornecida
    if not image_path or not os.path.exists(image_path):
        with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as f:
            test_path = f.name
        # Imagem clara com gradiente
        img = np.ones((400, 600, 3), dtype=np.uint8) * 128
        cv2.putText(img, "TEST IMAGE", (100, 200), cv2.FONT_HERSHEY_SIMPLEX, 2, (255, 255, 255), 3)
        cv2.imwrite(test_path, img)
        print(f"  Imagem de teste criada: {test_path}")
    else:
        test_path = image_path

    result = run_full_quality_check(test_path)
    print(f"  Passou: {result['passed']}")
    print(f"  Issues: {result['issues'] or 'nenhum'}")
    print(f"  Métricas: {result['metrics']}")

    if not image_path:
        os.unlink(test_path)

    return result["passed"]


def test_face_detection(image_path: str = None):
    """Teste 3: Detecção de rostos."""
    print(f"\n{SEPARATOR}")
    print("TESTE 3: Detecção de rostos")
    print(SEPARATOR)

    from identity.services.face_detector import detect_faces_mediapipe, detect_faces_opencv_haar

    if not image_path or not os.path.exists(image_path):
        print("  ⚠️  Sem imagem real fornecida — a usar imagem de teste sintética")
        print("      Para resultados reais, passe: python test_identity_system.py --image /caminho/para/foto.jpg")
        import cv2
        import numpy as np
        with tempfile.NamedTemporaryFile(suffix=".jpg", delete=False) as f:
            test_path = f.name
        # Imagem simples (não vai ter rosto real)
        img = np.zeros((400, 400, 3), dtype=np.uint8) + 200
        cv2.imwrite(test_path, img)
    else:
        test_path = image_path

    # MediaPipe
    result_mp = detect_faces_mediapipe(test_path)
    print(f"  MediaPipe — Rostos: {result_mp['face_count']} | Erro: {result_mp['error']}")

    # Haar fallback
    result_haar = detect_faces_opencv_haar(test_path)
    print(f"  Haar CV2  — Rostos: {result_haar['face_count']} | Erro: {result_haar['error']}")

    if not image_path:
        os.unlink(test_path)

    return result_mp["success"]


def test_face_comparison(img1: str = None, img2: str = None):
    """Teste 4: Comparação de rostos."""
    print(f"\n{SEPARATOR}")
    print("TESTE 4: Comparação de rostos (ArcFace)")
    print(SEPARATOR)

    from identity.services.face_matcher import compare_faces

    if not img1 or not img2:
        print("  ⚠️  Imagens reais não fornecidas")
        print("      Para testar comparação real:")
        print("      python test_identity_system.py --img1 /bi_frente.jpg --img2 /selfie.jpg")
        print("  (Saltando teste de comparação real)")
        return None  # Não falha — apenas salta

    print(f"  Comparando:")
    print(f"    Imagem 1 (BI): {os.path.basename(img1)}")
    print(f"    Imagem 2 (Selfie): {os.path.basename(img2)}")

    result = compare_faces(img1, img2, user_id=999, verification_id=999)
    print(f"  Match: {result['match']}")
    print(f"  Similaridade: {result['similarity_score']:.1%}")
    print(f"  Distância: {result['distance']}")
    print(f"  Erro: {result['error']}")

    return result.get("error") is None


def test_video_frame_extraction(video_path: str = None):
    """Teste 5: Extracção de frames de vídeo."""
    print(f"\n{SEPARATOR}")
    print("TESTE 5: Extracção de frames de vídeo")
    print(SEPARATOR)

    from identity.utils.video_utils import extract_frames_from_video, cleanup_temp_frames

    if not video_path or not os.path.exists(video_path):
        print("  ⚠️  Sem vídeo real fornecido")
        print("      Para testar: python test_identity_system.py --video /caminho/video.webm")
        return None

    frames = extract_frames_from_video(video_path, n_frames=3)
    print(f"  Frames extraídos: {len(frames)}")
    for i, f in enumerate(frames):
        print(f"    Frame {i+1}: {f} {'(existe)' if os.path.exists(f) else '(ERRO)'}")

    cleanup_temp_frames(frames)
    print("  Temp files limpos.")
    return len(frames) > 0


def test_api_health():
    """Teste 6: Health check da API (requer servidor a correr)."""
    print(f"\n{SEPARATOR}")
    print("TESTE 6: API Health Check")
    print(SEPARATOR)

    try:
        import urllib.request
        import urllib.error
        url = "http://127.0.0.1:8000/identity/health"
        with urllib.request.urlopen(url, timeout=3) as resp:
            data = json.loads(resp.read())
        print(f"  Status: {data.get('status')}")
        print(f"  Checks: {data.get('checks')}")
        return data.get("status") == "healthy"
    except Exception as e:
        print(f"  ⚠️  Servidor não disponível ({e})")
        print("      Inicie o servidor com: uvicorn main:app --reload")
        return None


def run_all_tests(args=None):
    """Executa todos os testes e mostra sumário."""
    print("\n" + SEPARATOR)
    print("  MASSANGO — IDENTITY VERIFICATION SYSTEM TESTS")
    print(SEPARATOR)

    img1  = getattr(args, "img1",  None) if args else None
    img2  = getattr(args, "img2",  None) if args else None
    image = getattr(args, "image", None) if args else None
    video = getattr(args, "video", None) if args else None

    results = {}

    tests = [
        ("imports",        lambda: test_imports()),
        ("image_quality",  lambda: test_image_quality(image)),
        ("face_detection", lambda: test_face_detection(image or img1)),
        ("face_comparison",lambda: test_face_comparison(img1, img2)),
        ("video_frames",   lambda: test_video_frame_extraction(video)),
        ("api_health",     lambda: test_api_health()),
    ]

    for name, fn in tests:
        try:
            results[name] = fn()
        except Exception as e:
            print(f"\n  💥 Excepção em {name}: {e}")
            traceback.print_exc()
            results[name] = False

    # Sumário
    print(f"\n{SEPARATOR}")
    print("  SUMÁRIO DOS TESTES")
    print(SEPARATOR)
    for name, passed in results.items():
        if passed is True:
            icon = "✅"
        elif passed is None:
            icon = "⏭️ (saltado)"
        else:
            icon = "❌"
        print(f"  {icon}  {name}")

    failed = [k for k, v in results.items() if v is False]
    if failed:
        print(f"\n  ❌ {len(failed)} teste(s) falharam: {', '.join(failed)}")
    else:
        print(f"\n  ✅ Todos os testes passaram ou foram saltados!")

    return len(failed) == 0


if __name__ == "__main__":
    import argparse

    parser = argparse.ArgumentParser(description="Testes do módulo de identidade Massango")
    parser.add_argument("--img1",  help="Caminho para imagem do BI (frente)")
    parser.add_argument("--img2",  help="Caminho para selfie/imagem de rosto")
    parser.add_argument("--image", help="Imagem genérica para testes de qualidade/detecção")
    parser.add_argument("--video", help="Caminho para vídeo .webm de prova de vida")

    args = parser.parse_args()
    success = run_all_tests(args)
    sys.exit(0 if success else 1)
