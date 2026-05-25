<?php

namespace App\Core\Support;

class DecimalComparer
{
    public static function differs(mixed $left, mixed $right, int $decimals = 2): bool
    {
        $factor = 10 ** $decimals;

        return (int) round(((float) $left) * $factor) !== (int) round(((float) $right) * $factor);
    }
}
