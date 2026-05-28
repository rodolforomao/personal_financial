<?php

namespace App\Core\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Receita',
            self::Expense => 'Despesa',
            self::Transfer => 'Transferência',
        };
    }

    public function numberPrefix(): string
    {
        return match ($this) {
            self::Income => 'R',
            self::Expense => 'D',
            self::Transfer => 'T',
        };
    }
}
