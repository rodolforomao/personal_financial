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
        $modelPath = config('financial.audio.vosk_model_path', base_path('scripts/vosk-model-pt'));

        $result = Process::env(['VOSK_MODEL_PATH' => $modelPath])
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
        $modelPath = config('financial.audio.vosk_model_path', base_path('scripts/vosk-model-pt'));

        return is_file($script) && is_dir($modelPath);
    }

    public function isAudioMime(string $mime): bool
    {
        return str_starts_with($mime, 'audio/');
    }
}
