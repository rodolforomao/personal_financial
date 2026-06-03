#!/usr/bin/env python3
"""
Transcrição de áudio via OpenAI Whisper API (padrão) ou Vosk (fallback offline).

Uso:
    python3 audio_transcribe.py <caminho_audio>

Saída (stdout):
    {"ok": true, "text": "gasto de cinquenta reais no mercado"}
    {"ok": false, "error": "mensagem de erro"}

Variáveis de ambiente:
    OPENAI_API_KEY       – chave da API OpenAI (usa Whisper API quando presente)
    OPENAI_BASE_URL      – base URL da API OpenAI (padrão: https://api.openai.com/v1)
    WHISPER_MODEL        – modelo Whisper (padrão: whisper-1)
    VOSK_MODEL_PATH      – caminho para o modelo Vosk (fallback offline)
    AUDIO_PROVIDER       – força provedor: "openai" ou "vosk"
    FFMPEG_BINARY        – binário do ffmpeg (padrão: ffmpeg)
"""

import json
import os
import subprocess
import sys
import tempfile
import wave


# ── OpenAI Whisper API ────────────────────────────────────────────────────────

_WHISPER_PROMPT = (
    "Transcrição de áudio financeiro em português brasileiro. "
    "Possíveis termos: receita, despesa, gasto, pagamento, transferência, "
    "salário, aluguel, mercado, supermercado, restaurante, empresa, categoria, "
    "valor em reais, centavos."
)


def transcribe_with_openai(audio_path: str, api_key: str, base_url: str, model: str) -> str:
    try:
        from openai import OpenAI  # type: ignore[import]
    except ImportError:
        raise RuntimeError("Pacote 'openai' não instalado. Execute: pip install openai")

    client = OpenAI(api_key=api_key, base_url=base_url or "https://api.openai.com/v1")

    with open(audio_path, "rb") as f:
        result = client.audio.transcriptions.create(
            model=model or "whisper-1",
            file=f,
            language="pt",
            prompt=_WHISPER_PROMPT,
        )

    return result.text.strip()


# ── Vosk (offline fallback) ───────────────────────────────────────────────────

def convert_to_wav(input_path: str, output_path: str) -> bool:
    ffmpeg = os.environ.get("FFMPEG_BINARY", "ffmpeg")
    result = subprocess.run(
        [ffmpeg, "-y", "-i", input_path, "-ar", "16000", "-ac", "1", "-f", "wav", output_path],
        capture_output=True,
    )
    return result.returncode == 0


def transcribe_with_vosk(audio_path: str, model_path: str) -> str:
    try:
        from vosk import KaldiRecognizer, Model  # type: ignore[import]
    except ImportError:
        raise RuntimeError("Pacote 'vosk' não instalado. Execute: pip install vosk")

    if not os.path.isdir(model_path):
        raise RuntimeError(
            f"Modelo Vosk não encontrado em: {model_path}. "
            "Baixe em https://alphacephei.com/vosk/models/vosk-model-small-pt-0.3.zip "
            f"e descompacte como {model_path}"
        )

    model = Model(model_path)
    tmp_wav = tempfile.mktemp(suffix=".wav")
    try:
        if not convert_to_wav(audio_path, tmp_wav):
            raise RuntimeError("Falha ao converter áudio (ffmpeg instalado?)")

        with wave.open(tmp_wav, "rb") as wf:
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
    finally:
        if os.path.exists(tmp_wav):
            os.unlink(tmp_wav)


# ── Main ──────────────────────────────────────────────────────────────────────

def main() -> None:
    if len(sys.argv) < 2:
        print(json.dumps({"ok": False, "error": "Uso: audio_transcribe.py <arquivo_audio>"}))
        sys.exit(1)

    audio_path = sys.argv[1]

    if not os.path.exists(audio_path):
        print(json.dumps({"ok": False, "error": f"Arquivo não encontrado: {audio_path}"}))
        sys.exit(1)

    api_key = os.environ.get("OPENAI_API_KEY", "")
    base_url = os.environ.get("OPENAI_BASE_URL", "https://api.openai.com/v1")
    whisper_model = os.environ.get("WHISPER_MODEL", "whisper-1")
    provider = os.environ.get("AUDIO_PROVIDER", "")

    # Escolhe provedor: explícito > OpenAI quando tem chave > Vosk
    use_openai = provider == "openai" or (provider != "vosk" and bool(api_key))

    try:
        if use_openai:
            if not api_key:
                raise RuntimeError("OPENAI_API_KEY não definida. Configure a variável de ambiente.")
            text = transcribe_with_openai(audio_path, api_key, base_url, whisper_model)
        else:
            script_dir = os.path.dirname(os.path.abspath(__file__))
            model_path = os.environ.get("VOSK_MODEL_PATH", os.path.join(script_dir, "vosk-model-pt"))
            text = transcribe_with_vosk(audio_path, model_path)

        if not text:
            print(json.dumps({"ok": False, "error": "Não foi possível entender o áudio"}))
        else:
            print(json.dumps({"ok": True, "text": text}))

    except Exception as e:  # noqa: BLE001
        print(json.dumps({"ok": False, "error": str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
