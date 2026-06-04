import os
from deepface import DeepFace
from ..config import FACE_MODEL, DETECTOR_BACKEND
from ..helpers.logger import get_logger
from ..helpers.image_helper import extract_face, get_best_frame_from_video

logger = get_logger("face_service")

class FaceVerificationService:
    @staticmethod
    async def verify(selfie_path: str, document_path: str):
        """
        Compara a selfie (ou vídeo) com o documento.
        """
        temp_files = []
        try:
            # 1. Determinar se a selfie é vídeo ou imagem
            is_video = selfie_path.lower().endswith(('.mp4', '.webm', '.avi', '.mov'))
            
            # 2. Obter imagem do rosto da selfie
            if is_video:
                logger.info(f"Processando vídeo de selfie: {selfie_path}")
                selfie_face_img = get_best_frame_from_video(selfie_path, "selfie")
            else:
                logger.info(f"Processando imagem de selfie: {selfie_path}")
                selfie_face_img = extract_face(selfie_path, "selfie")
                
            if not selfie_face_img:
                return {"success": False, "error": "Não foi possível detectar rosto na selfie/vídeo"}
            temp_files.append(selfie_face_img)
            
            # 3. Obter imagem do rosto do documento
            logger.info(f"Processando documento: {document_path}")
            doc_face_img = extract_face(document_path, "document")
            if not doc_face_img:
                return {"success": False, "error": "Não foi possível detectar rosto no documento"}
            temp_files.append(doc_face_img)
            
            # 4. Comparar usando DeepFace com ArcFace
            logger.info(f"Iniciando comparação com modelo {FACE_MODEL}")
            result = DeepFace.verify(
                img1_path=selfie_face_img,
                img2_path=doc_face_img,
                model_name=FACE_MODEL,
                detector_backend=DETECTOR_BACKEND,
                enforce_detection=True,
                distance_metric="cosine"
            )
            
            # 5. Calcular Score e Status
            # Distância de cosseno: 0 é idêntico, 1 é totalmente diferente
            # Score de similaridade = 1 - distância
            distance = result.get("distance", 1.0)
            score = round(1 - distance, 2)
            
            # Regras de negócio
            status = "rejected"
            match = False
            
            if score >= 0.90:
                status = "approved"
                match = True
            elif score >= 0.70:
                status = "manual_review"
                match = True # Consideramos match para revisão manual
            else:
                status = "rejected"
                match = False
                
            logger.info(f"Resultado: score={score}, status={status}")
            
            return {
                "success": True,
                "match": match,
                "score": score,
                "status": status
            }
            
        except Exception as e:
            logger.error(f"Erro no pipeline de verificação: {str(e)}")
            return {"success": False, "error": f"Erro interno: {str(e)}"}
            
        finally:
            # Limpeza de arquivos temporários
            for f in temp_files:
                if os.path.exists(f):
                    try:
                        os.remove(f)
                    except:
                        pass
