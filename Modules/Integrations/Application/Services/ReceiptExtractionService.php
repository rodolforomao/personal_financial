<?php

namespace Modules\Integrations\Application\Services;

use App\Core\DTOs\OcrRequestData;
use App\Core\Enums\FundingSource;
use App\Core\Enums\PaymentMethod;
use App\Core\Enums\TransactionType;
use App\Core\Support\BrazilianAmountParser;
use App\Core\Support\PdfFirstPageRenderer;
use RuntimeException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Application\Services\ReceiptCaptionContextResolver;
use Modules\Operations\Infrastructure\Models\Operation;
use Modules\OCR\Application\Services\OcrProviderManager;

class ReceiptExtractionService
{
    public function __construct(
        protected OcrProviderManager $ocrManager,
        protected BrazilianAmountParser $amountParser,
        protected ReceiptClassifier $classifier,
        protected PdfFirstPageRenderer $pdfRenderer,
        protected ReceiptCaptionContextResolver $captionContext,
        protected ReceiptDraftSupplementParser $supplementParser,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function extractFromFile(
        string $filePath,
        string $mimeType,
        int $workspaceId,
        ?string $originalFileName = null,
    ): array {
        if ($this->isXml($mimeType, $filePath, $originalFileName)) {
            return $this->extractFromFiscalXml($filePath, $originalFileName);
        }

        $ocrPath = $filePath;
        $ocrMime = $mimeType;
        $tempPng = null;

        if ($this->isPdf($mimeType, $filePath)) {
            $tempPng = $this->pdfRenderer->toPng($filePath);
            $ocrPath = $tempPng;
            $ocrMime = 'image/png';
        }

        try {
            $provider = $this->preferredProvider();
            $result = $this->ocrManager->driver($provider)->extract(new OcrRequestData(
                filePath: $ocrPath,
                mimeType: $ocrMime,
                workspaceId: $workspaceId,
            ));

            if ($provider === 'tesseract' && ! empty(config('financial.ai.openai.api_key'))) {
                try {
                    $vision = $this->ocrManager->driver('vision')->extract(new OcrRequestData(
                        filePath: $ocrPath,
                        mimeType: $ocrMime,
                        workspaceId: $workspaceId,
                    ));
                    if (($vision->confidence ?? 0) >= ($result->confidence ?? 0)) {
                        $result = $vision;
                    }
                } catch (\Throwable $e) {
                    Log::info('Vision OCR fallback skipped', ['message' => $e->getMessage()]);
                }
            }

            $entities = is_array($result->entities) ? $result->entities : [];

            return $this->normalize([
                'amount' => $result->amount,
                'date' => $result->date,
                'counterparty' => $result->counterparty,
                'bank' => $result->bank,
                'suggested_category' => $result->suggestedCategory,
                'confidence' => $result->confidence,
                'entities' => $entities,
            ], $result->rawText, $originalFileName);
        } finally {
            if ($tempPng !== null && is_file($tempPng)) {
                @unlink($tempPng);
            }
        }
    }

    protected function isPdf(string $mimeType, string $filePath): bool
    {
        return str_contains(strtolower($mimeType), 'pdf')
            || str_ends_with(strtolower($filePath), '.pdf');
    }

    protected function isXml(string $mimeType, string $filePath, ?string $originalFileName = null): bool
    {
        $mime = strtolower($mimeType);
        $path = strtolower($filePath);
        $name = strtolower((string) $originalFileName);

        if (str_contains($mime, 'xml') || str_ends_with($path, '.xml') || str_ends_with($name, '.xml')) {
            return true;
        }

        $handle = @fopen($filePath, 'rb');
        if (! $handle) {
            return false;
        }

        $prefix = fread($handle, 120) ?: '';
        fclose($handle);

        return str_contains(ltrim($prefix), '<?xml') || str_contains($prefix, '<nfeProc');
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractFromFiscalXml(string $filePath, ?string $originalFileName = null): array
    {
        $xmlText = file_get_contents($filePath) ?: '';
        if (trim($xmlText) === '') {
            throw new RuntimeException('XML vazio ou ilegível.');
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlText);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $xml) {
            throw new RuntimeException('XML de nota fiscal inválido.');
        }

        $inf = $this->firstXmlNode($xml, 'infNFe');
        $emit = $this->firstXmlNode($xml, 'emit');
        $ide = $this->firstXmlNode($xml, 'ide');
        $total = $this->firstXmlNode($xml, 'ICMSTot');
        $pag = $this->firstXmlNode($xml, 'detPag');
        $prot = $this->firstXmlNode($xml, 'infProt');

        $items = [];
        foreach ($xml->xpath('//*[local-name()="det"]/*[local-name()="prod"]') ?: [] as $prod) {
            $name = trim((string) ($prod->xProd ?? ''));
            if ($name !== '') {
                $items[] = $name;
            }
        }

        $issuer = trim((string) ($emit?->xNome ?? ''));
        $amount = $this->amountParser->parse((string) ($total?->vNF ?? '')) ?? 0.0;
        $date = $this->parseDate((string) ($ide?->dhEmi ?? '')) ?? now()->toDateString();
        $invoiceNumber = trim((string) ($ide?->nNF ?? ''));
        $accessKey = trim((string) ($prot?->chNFe ?? ''));
        $description = $items !== []
            ? implode('; ', array_slice($items, 0, 3))
            : ($issuer !== '' ? 'Nota fiscal '.$issuer : 'Nota fiscal');

        $paymentMethod = match ((string) ($pag?->tPag ?? '')) {
            '03', '04' => PaymentMethod::Card->value,
            '17' => PaymentMethod::Pix->value,
            '15', '16' => PaymentMethod::Boleto->value,
            '01' => PaymentMethod::Cash->value,
            default => PaymentMethod::Other->value,
        };

        $suggested = $this->suggestCategoryFromInvoice($description.' '.$issuer.' '.($originalFileName ?? ''));

        return [
            'type' => TransactionType::Expense->value,
            'amount' => $amount,
            'date' => $date,
            'description' => mb_substr($description, 0, 500),
            'counterparty' => $issuer ?: null,
            'bank' => null,
            'bank_slug' => null,
            'funding_source' => FundingSource::Other->value,
            'payment_method' => $paymentMethod,
            'receipt_type' => 'invoice',
            'receipt_type_label' => 'Nota fiscal eletrônica',
            'suggested_category' => $suggested,
            'confidence' => 1.0,
            'raw_text' => Str::limit($xmlText, 4000),
            'ocr_provider' => 'xml',
            'document_type' => 'invoice',
            'invoice' => [
                'number' => $invoiceNumber ?: null,
                'access_key' => $accessKey ?: null,
                'issuer' => $issuer ?: null,
                'items' => $items,
            ],
        ];
    }

    protected function firstXmlNode(\SimpleXMLElement $xml, string $localName): ?\SimpleXMLElement
    {
        $nodes = $xml->xpath('//*[local-name()="'.$localName.'"]') ?: [];

        return $nodes[0] ?? null;
    }

    protected function suggestCategoryFromInvoice(string $text): ?string
    {
        $lower = Str::lower($text);
        $patterns = config('financial.default_categorization_patterns', []);
        $patterns = is_array($patterns) ? $patterns : [];

        foreach ($patterns as $pattern => $slug) {
            if (str_contains($lower, Str::lower($pattern))) {
                return (string) $slug;
            }
        }

        if (preg_match('/\b(t[eê]nis|sapato|cal[cç]ado|adidas|nike)\b/u', $lower)) {
            return 'compras';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $ocr
     * @return array<string, mixed>
     */
    protected function normalize(array $ocr, string $rawText, ?string $originalFileName = null): array
    {
        $filenameHint = $originalFileName
            ? $this->amountParser->hintFromFilename($originalFileName)
            : null;

        $entities = $ocr['entities'] ?? [];
        $structured = is_array($entities)
            && ! isset($entities['lines'])
            && (isset($entities['amount']) || isset($entities['description']) || isset($entities['bank']));

        if ($structured) {
            $amount = $this->amountParser->parse($entities['amount'] ?? null);
            $date = $this->parseDate($entities['date'] ?? null);
            $type = $this->detectType($entities['type'] ?? null, $rawText);
            $description = trim((string) ($entities['description'] ?? $entities['counterparty'] ?? ''));
            $counterparty = $entities['counterparty'] ?? null;
            $bank = $entities['bank'] ?? null;
            $suggestedCategory = $entities['suggested_category'] ?? null;
        } else {
            $amount = $this->amountParser->parse($ocr['amount'] ?? null);
            $date = $this->parseDate($ocr['date'] ?? null);
            $type = $this->detectType($ocr['type'] ?? null, $rawText);
            $description = trim((string) ($ocr['counterparty'] ?? $ocr['description'] ?? ''));
            $counterparty = $ocr['counterparty'] ?? null;
            $bank = $ocr['bank'] ?? null;
            $suggestedCategory = $ocr['suggested_category'] ?? $ocr['suggestedCategory'] ?? null;
        }

        $bestFromText = $this->amountParser->extractBestFromText($rawText, $filenameHint);
        $amount = $this->reconcileAmount($amount, $bestFromText, $filenameHint);

        if ($description === '' || mb_strlen($description) < 3) {
            $description = $this->extractDescriptionFromText($rawText) ?: 'Comprovante via mensagem';
        }

        $classification = $this->classifier->classify($rawText, $bank);
        $entityPayload = $structured ? $entities : (is_array($ocr['entities'] ?? null) ? $ocr['entities'] : null);
        $type = $this->resolveTypeFromReceipt($type, $classification['receipt_type'], $entityPayload, $rawText);

        $bankSlug = $classification['bank_slug'] ?? null;

        return [
            'type' => $type->value,
            'amount' => $amount ?? 0.0,
            'date' => $date ?? now()->toDateString(),
            'description' => mb_substr($description, 0, 500),
            'counterparty' => $counterparty,
            'bank' => $classification['bank'] ?? $bank,
            'bank_slug' => $bankSlug,
            'funding_source' => FundingSource::tryFromBankSlug($bankSlug)?->value
                ?? FundingSource::tryFromBankName($classification['bank'] ?? $bank)?->value,
            'payment_method' => PaymentMethod::tryFromReceiptType($classification['receipt_type'] ?? null)?->value,
            'receipt_type' => $classification['receipt_type'],
            'receipt_type_label' => $classification['receipt_type_label'],
            'suggested_category' => $suggestedCategory,
            'confidence' => (float) ($ocr['confidence'] ?? 0),
            'raw_text' => Str::limit($rawText, 4000),
            'ocr_provider' => $this->preferredProvider(),
            'filename_amount_hint' => $filenameHint,
        ];
    }

    protected function reconcileAmount(?float $structured, ?float $fromText, ?float $filenameHint): ?float
    {
        if ($structured === null) {
            return $fromText;
        }

        if ($fromText === null) {
            return $structured;
        }

        if (abs($structured - $fromText) < 0.01) {
            return $structured;
        }

        if ($filenameHint !== null) {
            if (abs($fromText - $filenameHint) < abs($structured - $filenameHint)) {
                return $fromText;
            }
            if (abs($structured - $filenameHint) < 0.01) {
                return $structured;
            }
        }

        if ($fromText > $structured && $structured < 1000 && $fromText >= 1000) {
            return $fromText;
        }

        return max($structured, $fromText);
    }

    protected function preferredProvider(): string
    {
        if (! empty(config('financial.ai.openai.api_key'))) {
            return 'vision';
        }

        return (string) config('financial.ocr.default', 'tesseract');
    }

    /**
     * Atualiza rascunho com campos explícitos ou legenda livre (Telegram/WhatsApp).
     *
     * @param  array<string, mixed>  $extracted
     * @return array{extracted: array<string, mixed>, changed: bool}
     */
    public function applyDraftSupplement(array $extracted, string $text, int $workspaceId): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['extracted' => $extracted, 'changed' => false];
        }

        $fields = $this->supplementParser->parse($text);
        if ($this->supplementParser->hasAnyField($fields)) {
            $merged = $this->mergeSupplementFields($extracted, $fields, $workspaceId);
            $merged['supplement_from_user'] = true;

            return ['extracted' => $merged, 'changed' => true];
        }

        $merged = $this->applyUserCaption($extracted, $text, $workspaceId);
        $merged['supplement_from_user'] = true;

        return ['extracted' => $merged, 'changed' => true];
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @param  array{
     *     counterparty: ?string,
     *     category: ?string,
     *     company: ?string,
     *     operation: ?string,
     *     unit: ?string,
     *     description: ?string,
     *     type: ?string,
     *     bank: ?string,
     *     funding_source: ?string,
     *     payment_method: ?string
     * }  $fields
     * @return array<string, mixed>
     */
    protected function mergeSupplementFields(array $extracted, array $fields, int $workspaceId): array
    {
        if (! empty($fields['counterparty'])) {
            $extracted['counterparty'] = mb_substr($fields['counterparty'], 0, 255);
        }

        if (! empty($fields['description'])) {
            $extracted['description'] = mb_substr($fields['description'], 0, 500);
        }

        if (! empty($fields['type'])) {
            $lower = mb_strtolower($fields['type']);
            if (preg_match('/\b(receita|income|entrada)\b/u', $lower)) {
                $extracted['type'] = TransactionType::Income->value;
            } elseif (preg_match('/\b(gasto|expense|despesa|sa[ií]da)\b/u', $lower)) {
                $extracted['type'] = TransactionType::Expense->value;
            }
        }

        if (! empty($fields['category'])) {
            $category = $this->captionContext->findCategoryByName($workspaceId, $fields['category']);
            if ($category) {
                $extracted['category_id'] = $category->id;
                $extracted['category_name'] = $category->name;
                $extracted['suggested_category'] = $category->slug;
            } else {
                $extracted['suggested_category'] = Str::slug($fields['category']);
                $extracted['category_name'] = $fields['category'];
                unset($extracted['category_id']);
            }
        }

        $company = null;
        if (! empty($fields['company'])) {
            $company = $this->captionContext->findCompanyByName($workspaceId, $fields['company']);
            if ($company) {
                $extracted['company_id'] = $company->id;
                $extracted['company_name'] = $company->name;
            } else {
                $extracted['company_name'] = $fields['company'];
                unset($extracted['company_id']);
            }
        }

        if (! empty($fields['operation'])) {
            $operation = $this->captionContext->findOperationByName($workspaceId, $fields['operation'], $company);
            if ($operation) {
                $extracted['operation_id'] = $operation->id;
                $extracted['operation_name'] = $operation->name;
                if (! isset($extracted['company_id']) && $operation->company_id) {
                    $extracted['company_id'] = $operation->company_id;
                    $extracted['company_name'] = $operation->company?->name;
                }
            } else {
                $extracted['operation_name'] = $fields['operation'];
                unset($extracted['operation_id']);
            }
        }

        if (! empty($fields['unit']) && empty($extracted['operation_unit_id'])) {
            $operation = isset($extracted['operation_id'])
                ? Operation::query()->find($extracted['operation_id'])
                : null;
            if ($operation) {
                $unit = $this->captionContext->findOperationUnit($operation, $fields['unit']);
                if ($unit) {
                    $extracted['operation_unit_id'] = $unit->id;
                    $extracted['operation_unit_name'] = $unit->displayName();
                }
            }
        }

        if (! empty($fields['bank'])) {
            $extracted['bank'] = mb_substr($fields['bank'], 0, 120);
        }

        if (! empty($fields['funding_source'])) {
            $fundingSource = $this->resolveFundingSourceFromText($fields['funding_source']);
            $extracted['funding_source'] = $fundingSource?->value ?? FundingSource::Other->value;
            if (empty($extracted['bank'])) {
                $extracted['bank'] = $fundingSource && $fundingSource !== FundingSource::Other
                    ? $fundingSource->label()
                    : mb_substr($fields['funding_source'], 0, 120);
            }
        }

        if (! empty($fields['payment_method'])) {
            $extracted['payment_method'] = $this->resolvePaymentMethodFromText($fields['payment_method'])->value;
        }

        return $this->applyPersonalExpenseDefaults($extracted, implode(' ', array_filter($fields)), $workspaceId);
    }

    public function applyUserCaption(array $extracted, string $caption, int $workspaceId): array
    {
        $caption = trim($caption);
        if ($caption === '') {
            return $extracted;
        }

        $extracted['description'] = mb_substr($caption, 0, 500);
        $extracted['caption_from_user'] = true;

        $lower = mb_strtolower($caption);

        if (preg_match('/\b(recebimento|receita|recebi|recebido|venda|vendi|entrada|pagamento\s+recebido|valor\s+recebido)\b/u', $lower)) {
            $extracted['type'] = TransactionType::Income->value;
            $extracted['receipt_type'] = $extracted['receipt_type'] ?? 'pix_received';
            $extracted['receipt_type_label'] = 'PIX recebido (legenda)';
        } elseif (preg_match('/\b(gasto|despesa|despeza|paguei|pagamento\s+enviado|compra|pix\s+enviado)\b/u', $lower)) {
            $extracted['type'] = TransactionType::Expense->value;
        }

        $context = $this->captionContext->resolve($workspaceId, $caption);
        if ($context['category_id']) {
            $extracted['category_id'] = $context['category_id'];
            $extracted['suggested_category'] = $context['category_slug'];
            $extracted['category_name'] = $context['category_name'];
        } elseif ($context['category_slug']) {
            $extracted['suggested_category'] = $context['category_slug'];
        }
        if ($context['company_id']) {
            $extracted['company_id'] = $context['company_id'];
            $extracted['company_name'] = $context['company_name'];
        }
        if ($context['operation_id']) {
            $extracted['operation_id'] = $context['operation_id'];
            $extracted['operation_name'] = $context['operation_name'];
        }
        if ($context['operation_unit_id']) {
            $extracted['operation_unit_id'] = $context['operation_unit_id'];
            $extracted['operation_unit_name'] = $context['operation_unit_name'];
        }

        return $this->applyPersonalExpenseDefaults($extracted, $caption, $workspaceId);
    }

    /**
     * Regra de negócio: "despesa/despeza pessoal" sempre entra na Pessoa Física,
     * Operação Geral e como despesa, mesmo quando o comprovante sugere outro contexto.
     *
     * @param  array<string, mixed>  $extracted
     * @return array<string, mixed>
     */
    protected function applyPersonalExpenseDefaults(array $extracted, string $text, int $workspaceId): array
    {
        if (! $this->isPersonalExpenseText($text)) {
            return $extracted;
        }

        $extracted['type'] = TransactionType::Expense->value;

        $company = $this->captionContext->findCompanyByName($workspaceId, 'Pessoa Física');
        if ($company) {
            $extracted['company_id'] = $company->id;
            $extracted['company_name'] = $company->name;
        } else {
            $extracted['company_name'] = 'Pessoa Física';
            unset($extracted['company_id']);
        }

        $operation = $this->captionContext->findOperationByName($workspaceId, 'Geral', $company)
            ?? $this->captionContext->findOperationByName($workspaceId, 'Geral (pessoa física)', $company);

        if ($operation) {
            $extracted['operation_id'] = $operation->id;
            $extracted['operation_name'] = $operation->name;
        } else {
            $extracted['operation_name'] = 'Geral';
            unset($extracted['operation_id']);
        }

        unset($extracted['operation_unit_id'], $extracted['operation_unit_name']);

        return $extracted;
    }

    protected function isPersonalExpenseText(string $text): bool
    {
        $lower = mb_strtolower($text);

        return (bool) preg_match('/\b(despesa|despesas|despeza|despezas)\b/u', $lower)
            && (bool) preg_match('/\b(pessoal|pessoais|pessoa\s+f[ií]sica)\b/u', $lower);
    }

    protected function resolveFundingSourceFromText(string $text): ?FundingSource
    {
        $value = Str::slug($text, '_');

        return FundingSource::tryFrom($value)
            ?? FundingSource::tryFromBankSlug($value)
            ?? FundingSource::tryFromBankName($text);
    }

    protected function resolvePaymentMethodFromText(string $text): PaymentMethod
    {
        $lower = mb_strtolower($text);
        $value = Str::slug($text, '_');

        return PaymentMethod::tryFrom($value) ?? match (true) {
            str_contains($lower, 'pix') => PaymentMethod::Pix,
            str_contains($lower, 'boleto') => PaymentMethod::Boleto,
            str_contains($lower, 'ted') => PaymentMethod::Ted,
            str_contains($lower, 'doc') => PaymentMethod::Doc,
            str_contains($lower, 'debito') || str_contains($lower, 'débito') => PaymentMethod::Debit,
            str_contains($lower, 'credito') || str_contains($lower, 'crédito') => PaymentMethod::Credit,
            str_contains($lower, 'cartao') || str_contains($lower, 'cartão') => PaymentMethod::Card,
            str_contains($lower, 'dinheiro') || str_contains($lower, 'cash') => PaymentMethod::Cash,
            str_contains($lower, 'cripto') || str_contains($lower, 'crypto') => PaymentMethod::Crypto,
            str_contains($lower, 'transfer') => PaymentMethod::Transfer,
            default => PaymentMethod::Other,
        };
    }

    protected function detectType(?string $explicit, string $rawText): TransactionType
    {
        $lower = mb_strtolower(($explicit ?? '').' '.$rawText);

        if (preg_match('/\b(receita|recebi|crédito|credito|entrada|pix recebido|transferência recebida)\b/u', $lower)) {
            return TransactionType::Income;
        }

        if (preg_match('/\b(expense|despesa|gasto|pix enviado|transferência enviada)\b/u', $lower)) {
            return TransactionType::Expense;
        }

        if (preg_match('/\bincome\b/u', $lower)) {
            return TransactionType::Income;
        }

        return TransactionType::Expense;
    }

    /**
     * @param  array<string, mixed>|null  $entities
     */
    protected function resolveTypeFromReceipt(
        TransactionType $type,
        string $receiptType,
        ?array $entities,
        string $rawText,
    ): TransactionType {
        if (is_array($entities)) {
            $direction = mb_strtolower((string) ($entities['transfer_direction'] ?? ''));
            if ($direction === 'received') {
                return TransactionType::Income;
            }
            if ($direction === 'sent') {
                return TransactionType::Expense;
            }
        }

        return match ($receiptType) {
            'pix_received' => TransactionType::Income,
            'pix_sent' => TransactionType::Expense,
            default => $type,
        };
    }

    protected function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            if (preg_match('/\d{4}-\d{2}-\d{2}/', $value, $m)) {
                return Carbon::parse($m[0])->toDateString();
            }

            if (preg_match('/\d{2}\/\d{2}\/\d{4}/', $value, $m)) {
                return Carbon::createFromFormat('d/m/Y', $m[0])->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function extractDescriptionFromText(string $text): string
    {
        if (preg_match('/"description"\s*:\s*"([^"]+)"/u', $text, $m)) {
            return Str::limit(trim($m[1]), 200);
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: [])));
        foreach ($lines as $line) {
            if (mb_strlen($line) >= 8 && ! preg_match('/^(r\$|total|valor|data|pix|\{|"type")/iu', $line)) {
                return Str::limit($line, 200);
            }
        }

        return '';
    }
}
