# 🔐 Massango — Módulo de Verificação de Identidade

**Versão:** 1.0.0 | **Python:** 3.11 | **SO:** Windows / XAMPP

---

## 📁 Estrutura

```
ai/
├── main_updated.py              ← Substitui main.py (mantém NudeNet + adiciona /identity)
└── identity/
    ├── __init__.py
    ├── config.py                ← Configurações centrais (thresholds, paths)
    ├── verify_identity.py       ← Orquestrador principal do pipeline
    ├── api_endpoint.py          ← Router FastAPI (/identity/verify, /status, /health)
    ├── php_trigger.php          ← Helper PHP para chamar o serviço
    ├── integration_example.php  ← Exemplo de integração no process_verification.php
    ├── migration_identity_ai.sql← Migração SQL para novas colunas
    ├── requirements_identity.txt← Dependências pip
    │
    ├── services/
    │   ├── face_detector.py     ← Detecção com MediaPipe + fallback Haar
    │   ├── face_matcher.py      ← Comparação ArcFace via DeepFace
    │   └── liveness_detector.py ← Liveness por movimento (+ stubs futuros)
    │
    ├── utils/
    │   ├── logger.py            ← Logs centralizados por dia
    │   ├── image_quality.py     ← Blur, brilho, tamanho do rosto
    │   └── video_utils.py       ← Extracção de frames de vídeo
    │
    ├── tests/
    │   └── test_identity_system.py ← Suite de testes
    │
    └── logs/                    ← Logs diários (identity_YYYY-MM-DD.log)
```

---

## 🚀 Instalação

### 1. Instalar dependências

```bash
cd ai
pip install -r identity/requirements_identity.txt
```

> **Windows:** Se TensorFlow falhar, usar `tensorflow-cpu` em vez de `tensorflow`

### 2. Executar migração SQL

No phpMyAdmin ou MySQL CLI:
```sql
source ai/identity/migration_identity_ai.sql
```

### 3. Substituir main.py

```bash
# Backup do original
copy ai\main.py ai\main_backup.py

# Usar versão actualizada
copy ai\main_updated.py ai\main.py
```

### 4. Reiniciar o servidor FastAPI

```bash
cd ai
uvicorn main:app --host 127.0.0.1 --port 8000 --reload
```

---

## 🔗 Integração com PHP

### Adicionar ao `process_verification.php`

Após o `$pdo->commit()`, inserir:

```php
require_once __DIR__ . '/../ai/identity/php_trigger.php';

$new_verification_id = (int)$pdo->lastInsertId();

trigger_ai_identity_verification(
    $user_id,
    $new_verification_id,
    UPLOAD_DIR . 'verifications/' . $user_id . '/' . basename($rel_front),
    UPLOAD_DIR . 'verifications/' . $user_id . '/' . basename($rel_back),
    UPLOAD_DIR . 'verifications/' . $user_id . '/' . basename($rel_video),
    true // async — não bloqueia o utilizador
);
```

Ver `integration_example.php` para contexto completo.

---

## 🔍 Pipeline de Verificação

```
Pedido chega (BI frente + BI verso + vídeo)
         │
         ▼
[1] Ficheiros existem?
         │ não → erro
         ▼
[2] Qualidade das imagens do BI
         │ má qualidade → sinalizar (não rejeitar)
         ▼
[3] Rosto detectado no BI?
         │ não → revisão manual
         ▼
[4] Liveness no vídeo (movimento + rosto)
         │ falha → rejeitar (possível fraude)
         ▼
[5] Extrair frames do vídeo
         ▼
[6] ArcFace: BI vs melhor frame do vídeo
         │
    similarity ≥ 50% → ✅ ai_approved
    40%–50%          → ⚠️  manual_review
    < 40%            → ❌ ai_rejected
```

---

## 📡 API Endpoints

| Método | URL | Descrição |
|--------|-----|-----------|
| `POST` | `/identity/verify` | Executar verificação |
| `GET`  | `/identity/status/{id}` | Consultar status |
| `GET`  | `/identity/health` | Health check |
| `POST` | `/analyze` | **NudeNet** (inalterado) |

---

## 🧪 Testes

```bash
# Testes básicos (sem imagens reais)
python -m pytest ai/identity/tests/ -v

# Com imagens reais
python ai/identity/tests/test_identity_system.py \
    --img1 uploads/verifications/1/id_front.jpg \
    --img2 uploads/verifications/1/selfie.jpg \
    --video uploads/verifications/1/video.webm
```

---

## 🗂️ Colunas adicionadas na BD

| Tabela | Coluna | Tipo | Descrição |
|--------|--------|------|-----------|
| `user_verifications` | `ai_status` | ENUM | Status dado pela IA |
| `user_verifications` | `ai_similarity` | FLOAT | Score de similaridade (0-1) |
| `user_verifications` | `ai_liveness` | TINYINT | 1=passou, 0=falhou |
| `user_verifications` | `ai_notes` | TEXT | Notas automáticas |
| `user_verifications` | `ai_result_json` | JSON | Resultado completo |
| `user_verifications` | `ai_processed_at` | DATETIME | Quando foi processado |

**Fluxo de status:**
- `pending` → AI processa → `ai_approved` / `ai_rejected` / `manual_review`
- Admin revê manualmente → `approved` / `rejected` (status final)

---

## 🛣️ Roadmap

| Versão | Funcionalidade |
|--------|---------------|
| ✅ v1.0 | Detecção de rosto (MediaPipe), ArcFace, liveness por movimento |
| 🔜 v1.1 | Detecção de piscar de olhos (Eye Aspect Ratio) |
| 🔜 v1.2 | Challenge-response (aceno, sorrir) |
| 🔜 v1.3 | Anti-spoofing com modelo dedicado (Silent-Face) |
| 🔜 v2.0 | Dashboard admin com AI scores em tempo real |

---

## ⚠️ Compatibilidade

- ✅ Não altera `detector.py` (NudeNet)
- ✅ Não altera `video_processor.py`
- ✅ Não altera `worker.py`
- ✅ Não altera endpoints existentes
- ✅ Funciona mesmo se as novas colunas SQL não existirem (graceful fallback)
- ✅ Se o serviço de IA estiver offline, a verificação fica em `pending` para revisão manual
