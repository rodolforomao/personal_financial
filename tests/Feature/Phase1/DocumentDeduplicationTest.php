<?php

namespace Tests\Feature\Phase1;

use App\Core\Support\DocumentContentHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\OCR\Application\Services\ReceiptStorageService;
use Tests\Concerns\SeedsFinancialWorkspace;
use Tests\TestCase;

class DocumentDeduplicationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsFinancialWorkspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialWorkspace();
        Storage::fake('local');
    }

    public function test_duplicate_upload_reuses_storage_path(): void
    {
        $bytes = $this->minimalJpegBytes();
        $fileA = UploadedFile::fake()->createWithContent('comprovante-a.jpg', $bytes);
        $fileB = UploadedFile::fake()->createWithContent('comprovante-b.jpg', $bytes);

        $storage = app(ReceiptStorageService::class);

        $docA = $storage->store($this->workspace->id, $this->user, $fileA, queueOcr: false);
        $docB = $storage->store($this->workspace->id, $this->user, $fileB, queueOcr: false);

        $this->assertSame($docA->storage_path, $docB->storage_path);
        $this->assertSame($docA->content_hash, $docB->content_hash);
        $this->assertNotSame($docA->id, $docB->id);
        $this->assertSame('comprovante-a.jpg', $docA->original_name);
        $this->assertSame('comprovante-b.jpg', $docB->original_name);

        $storedFiles = collect(Storage::disk('local')->allFiles())
            ->filter(fn (string $path) => str_starts_with($path, 'documents/'))
            ->values();

        $this->assertCount(1, $storedFiles);
    }

    public function test_deleting_one_duplicate_keeps_physical_file(): void
    {
        $bytes = $this->minimalJpegBytes();
        $storage = app(ReceiptStorageService::class);

        $docA = $storage->store(
            $this->workspace->id,
            $this->user,
            UploadedFile::fake()->createWithContent('a.jpg', $bytes),
            queueOcr: false,
        );
        $docB = $storage->store(
            $this->workspace->id,
            $this->user,
            UploadedFile::fake()->createWithContent('b.jpg', $bytes),
            queueOcr: false,
        );

        $path = $docA->storage_path;

        $storage->deleteFile($docA);
        $docA->delete();

        Storage::disk('local')->assertExists($path);

        $storage->deleteFile($docB);
        $docB->delete();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_same_image_different_exif_hashes_match(): void
    {
        $base = $this->minimalJpegBytes();
        $exifPayload = "Exif\x00\x00fake-meta";
        $withExif = "\xFF\xD8"
            ."\xFF\xE1".pack('n', strlen($exifPayload) + 2).$exifPayload
            .substr($base, 2);

        $hasher = new DocumentContentHasher;
        $pathA = $this->tempFile('plain.jpg', $base);
        $pathB = $this->tempFile('exif.jpg', $withExif);

        $this->assertSame(
            $hasher->hashFile($pathA, 'image/jpeg'),
            $hasher->hashFile($pathB, 'image/jpeg'),
        );
    }

    private function tempFile(string $name, string $bytes): string
    {
        $path = Storage::disk('local')->path('tmp/'.$name);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $bytes);

        return $path;
    }

    private function minimalJpegBytes(): string
    {
        $dqt = "\xFF\xDB\x00\x43\x00".str_repeat("\x11", 64);
        $sof = "\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00";
        $dht = "\xFF\xC4\x00\x1C\x00\x00\x01\x05\x01\x01\x01\x00\x00\x00\x00\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B";
        $sos = "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00";
        $scan = "\x00\x3F";

        return "\xFF\xD8".$dqt.$sof.$dht.$sos.$scan."\xFF\xD9";
    }
}
