"""
ai/test_imports.py

Diagnóstico rápido de imports do módulo de identidade.
Executar ANTES de iniciar o uvicorn para confirmar que tudo carrega.

Uso:
    cd C:\\Users\\Bila\\Desktop\\massangos\\massango\\ai
    venv\\Scripts\\activate
    python test_imports.py

Saída esperada (tudo OK):
    [OK] identity.config
    [OK] identity.utils.logger
    [OK] identity.utils.video_utils
    [OK] identity.services.face_detector
    [OK] identity.services.face_matcher
    [OK] identity.services.face_service
    [OK] identity.face_verify (verify_faces, extract_face_from_document, process_video_for_selfie)
    [OK] identity.services.face_verification_service
    [OK] identity.routes.face_routes (face_router)
    [OK] identity.verify_identity
    [OK] identity.api_endpoint (identity_router)
    [OK] main (identity_router registado)
    ✅ TODOS OS IMPORTS OK — pode iniciar o uvicorn
"""

import sys
import traceback

errors = []

def test(label, fn):
    try:
        fn()
        print(f"  [OK] {label}")
    except Exception as e:
        print(f"  [FALHA] {label}")
        print(f"          → {type(e).__name__}: {e}")
        errors.append((label, e))

print("\n=== DIAGNÓSTICO DE IMPORTS — Massango Identity Module ===\n")

test("identity.config", lambda: __import__("identity.config", fromlist=["FACE_MODEL"]))
test("identity.utils.logger", lambda: __import__("identity.utils.logger", fromlist=["get_identity_logger"]))
test("identity.utils.video_utils", lambda: __import__("identity.utils.video_utils", fromlist=["extract_frames_from_video"]))
test("identity.services.face_detector", lambda: __import__("identity.services.face_detector", fromlist=["detect_faces_mediapipe"]))
test("identity.services.face_matcher", lambda: __import__("identity.services.face_matcher", fromlist=["compare_faces"]))
test("identity.services.face_service", lambda: __import__("identity.services.face_service", fromlist=["FaceVerificationService"]))

def _test_face_verify():
    m = __import__("identity.face_verify", fromlist=["verify_faces", "extract_face_from_document", "process_video_for_selfie"])
    assert hasattr(m, "verify_faces"), "verify_faces não encontrado"
    assert hasattr(m, "extract_face_from_document"), "extract_face_from_document não encontrado"
    assert hasattr(m, "process_video_for_selfie"), "process_video_for_selfie não encontrado"

test("identity.face_verify (verify_faces, extract_face_from_document, process_video_for_selfie)", _test_face_verify)
test("identity.services.face_verification_service", lambda: __import__("identity.services.face_verification_service", fromlist=["perform_face_verification"]))

def _test_face_routes():
    m = __import__("identity.routes.face_routes", fromlist=["face_router"])
    assert hasattr(m, "face_router"), "face_router não encontrado (ficheiro exporta 'router'?)"

test("identity.routes.face_routes (face_router)", _test_face_routes)
test("identity.verify_identity", lambda: __import__("identity.verify_identity", fromlist=["run_identity_verification"]))

def _test_api_endpoint():
    m = __import__("identity.api_endpoint", fromlist=["identity_router"])
    assert hasattr(m, "identity_router"), "identity_router não encontrado"

test("identity.api_endpoint (identity_router)", _test_api_endpoint)

def _test_main():
    import importlib
    m = importlib.import_module("main")
    # Verificar que o router foi registado
    from identity.api_endpoint import identity_router
    routes = [r.path for r in m.app.routes]
    identity_routes = [r for r in routes if "identity" in r]
    assert identity_routes, f"Nenhuma rota /identity registada. Rotas existentes: {routes}"

test("main (identity_router registado)", _test_main)

# ─── Dependências externas ────────────────────────────────────────────────────
print()
print("=== DEPENDÊNCIAS EXTERNAS ===")

def _check_dep(name, import_name=None):
    try:
        mod = __import__(import_name or name)
        version = getattr(mod, "__version__", "instalado")
        print(f"  [OK] {name} ({version})")
    except ImportError:
        print(f"  [NÃO INSTALADO] {name} → pip install {name}")
        errors.append((name, ImportError(f"{name} não instalado")))

_check_dep("deepface")
_check_dep("mediapipe")
_check_dep("opencv-python", "cv2")
_check_dep("mysql-connector-python", "mysql.connector")
_check_dep("fastapi")
_check_dep("uvicorn")

# ─── Resultado ────────────────────────────────────────────────────────────────
print()
if errors:
    print(f"❌ {len(errors)} ERRO(S) ENCONTRADO(S):")
    for label, e in errors:
        print(f"   • {label}: {type(e).__name__}: {e}")
    print()
    print("Corrija os erros acima antes de iniciar o uvicorn.")
    sys.exit(1)
else:
    print("✅ TODOS OS IMPORTS OK — pode iniciar o uvicorn:")
    print()
    print("   uvicorn main:app --reload --host 127.0.0.1 --port 8000")
    print()
