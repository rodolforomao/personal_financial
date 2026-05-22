<?php

namespace App\Core\Enums;

enum RecurrenceFrequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Semanal',
            self::Biweekly => 'Quinzenal',
            self::Monthly => 'Mensal',
            self::Quarterly => 'Trimestral',
            self::Yearly => 'Anual',
        };
    }

    public function nextDueFrom(\DateTimeInterface|string $from): \Carbon\Carbon
    {
        $date = \Carbon\Carbon::parse($from);

        return match ($this) {
            self::Weekly => $date->addWeek(),
            self::Biweekly => $date->addWeeks(2),
            self::Monthly => $date->addMonth(),
            self::Quarterly => $date->addMonths(3),
            self::Yearly => $date->addYear(),
        };
    }
}
