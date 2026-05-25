<?php

namespace App\Core\Exceptions;

use RuntimeException;

class AiUnavailableException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('ai_not_configured');
    }

    public static function platformBillingNotAccepted(): self
    {
        return new self('ai_platform_billing_not_accepted');
    }

    public static function invalidKey(): self
    {
        return new self('ai_invalid_key');
    }

    public static function providerError(string $detail): self
    {
        return new self('ai_provider_error:'.$detail);
    }

    public function userMessage(): string
    {
        return match ($this->getMessage()) {
            'ai_not_configured' => 'A IA não está configurada. Use sua própria API key em Inteligência → Configuração IA ou fale com o suporte para ativar a IA da plataforma.',
            'ai_platform_billing_not_accepted' => 'Para usar a IA da plataforma, aceite os termos de cobrança em Inteligência → Configuração IA (modo "IA da plataforma").',
            'ai_invalid_key' => 'A API key informada é inválida ou expirou. Atualize em Inteligência → Configuração IA.',
            default => str_starts_with($this->getMessage(), 'ai_provider_error:')
                ? 'Não foi possível contactar o provedor de IA: '.substr($this->getMessage(), strlen('ai_provider_error:'))
                : 'Serviço de IA temporariamente indisponível. Tente novamente ou use a resposta local abaixo.',
        };
    }
}
