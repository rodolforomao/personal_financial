<?php

namespace Tests\Unit;

use App\Core\Support\LogInterpreter;
use PHPUnit\Framework\TestCase;

class LogInterpreterTest extends TestCase
{
    public function test_interprets_telegram_failure(): void
    {
        $result = (new LogInterpreter)->interpretLogEntry([
            'message' => 'Telegram send failed',
            'level' => 'WARNING',
            'context' => ['description' => 'chat not found'],
        ]);

        $this->assertSame('telegram', $result['category']);
        $this->assertStringContainsString('/start', $result['hint']);
    }
}
