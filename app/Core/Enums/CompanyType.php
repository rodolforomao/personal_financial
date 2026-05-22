<?php

namespace App\Core\Enums;

enum CompanyType: string
{
    /** Empresa que você controla / é dono */
    case Own = 'own';
    /** Empresa na qual você é sócio */
    case Partner = 'partner';
    /** Empresa que paga você (cliente, empregador PJ, etc.) */
    case Payer = 'payer';
    /** @deprecated use Payer — mantido para dados antigos */
    case Client = 'client';
    case Employer = 'employer';
    case Investment = 'investment';

    public function label(): string
    {
        return match ($this) {
            self::Own => 'Minha empresa',
            self::Partner => 'Sou sócio',
            self::Payer, self::Client => 'Me paga',
            self::Employer => 'Empregador',
            self::Investment => 'Investimento',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Own => 'Negócio seu, CNPJ/operacao própria',
            self::Partner => 'Participação societária, dividendos ou pró-labore',
            self::Payer, self::Client => 'Fonte de receita — contratos, salários, clientes',
            self::Employer => 'CLT ou vínculo empregatício',
            self::Investment => 'Veículo de investimento ou holding',
        };
    }

    /** Tipos exibidos no formulário web */
    public static function forForm(): array
    {
        return [self::Own, self::Partner, self::Payer];
    }
}
