<?php

namespace App\Core\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class AudioTranscriptionService
{
    public function transcribe(string $audioPath): ?string
    {
        $python = config('financial.audio.python_binary', 'python3');
        $script = base_path('scripts/audio_transcribe.py');

        $env = [
            'AUDIO_PROVIDER'  => config('financial.audio.provider', 'openai'),
            'OPENAI_API_KEY'  => config('financial.ai.openai.api_key', ''),
            'OPENAI_BASE_URL' => config('financial.ai.openai.base_url', 'https://api.openai.com/v1'),
            'WHISPER_MODEL'   => config('financial.audio.whisper_model', 'whisper-1'),
            'VOSK_MODEL_PATH' => config('financial.audio.vosk_model_path', base_path('scripts/vosk-model-pt')),
        ];

        $result = Process::env($env)
            ->run([$python, $script, $audioPath]);

        $output = trim($result->output());

        if ($output === '') {
            Log::warning('AudioTranscription: empty output', ['stderr' => $result->errorOutput()]);

            return null;
        }

        $decoded = json_decode($output, true);

        if (! ($decoded['ok'] ?? false)) {
            Log::warning('AudioTranscription: failed', ['error' => $decoded['error'] ?? $output]);

            return null;
        }

        $text = trim((string) ($decoded['text'] ?? ''));

        return $text !== '' ? $text : null;
    }

    public function isAvailable(): bool
    {
        $script = base_path('scripts/audio_transcribe.py');

        if (! is_file($script)) {
            return false;
        }

        $provider = config('financial.audio.provider', 'openai');

        if ($provider === 'openai') {
            return ! empty(config('financial.ai.openai.api_key'));
        }

        return is_dir(config('financial.audio.vosk_model_path', base_path('scripts/vosk-model-pt')));
    }

    public function isAudioMime(string $mime): bool
    {
        return str_starts_with($mime, 'audio/');
    }
}
