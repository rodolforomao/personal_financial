<?php

namespace App\Core\Enums;

enum FundingSource: string
{
    case Nubank = 'nubank';
    case Inter = 'inter';
    case Sideswap = 'sideswap';
    case Wise = 'wise';
    case Itau = 'itau';
    case Bradesco = 'bradesco';
    case Santander = 'santander';
    case Caixa = 'caixa';
    case Bb = 'bb';
    case Stone = 'stone';
    case C6 = 'c6';
    case MercadoPago = 'mercado_pago';
    case Picpay = 'picpay';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Nubank => 'Nubank',
            self::Inter => 'Banco Inter',
            self::Sideswap => 'Sideswap',
            self::Wise => 'Wise',
            self::Itau => 'Itaú',
            self::Bradesco => 'Bradesco',
            self::Santander => 'Santander',
            self::Caixa => 'Caixa',
            self::Bb => 'Banco do Brasil',
            self::Stone => 'Stone',
            self::C6 => 'C6 Bank',
            self::MercadoPago => 'Mercado Pago',
            self::Picpay => 'PicPay',
            self::Cash => 'Dinheiro / caixa',
            self::Other => 'Outro',
        };
    }

    public static function tryFromBankSlug(?string $slug): ?self
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return match ($slug) {
            'nubank' => self::Nubank,
            'banco_inter', 'inter' => self::Inter,
            'sideswap' => self::Sideswap,
            'wise' => self::Wise,
            'itau' => self::Itau,
            'bradesco' => self::Bradesco,
            'santander' => self::Santander,
            'caixa' => self::Caixa,
            'bb' => self::Bb,
            'stone' => self::Stone,
            'c6' => self::C6,
            'mercado_pago' => self::MercadoPago,
            'picpay' => self::Picpay,
            default => self::Other,
        };
    }

    public static function tryFromBankName(?string $name): ?self
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $lower = mb_strtolower($name);

        return match (true) {
            str_contains($lower, 'nubank') => self::Nubank,
            str_contains($lower, 'inter') => self::Inter,
            str_contains($lower, 'sideswap') => self::Sideswap,
            str_contains($lower, 'wise') => self::Wise,
            str_contains($lower, 'itau') || str_contains($lower, 'itaú') => self::Itau,
            str_contains($lower, 'bradesco') => self::Bradesco,
            str_contains($lower, 'santander') => self::Santander,
            str_contains($lower, 'caixa') => self::Caixa,
            str_contains($lower, 'banco do brasil') => self::Bb,
            str_contains($lower, 'stone') => self::Stone,
            str_contains($lower, 'c6') => self::C6,
            str_contains($lower, 'mercado pago') => self::MercadoPago,
            str_contains($lower, 'picpay') => self::Picpay,
            default => self::Other,
        };
    }
}
