<?php

namespace Tests\Unit;

use App\Core\Support\DocumentContentHasher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DocumentContentHasherTest extends TestCase
{
    private DocumentContentHasher $hasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hasher = new DocumentContentHasher;
    }

    #[Test]
    public function test_same_jpeg_different_exif_produces_same_hash(): void
    {
        $base = $this->minimalJpeg();
        $withExif = $this->jpegWithFakeExifSegment($base);

        $pathA = $this->writeTemp('a.jpg', $base);
        $pathB = $this->writeTemp('b.jpg', $withExif);

        $this->assertSame(
            $this->hasher->hashFile($pathA, 'image/jpeg'),
            $this->hasher->hashFile($pathB, 'image/jpeg'),
        );
    }

    #[Test]
    public function test_different_file_content_produces_different_hash(): void
    {
        $pathA = $this->writeTemp('a.jpg', $this->minimalJpeg());
        $pathB = $this->writeTemp('b.jpg', $this->minimalJpeg(0x22));

        $this->assertNotSame(
            $this->hasher->hashFile($pathA, 'image/jpeg'),
            $this->hasher->hashFile($pathB, 'image/jpeg'),
        );
    }

    private function writeTemp(string $name, string $bytes): string
    {
        $path = sys_get_temp_dir().'/doc_hash_test_'.uniqid('', true).'_'.$name;
        file_put_contents($path, $bytes);

        return $path;
    }

    private function minimalJpeg(int $fillByte = 0x11): string
    {
        // SOI + DQT + SOF0 + DHT + SOS + minimal scan + EOI
        $dqt = "\xFF\xDB\x00\x43\x00"
            .str_repeat(chr($fillByte), 64);
        $sof = "\xFF\xC0\x00\x0B\x08\x00\x01\x00\x01\x01\x01\x11\x00";
        $dht = "\xFF\xC4\x00\x1C\x00\x00\x01\x05\x01\x01\x01\x00\x00\x00\x00\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B";
        $sos = "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00";
        $scan = "\x00\x3F";
        $eoi = "\xFF\xD9";

        return "\xFF\xD8".$dqt.$sof.$dht.$sos.$scan.$eoi;
    }

    private function jpegWithFakeExifSegment(string $base): string
    {
        if (! str_starts_with($base, "\xFF\xD8")) {
            return $base;
        }

        $payload = "Exif\x00\x00meta";
        $len = strlen($payload) + 2;
        $exif = "\xFF\xE1".pack('n', $len).$payload;

        return "\xFF\xD8".$exif.substr($base, 2);
    }
}
