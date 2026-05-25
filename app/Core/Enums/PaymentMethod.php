<?php

namespace App\Core\Enums;

enum PaymentMethod: string
{
    case Pix = 'pix';
    case Ted = 'ted';
    case Doc = 'doc';
    case Boleto = 'boleto';
    case Card = 'card';
    case Debit = 'debit';
    case Credit = 'credit';
    case Cash = 'cash';
    case Crypto = 'crypto';
    case Transfer = 'transfer';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'PIX',
            self::Ted => 'TED',
            self::Doc => 'DOC',
            self::Boleto => 'Boleto',
            self::Card => 'Cartão',
            self::Debit => 'Débito',
            self::Credit => 'Crédito',
            self::Cash => 'Dinheiro',
            self::Crypto => 'Cripto',
            self::Transfer => 'Transferência',
            self::Other => 'Outro',
        };
    }

    public static function tryFromReceiptType(?string $receiptType): ?self
    {
        if ($receiptType === null || $receiptType === '') {
            return null;
        }

        return match ($receiptType) {
            'pix', 'pix_sent', 'pix_received' => self::Pix,
            'boleto' => self::Boleto,
            'card' => self::Card,
            'transfer' => self::Ted,
            default => null,
        };
    }
}
