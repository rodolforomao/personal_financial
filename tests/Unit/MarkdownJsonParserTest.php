<?php

namespace Tests\Unit;

use App\Core\Support\MarkdownJsonParser;
use PHPUnit\Framework\TestCase;

class MarkdownJsonParserTest extends TestCase
{
    public function test_decodes_json_inside_markdown_fence(): void
    {
        $content = <<<'JSON'
```json
{
  "amount": 5000.00,
  "bank": "BANCO INTER"
}
```
JSON;

        $parsed = MarkdownJsonParser::decode($content);

        $this->assertSame(5000.0, $parsed['amount']);
        $this->assertSame('BANCO INTER', $parsed['bank']);
    }
}
