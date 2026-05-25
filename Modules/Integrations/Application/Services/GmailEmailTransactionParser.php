<?php

namespace Modules\Integrations\Application\Services;

use App\Core\Enums\PaymentMethod;
use App\Core\Enums\TransactionType;
use App\Core\Support\BrazilianAmountParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GmailEmailTransactionParser
{
    public function __construct(
        protected BrazilianAmountParser $amountParser,
    ) {}

    /**
     * @return array{
     *     type: TransactionType,
     *     amount: float,
     *     description: string,
     *     transaction_date: string,
     *     counterparty: ?string,
     *     payment_method: ?string,
     *     raw_text: string,
     *     subject: string,
     *     from: string
     * }|null
     */
    public function parse(array $message): ?array
    {
        $headers = $this->headers($message);
        $subject = $headers['subject'] ?? '(sem assunto)';
        $from = $headers['from'] ?? '';
        $body = $this->bodyText($message);
        $haystack = trim($subject."\n".$from."\n".$body."\n".($message['snippet'] ?? ''));

        $type = $this->detectType($haystack);
        if (! $type) {
            return null;
        }

        $amount = $this->amountParser->extractBestFromText($haystack);
        if ($amount === null || $amount <= 0) {
            return null;
        }

        return [
            'type' => $type,
            'amount' => $amount,
            'description' => $this->description($subject, $body),
            'transaction_date' => $this->date($message, $headers),
            'counterparty' => $this->counterparty($from, $subject),
            'payment_method' => $this->paymentMethod($haystack),
            'raw_text' => Str::limit($haystack, 4000),
            'subject' => Str::limit($subject, 500),
            'from' => Str::limit($from, 500),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function headers(array $message): array
    {
        $headers = [];
        foreach (data_get($message, 'payload.headers', []) as $header) {
            $name = Str::lower((string) ($header['name'] ?? ''));
            if ($name !== '') {
                $headers[$name] = (string) ($header['value'] ?? '');
            }
        }

        return $headers;
    }

    protected function bodyText(array $message): string
    {
        $parts = [];
        $this->collectBodyParts($message['payload'] ?? [], $parts);

        return trim(html_entity_decode(strip_tags(implode("\n", $parts))));
    }

    /**
     * @param  list<string>  $parts
     */
    protected function collectBodyParts(array $payload, array &$parts): void
    {
        $mime = (string) ($payload['mimeType'] ?? '');
        $data = data_get($payload, 'body.data');

        if (is_string($data) && in_array($mime, ['text/plain', 'text/html'], true)) {
            $decoded = $this->base64UrlDecode($data);
            if ($decoded !== '') {
                $parts[] = $decoded;
            }
        }

        foreach (($payload['parts'] ?? []) as $part) {
            if (is_array($part)) {
                $this->collectBodyParts($part, $parts);
            }
        }
    }

    protected function base64UrlDecode(string $value): string
    {
        $normalized = strtr($value, '-_', '+/');
        $normalized .= str_repeat('=', (4 - strlen($normalized) % 4) % 4);

        return base64_decode($normalized, true) ?: '';
    }

    protected function detectType(string $text): ?TransactionType
    {
        $lower = Str::lower($text);

        if (preg_match('/\b(recebido|recebimento|cr[eé]dito|creditado|pix recebido|dep[oó]sito recebido|pagamento recebido|venda aprovada)\b/u', $lower)) {
            return TransactionType::Income;
        }

        if (preg_match('/\b(compra|gasto|despesa|d[eé]bito|debitado|pagamento enviado|pix enviado|boleto pago|cart[aã]o|fatura|nota fiscal)\b/u', $lower)) {
            return TransactionType::Expense;
        }

        return null;
    }

    protected function description(string $subject, string $body): string
    {
        $subject = trim($subject);
        if ($subject !== '' && $subject !== '(sem assunto)') {
            return Str::limit($subject, 500);
        }

        $line = trim((string) Str::of($body)->replaceMatches('/\s+/', ' '));

        return Str::limit($line !== '' ? $line : 'Transação importada do Gmail', 500);
    }

    protected function date(array $message, array $headers): string
    {
        if (! empty($message['internalDate'])) {
            return Carbon::createFromTimestampMs((int) $message['internalDate'])->toDateString();
        }

        if (! empty($headers['date'])) {
            try {
                return Carbon::parse($headers['date'])->toDateString();
            } catch (\Throwable) {
                // fallback abaixo
            }
        }

        return now()->toDateString();
    }

    protected function counterparty(string $from, string $subject): ?string
    {
        if (preg_match('/^"?([^"<]+)"?\s*</u', $from, $match)) {
            return trim($match[1]) ?: null;
        }

        if (preg_match('/([A-Z0-9._%+\-]+)@([A-Z0-9.\-]+)/iu', $from, $match)) {
            return $match[2];
        }

        return trim($subject) ?: null;
    }

    protected function paymentMethod(string $text): ?string
    {
        $lower = Str::lower($text);

        return match (true) {
            str_contains($lower, 'pix') => PaymentMethod::Pix->value,
            str_contains($lower, 'boleto') => PaymentMethod::Boleto->value,
            str_contains($lower, 'cartão') || str_contains($lower, 'cartao') || str_contains($lower, 'card') => PaymentMethod::Card->value,
            str_contains($lower, 'ted') => PaymentMethod::Ted->value,
            default => null,
        };
    }
}
