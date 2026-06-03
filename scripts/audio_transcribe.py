#!/usr/bin/env python3
"""
Transcrição offline de áudio via Vosk (sem API, sem IA em nuvem).

Uso:
    python3 audio_transcribe.py <caminho_audio>

Saída (stdout):
    {"ok": true, "text": "gasto de cinquenta reais no mercado"}
    {"ok": false, "error": "mensagem de erro"}

Variáveis de ambiente:
    VOSK_MODEL_PATH  – caminho para o modelo Vosk (padrão: scripts/vosk-model-pt)
    FFMPEG_BINARY    – binário do ffmpeg (padrão: ffmpeg)

Modelo recomendado (Português):
    https://alphacephei.com/vosk/models/vosk-model-small-pt-0.3.zip
    Descompacte em: <raiz do projeto>/scripts/vosk-model-pt
"""

import json
import os
import subprocess
import sys
import tempfile
import wave


def convert_to_wav(input_path: str, output_path: str) -> bool:
    ffmpeg = os.environ.get("FFMPEG_BINARY", "ffmpeg")
    result = subprocess.run(
        [ffmpeg, "-y", "-i", input_path, "-ar", "16000", "-ac", "1", "-f", "wav", output_path],
        capture_output=True,
    )
    return result.returncode == 0


def transcribe_wav(wav_path: str, model_path: str) -> str:
    try:
        from vosk import KaldiRecognizer, Model  # type: ignore[import]
    except ImportError:
        raise RuntimeError("Pacote 'vosk' não instalado. Execute: pip install vosk")

    model = Model(model_path)

    with wave.open(wav_path, "rb") as wf:
        sample_rate = wf.getframerate()
        rec = KaldiRecognizer(model, sample_rate)
        rec.SetWords(False)

        parts: list[str] = []
        while True:
            data = wf.readframes(4000)
            if not data:
                break
            if rec.AcceptWaveform(data):
                result = json.loads(rec.Result())
                text = result.get("text", "").strip()
                if text:
                    parts.append(text)

        final = json.loads(rec.FinalResult())
        text = final.get("text", "").strip()
        if text:
            parts.append(text)

    return " ".join(parts).strip()


def main() -> None:
    if len(sys.argv) < 2:
        print(json.dumps({"ok": False, "error": "Uso: audio_transcribe.py <arquivo_audio>"}))
        sys.exit(1)

    audio_path = sys.argv[1]

    if not os.path.exists(audio_path):
        print(json.dumps({"ok": False, "error": f"Arquivo não encontrado: {audio_path}"}))
        sys.exit(1)

    script_dir = os.path.dirname(os.path.abspath(__file__))
    model_path = os.environ.get("VOSK_MODEL_PATH", os.path.join(script_dir, "vosk-model-pt"))

    if not os.path.isdir(model_path):
        msg = (
            f"Modelo Vosk não encontrado em: {model_path}. "
            "Baixe em https://alphacephei.com/vosk/models/vosk-model-small-pt-0.3.zip "
            f"e descompacte como {model_path}"
        )
        print(json.dumps({"ok": False, "error": msg}))
        sys.exit(1)

    tmp_wav = tempfile.mktemp(suffix=".wav")
    try:
        if not convert_to_wav(audio_path, tmp_wav):
            print(json.dumps({"ok": False, "error": "Falha ao converter áudio (ffmpeg instalado?)"}))
            sys.exit(1)

        text = transcribe_wav(tmp_wav, model_path)

        if not text:
            print(json.dumps({"ok": False, "error": "Não foi possível entender o áudio"}))
        else:
            print(json.dumps({"ok": True, "text": text}))
    except Exception as e:  # noqa: BLE001
        print(json.dumps({"ok": False, "error": str(e)}))
        sys.exit(1)
    finally:
        if os.path.exists(tmp_wav):
            os.unlink(tmp_wav)


if __name__ == "__main__":
    main()
