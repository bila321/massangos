
import mysql.connector
import time
import os
import sys
import traceback

# Garantir imports locais
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from detector import analyze_image
from video_processor import analyze_video
from config import DB_CONFIG

# Caminho base do projeto (um nível acima da pasta ai/)
BASE_PATH = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def get_db_connection():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Exception as e:
        print(f"[ERRO] Falha ao conectar à base de dados: {e}")
        return None


def resolve_path(relative_path):
    if not relative_path:
        return None

    clean_filename = os.path.basename(relative_path.replace("\\", "/"))

    # ROOT correcto do storage
    STORAGE_ROOT = r"C:\xampp\htdocs\massangos\storage"  # <-- Ajusta este caminho conforme necessário

    # 1. tentar caminho directo dentro de storage
    direct_path = os.path.abspath(os.path.join(STORAGE_ROOT, relative_path.replace("\\", "/")))
    if os.path.exists(direct_path):
        return direct_path

    # 2. tentar pastas comuns dentro de storage/uploads
    sub_folders = [
        "uploads/videos",
        "uploads/albums",
        "uploads/thumbnails",
        "uploads/posts",
        "uploads",
        "albums",
        "videos",
        "thumbnails",
    ]

    for folder in sub_folders:
        test_path = os.path.abspath(os.path.join(STORAGE_ROOT, folder, clean_filename))
        if os.path.exists(test_path):
            print(f"[DEBUG] Ficheiro encontrado: {test_path}")
            return test_path

    # 3. fallback uploads/...
    if "uploads" in relative_path.lower():
        parts = relative_path.replace("\\", "/").split("/")
        try:
            idx = parts.index("uploads")
            reconstructed = os.path.join(*parts[idx:])
            test_path = os.path.abspath(os.path.join(STORAGE_ROOT, reconstructed))
            if os.path.exists(test_path):
                return test_path
        except Exception:
            pass

    return None


def update_analysis(post_id, is_sensitive, score, triggered_by, status, item_type, conn=None):
    """
    Grava ou actualiza o resultado de análise em media_analysis.
    Calcula também o risk_level com base no score.
    """
    close_conn = False
    if conn is None:
        conn = get_db_connection()
        close_conn = True
    if not conn:
        return

    cursor = conn.cursor()
    try:
        if is_sensitive:
            risk_level = "high" if score >= 70 else ("medium" if score >= 40 else "low")
        else:
            risk_level = "low"

        cursor.execute(
            """
            INSERT INTO media_analysis
                (post_id, type, explicit_percentage, risk_level, status, is_sensitive, score, triggered_by)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                type               = VALUES(type),
                explicit_percentage= VALUES(explicit_percentage),
                risk_level         = VALUES(risk_level),
                status             = VALUES(status),
                is_sensitive       = VALUES(is_sensitive),
                score              = VALUES(score),
                triggered_by       = VALUES(triggered_by)
            """,
            (post_id, item_type, score, risk_level, status, is_sensitive, score, triggered_by),
        )
        conn.commit()
    except Exception as e:
        print(f"[ERRO] Falha ao actualizar media_analysis: {e}")
    finally:
        cursor.close()
        if close_conn:
            conn.close()


def get_album_cover_path(conn, album_id):
    cursor = conn.cursor(dictionary=True)
    try:
        cursor.execute(
            "SELECT thumbnail_path, cover_photo_url FROM albums WHERE id = %s", (album_id,)
        )
        album = cursor.fetchone()
        if album:
            return album.get("thumbnail_path") or album.get("cover_photo_url")
        return None
    finally:
        cursor.close()


def get_all_album_photos(conn, album_id):
    """
    Devolve lista de dicts {id, photo_path} das fotos do album.
    O campo 'id' e o album_photos.id — usado como post_id na media_analysis
    para gravar analise individual por foto.
    Tenta 'album_photos' primeiro, depois 'photos' como fallback.
    """
    cursor = conn.cursor(dictionary=True)
    try:
        # Tentativa 1: tabela album_photos
        try:
            cursor.execute(
                "SELECT id, photo_path FROM album_photos WHERE album_id = %s ORDER BY id ASC",
                (album_id,),
            )
            rows = cursor.fetchall()
            photos = [r for r in rows if r.get("photo_path")]
            if photos:
                print(f"  [DB] album_photos -> {len(photos)} foto(s) para album_id={album_id}")
                return photos
            else:
                print(f"  [AVISO] album_photos sem fotos para album_id={album_id}")
        except Exception as e1:
            print(f"  [AVISO] Tabela album_photos inacessivel: {e1}")

        # Tentativa 2: tabela photos com coluna album_id
        try:
            cursor.execute(
                "SELECT id, photo_path FROM photos WHERE album_id = %s ORDER BY id ASC",
                (album_id,),
            )
            rows = cursor.fetchall()
            photos = [r for r in rows if r.get("photo_path")]
            if photos:
                print(f"  [DB] photos -> {len(photos)} foto(s) para album_id={album_id}")
                return photos
            else:
                print(f"  [AVISO] photos sem fotos para album_id={album_id}")
        except Exception as e2:
            print(f"  [AVISO] Tabela photos inacessivel: {e2}")

        print(f"  [ERRO FATAL] Nenhuma tabela de fotos encontrada para album_id={album_id}.")
        print(f"  [DICA] Verifica se a tabela se chama album_photos ou photos e se tem coluna album_id.")
        return []
    finally:
        cursor.close()


def get_all_album_photo_paths(conn, album_id):
    """Compatibilidade: devolve so os paths."""
    return [p["photo_path"] for p in get_all_album_photos(conn, album_id)]


def analyze_album(album_id, conn):
    """
    Analisa TODAS as fotos do album:
    - Grava o resultado individual de cada foto em media_analysis
      (type='image', post_id=album_photos.id) para que o PHP possa
      aplicar blur selectivo por foto.
    - Devolve o resultado agregado worst-case para a linha do album
      (type='album').
    """
    photos = get_all_album_photos(conn, album_id)

    if not photos:
        # Fallback: tentar pela capa (sem id individual)
        cover = get_album_cover_path(conn, album_id)
        if cover:
            photos = [{"id": None, "photo_path": cover}]

    if not photos:
        print(f"[AVISO] Album {album_id} nao tem fotos para analisar.")
        return None

    best_is_sensitive = False
    best_score = 0.0
    best_triggered_by = None
    analysed = 0
    failed = 0

    for photo in photos:
        rel_path = photo["photo_path"]
        photo_id = photo.get("id")
        full_path = resolve_path(rel_path)
        if not full_path:
            print(f"  [SKIP] Ficheiro nao encontrado: {rel_path}")
            failed += 1
            continue

        try:
            result = analyze_image(full_path)
            if not result:
                failed += 1
                continue

            score = float(result.get("score", 0))
            is_sensitive = bool(result.get("is_sensitive", False))
            triggered_by = result.get("triggered_by")
            analysed += 1

            print(f"  [FOTO] {os.path.basename(rel_path)} -> score={score:.1f}% sensitive={is_sensitive} trigger={triggered_by}")

            # Gravar analise individual desta foto (para blur selectivo no PHP)
            if photo_id is not None:
                update_analysis(photo_id, is_sensitive, score, triggered_by, "done", "image", conn)

            # Acumular worst-case para o resultado do album
            if score > best_score:
                best_score = score
                best_is_sensitive = is_sensitive
                best_triggered_by = triggered_by

        except Exception as e:
            print(f"  [ERRO] Falha a analisar {rel_path}: {e}")
            failed += 1

    print(f"  [ALBUM {album_id}] Analisadas: {analysed} | Falhadas: {failed} | "
          f"Score max: {best_score:.1f}% | Sensivel: {best_is_sensitive} | Trigger: {best_triggered_by}")

    if analysed == 0:
        return None

    return {
        "type": "album",
        "is_sensitive": best_is_sensitive,
        "score": round(best_score, 2),
        "triggered_by": best_triggered_by,
    }


def process_queue():
    conn = get_db_connection()
    if not conn:
        return False

    cursor = conn.cursor(dictionary=True)

    try:
        cursor.execute(
            "SELECT * FROM media_queue WHERE status='pending' ORDER BY id ASC LIMIT 1"
        )
        item = cursor.fetchone()
        if not item:
            # Debug: mostrar contagem total da fila para ajudar a diagnosticar
            cursor.execute("SELECT COUNT(*) as total, status FROM media_queue GROUP BY status")
            stats = cursor.fetchall()
            if stats:
                print(f"  [FILA] Estado actual: { {r['status']: r['total'] for r in stats} }")
            return False

        queue_id  = item["id"]
        post_id   = item["post_id"]
        file_path = item["file_path"]
        item_type = item.get("item_type") or (
            "video" if file_path.lower().endswith((".mp4", ".webm")) else "image"
        )

        print(f"\n[*] Processando Item ID {queue_id} | Post ID {post_id} | Tipo: {item_type}")

        cursor.execute("UPDATE media_queue SET status='processing' WHERE id=%s", (queue_id,))
        conn.commit()

        update_analysis(post_id, False, 0, None, "processing", item_type, conn)

        # ── ANÁLISE ───────────────────────────────────────────────────────────
        result = None

        if item_type == "album":
            # Analisar TODAS as fotos do álbum
            print(f"  [ALBUM] A analisar album_id={post_id}...")
            result = analyze_album(post_id, conn)
            if result is None:
                print(f"  [ALBUM] analyze_album devolveu None para album_id={post_id}.")
                print(f"  [ALBUM] Causas possiveis:")
                print(f"  [ALBUM]   1. Tabela de fotos errada (album_photos vs photos)")
                print(f"  [ALBUM]   2. Fotos sem coluna photo_path preenchida")
                print(f"  [ALBUM]   3. Caminhos de ficheiro incorrectos")
                print(f"  [ALBUM]   4. Album sem fotos na base de dados")

        elif item_type == "video" or file_path.lower().endswith((".mp4", ".mov", ".avi", ".mkv", ".webm")):
            full_path = resolve_path(file_path)
            if not full_path:
                raise Exception(f"Ficheiro de vídeo não encontrado: {file_path}")
            result = analyze_video(full_path)
            if result:
                result["type"] = "video"

        else:
            # Imagem normal
            full_path = resolve_path(file_path)
            if not full_path:
                raise Exception(f"Ficheiro de imagem não encontrado: {file_path}")
            result = analyze_image(full_path)
            if result:
                result["type"] = "image"

        # ── GUARDAR RESULTADO ────────────────────────────────────────────────
        if not result:
            raise Exception("Análise falhou — resultado vazio")

        is_sensitive = bool(result.get("is_sensitive", False))
        score        = float(result.get("score", 0))
        triggered_by = result.get("triggered_by")
        final_type   = result.get("type", item_type)

        update_analysis(post_id, is_sensitive, score, triggered_by, "done", final_type, conn)
        cursor.execute("UPDATE media_queue SET status='done' WHERE id=%s", (queue_id,))
        conn.commit()

        print(
            f"[+] CONCLUÍDO: Post {post_id} | Sensível: {is_sensitive} | "
            f"Score: {score}% | Triggered By: {triggered_by}"
        )
        return True

    except Exception as e:
        print(f"[ERRO] {e}")
        traceback.print_exc()
        if "post_id" in locals():
            update_analysis(post_id, False, 0, None, "failed", item_type, conn)
        if "queue_id" in locals():
            cursor.execute("UPDATE media_queue SET status='failed' WHERE id=%s", (queue_id,))
            conn.commit()
        return True

    finally:
        cursor.close()
        conn.close()


if __name__ == "__main__":
    print("=" * 60)
    print("=== AI Worker (Massango) Iniciado ===")
    print(f"Base Path: {BASE_PATH}")
    print("Aguardando novas tarefas em media_queue...")
    print("=" * 60)

    while True:
        try:
            processed = process_queue()
            if not processed:
                time.sleep(5)
        except Exception as e:
            print("Loop error:", e)
            time.sleep(10)