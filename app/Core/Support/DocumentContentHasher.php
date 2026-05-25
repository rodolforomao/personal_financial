<?php

namespace App\Core\Support;

use Illuminate\Http\UploadedFile;

/**
 * Hash do conteúdo visual do arquivo, ignorando metadados embutidos (EXIF, Info PDF, chunks PNG, etc.).
 */
class DocumentContentHasher
{
    public function hashUpload(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new \InvalidArgumentException('Não foi possível ler o arquivo enviado.');
        }

        return $this->hashFile($path, $file->getMimeType() ?: null);
    }

    public function hashFile(string $absolutePath, ?string $mimeType = null): string
    {
        if (! is_file($absolutePath)) {
            throw new \InvalidArgumentException('Arquivo não encontrado para hash.');
        }

        $mimeType ??= mime_content_type($absolutePath) ?: 'application/octet-stream';
        $bytes = file_get_contents($absolutePath);
        if ($bytes === false) {
            throw new \RuntimeException('Falha ao ler o arquivo para hash.');
        }

        $normalized = match (true) {
            str_contains($mimeType, 'jpeg') || str_contains($mimeType, 'jpg') => $this->stripJpegMetadata($bytes),
            str_contains($mimeType, 'png') => $this->stripPngMetadata($bytes),
            str_contains($mimeType, 'webp') => $this->stripWebpMetadata($bytes),
            str_contains($mimeType, 'pdf') => $this->stripPdfMetadata($bytes),
            default => $bytes,
        };

        return hash('sha256', $normalized);
    }

    private function stripJpegMetadata(string $data): string
    {
        if (strlen($data) < 4 || $data[0] !== "\xFF" || $data[1] !== "\xD8") {
            return $data;
        }

        $out = "\xFF\xD8";
        $pos = 2;
        $len = strlen($data);

        while ($pos + 3 < $len) {
            if ($data[$pos] !== "\xFF") {
                break;
            }

            $marker = $data[$pos + 1];

            // SOS — restante é dados da imagem
            if ($marker === "\xDA") {
                return $out.substr($data, $pos);
            }

            if ($marker === "\xD9") {
                return $out."\xFF\xD9";
            }

            if ($pos + 4 > $len) {
                break;
            }

            $segmentLen = unpack('n', substr($data, $pos + 2, 2))[1];
            if ($segmentLen < 2) {
                break;
            }

            $total = 2 + $segmentLen;

            // APP0–APP15 (EXIF, XMP, etc.)
            if ($marker >= "\xE0" && $marker <= "\xEF") {
                $pos += $total;

                continue;
            }

            if ($pos + $total > $len) {
                break;
            }

            $out .= substr($data, $pos, $total);
            $pos += $total;
        }

        return $data;
    }

    private function stripPngMetadata(string $data): string
    {
        $signature = "\x89PNG\r\n\x1a\n";
        if (! str_starts_with($data, $signature)) {
            return $data;
        }

        $ancillary = [
            'tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME', 'pHYs', 'sPLT', 'hIST', 'iCCP', 'sRGB', 'gAMA', 'cHRM',
        ];

        $out = $signature;
        $pos = 8;
        $len = strlen($data);

        while ($pos + 12 <= $len) {
            $chunkLen = unpack('N', substr($data, $pos, 4))[1];
            $type = substr($data, $pos + 4, 4);
            $chunkTotal = 12 + $chunkLen;

            if ($pos + $chunkTotal > $len) {
                break;
            }

            if (! in_array($type, $ancillary, true)) {
                $out .= substr($data, $pos, $chunkTotal);
            }

            $pos += $chunkTotal;

            if ($type === 'IEND') {
                break;
            }
        }

        return $out;
    }

    private function stripWebpMetadata(string $data): string
    {
        if (strlen($data) < 12 || substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WEBP') {
            return $data;
        }

        $metadataChunks = ['EXIF', 'XMP '];
        $out = substr($data, 0, 12);
        $pos = 12;
        $len = strlen($data);

        while ($pos + 8 <= $len) {
            $chunkId = substr($data, $pos, 4);
            $chunkSize = unpack('V', substr($data, $pos + 4, 4))[1];
            $padded = $chunkSize + ($chunkSize % 2);
            $chunkTotal = 8 + $padded;

            if ($pos + $chunkTotal > $len) {
                break;
            }

            if (! in_array($chunkId, $metadataChunks, true)) {
                $out .= substr($data, $pos, $chunkTotal);
            }

            $pos += $chunkTotal;
        }

        return $out;
    }

    private function stripPdfMetadata(string $data): string
    {
        $stripped = preg_replace('/<x:xmpmeta[\s\S]*?<\/x:xmpmeta>/', '', $data) ?? $data;
        $stripped = preg_replace('/\/Info\s*<<(?:[^>]|<[^<])*>>/s', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\/Metadata\s+\d+\s+\d+\s+R\s*/', '', $stripped) ?? $stripped;

        return $stripped;
    }
}
