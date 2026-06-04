from nudenet import NudeDetector

detector = NudeDetector()

# Teste com uma imagem
result = detector.detect("imagem.jpg")

print(result)