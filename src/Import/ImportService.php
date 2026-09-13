<?php

declare(strict_types=1);

namespace Upp\Import;

use Throwable;
use Upp\Domain\ProductRecord;
use Upp\Logging\ImportLogger;
use Upp\Mapping\BrandMapper;
use Upp\Mapping\CategoryMapper;
use Upp\WooCommerce\GatewayException;
use Upp\WooCommerce\WooCommerceGatewayInterface;

final class ImportService
{
    /** @var array<int, true> */
    private array $updatedVariationParentIds = [];

    public function __construct(
        private readonly JsonFileReader $reader,
        private readonly JsonValidator $validator,
        private readonly ProductRecordNormalizer $normalizer,
        private readonly ProductPayloadFactory $payloadFactory,
        private readonly CategoryMapper $categoryMapper,
        private readonly BrandMapper $brandMapper,
        private readonly WooCommerceGatewayInterface $gateway,
        private readonly ?ImportLogger $logger = null,
        private readonly string $unknownCategoryBehavior = 'skip_category',
        private readonly string $unknownBrandBehavior = 'continue',
    ) {
    }

    /** @return array{summary: array<string, mixed>, results: list<ImportResult>} */
    public function import(string $path, ?callable $onResult = null): array
    {
        $startedAt = gmdate(DATE_ATOM);
        $raw = $this->reader->read($path);
        $this->validator->validate($raw);
        $records = [];
        foreach ($raw as $index => $item) {
            $records[] = $this->normalizer->normalize($item, $index);
        }

        $this->updatedVariationParentIds = [];

        // Nijedan gateway poziv ne smije se dogoditi prije potpune validacije/normalizacije.
        $this->gateway->checkConnection();
        $results = [];
        foreach ($records as $record) {
            $result = $this->process($record);
            $results[] = $result;
            $this->logger?->result($result);
            if ($onResult !== null) {
                $onResult($result);
            }
        }
        $this->updateVariationParentStocks();

        $summary = $this->summarize($results, count($records), $startedAt, gmdate(DATE_ATOM));
        $this->logger?->summary($summary);
        return ['summary' => $summary, 'results' => $results];
    }

    private function process(ProductRecord $record): ImportResult
    {
        $warnings = [];
        try {
            $existing = $this->gateway->resolveProductBySku($record->sku);
            if (!$record->active && $record->totalStock > 0) {
                return $this->result($record, 'skipped_with_warning', $existing['id'] ?? null, null, 'Neaktivan proizvod ima pozitivnu zalihu; nije promijenjen.');
            }
            if (!$record->active && $record->totalStock == 0.0 && $existing === null) {
                return $this->result($record, 'skip', null, null, 'Neaktivan proizvod bez zalihe ne postoji; kreiranje je preskočeno.');
            }

            $retiring = !$record->active && $record->totalStock == 0.0;
            // Povlačenje postojećeg proizvoda ne smije ovisiti o ERP mapiranjima.
            $categoryIds = $retiring ? [] : $this->categories($record, $warnings);
            if ($categoryIds === null) {
                return $this->result($record, 'skipped_with_warning', $existing['id'] ?? null, null, implode(' ', $warnings));
            }
            $brandName = $this->brandMapper->nameFor($record->brandCode);
            if ($record->brandCode !== null && $brandName === null) {
                $warnings[] = "Nepoznat brand {$record->brandCode}; atribut Proizvođač nije upisan.";
                if (!$retiring && $this->unknownBrandBehavior === 'skip_product') {
                    return $this->result($record, 'skipped_with_warning', $existing['id'] ?? null, null, implode(' ', $warnings));
                }
            }

            if ($existing === null) {
                $createPayload = $this->payloadFactory->create($record, $categoryIds, $brandName);
                $response = $record->parentProductId !== null
                    ? (new VariationService($this->gateway))->create($record, $createPayload)
                    : $this->gateway->createProduct($createPayload, $record->sku);
                return $this->result($record, 'create', isset($response['id']) ? (int) $response['id'] : null, 201, $this->message('Proizvod je kreiran.', $warnings));
            }

            $payload = $this->payloadFactory->update($record, $categoryIds, $brandName);
            if (($existing['type'] ?? '') === 'variation') {
                $response = (new VariationService($this->gateway))->update($record, $existing, $payload);
                $this->updatedVariationParentIds[(int) $existing['parent_id']] = true;
            } else {
                $this->applyInactiveMarker($record, $existing, $payload, $warnings);
                $response = $this->gateway->updateProduct((int) $existing['id'], $payload, $record->sku);
            }
            return $this->result($record, 'update', isset($response['id']) ? (int) $response['id'] : (int) $existing['id'], 200, $this->message('Proizvod je ažuriran.', $warnings));
        } catch (Throwable $exception) {
            $status = $exception instanceof GatewayException ? $exception->httpStatus : null;
            return $this->result($record, 'error', null, $status, ImportLogger::sanitize($exception->getMessage()));
        }
    }

    private function applyInactiveMarker(ProductRecord $record, array $existing, array &$payload, array &$warnings): void
    {
        $current = $this->gateway->getProduct((int) $existing['id'], $record->sku);
        if ((int) ($current['id'] ?? 0) !== (int) $existing['id'] || !is_string($current['name'] ?? null) || trim($current['name']) === '') {
            throw new \RuntimeException('Nije moguće pouzdano pročitati postojeći naziv proizvoda; ažuriranje je zaustavljeno.');
        }
        $name = $current['name'];
        $retiring = !$record->active && $record->totalStock == 0.0;
        if ($retiring) {
            if (!str_contains($name, '#0#')) {
                $payload['name'] = $name . ' #0#';
            }
            // Sačuvaj postojeće kategorije i dodaj oznaku za povlačenje.
            $categoryId = $this->gateway->categoryIdBySlug('brisati');
            if ($categoryId === null) {
                $warnings[] = 'Kategorija Brisati (brisati) nije pronađena; proizvod je ipak označen i postavljen u skicu.';
            } else {
                if (!is_array($current['categories'] ?? null)) {
                    throw new \RuntimeException('Nije moguće pročitati postojeće kategorije proizvoda.');
                }
                $ids = array_map(static fn (array $category): int => (int) $category['id'], $current['categories']);
                $ids[] = $categoryId;
                $payload['categories'] = array_map(static fn (int $id): array => ['id' => $id], array_values(array_unique($ids)));
            }
        } elseif ($record->active && str_contains($name, '#0#')) {
            $cleanName = trim(str_replace([' #0#', '#0#'], '', $name));
            if ($cleanName === '') {
                throw new \RuntimeException('Postojeći naziv sadrži samo oznaku #0#; prije importa potrebno je ispraviti naziv u WooCommerceu.');
            }
            $payload['name'] = $cleanName;
            $categoryId = $this->gateway->categoryIdBySlug('brisati');
            if ($categoryId !== null) {
                $categories = $payload['categories'] ?? ($current['categories'] ?? null);
                if (!is_array($categories)) {
                    throw new \RuntimeException('Nije moguće pročitati postojeće kategorije proizvoda.');
                }
                $payload['categories'] = array_values(array_map(
                    static fn (array $category): array => ['id' => (int) $category['id']],
                    array_filter($categories, static fn (array $category): bool => (int) $category['id'] !== $categoryId),
                ));
            }
        }
    }

    private function updateVariationParentStocks(): void
    {
        $perPage = 100;
        foreach (array_keys($this->updatedVariationParentIds) as $parentId) {
            $totalStock = 0.0;
            $page = 1;
            do {
                $variations = $this->gateway->variationsPage($parentId, $page++, $perPage);
                foreach ($variations as $variation) {
                    $quantity = $variation['stock_quantity'] ?? 0;
                    $totalStock += is_numeric($quantity) ? (float) $quantity : 0.0;
                }
            } while (count($variations) === $perPage);

            $stock = floor($totalStock) === $totalStock ? (int) $totalStock : $totalStock;
            $this->gateway->updateProduct($parentId, [
                'meta_data' => [[
                    'key' => 'upp_variations_total_stock',
                    'value' => $stock,
                ]],
            ], '');
        }
    }

    /** @return list<int>|null */
    private function categories(ProductRecord $record, array &$warnings): ?array
    {
        $ids = [];
        foreach (array_filter([$record->primaryCategoryCode, $record->secondaryCategoryCode]) as $code) {
            $slug = $this->categoryMapper->slugFor($code);
            if ($slug === null) {
                $warnings[] = "Nema mapiranja kategorije za ERP šifru {$code}.";
            } else {
                $id = $this->gateway->categoryIdBySlug($slug);
                if ($id !== null) {
                    $ids[] = $id;
                    continue;
                }
                $warnings[] = "WooCommerce kategorija {$slug} nije pronađena.";
            }
            if ($this->unknownCategoryBehavior === 'skip_product') {
                return null;
            }
        }
        return array_values(array_unique($ids));
    }

    private function result(ProductRecord $record, string $operation, ?int $id, ?int $status, string $message): ImportResult
    {
        return new ImportResult($record->index, $record->sku, $operation, $id, $status, $message, gmdate(DATE_ATOM));
    }

    private function message(string $message, array $warnings): string
    {
        return $warnings === [] ? $message : $message . ' Upozorenje: ' . implode(' ', $warnings);
    }

    private function summarize(array $results, int $total, string $started, string $finished): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'warnings' => 0, 'errors' => 0];
        foreach ($results as $result) {
            if ($result->operation === 'create') $counts['created']++;
            if ($result->operation === 'update') $counts['updated']++;
            if (in_array($result->operation, ['skip', 'skipped_with_warning'], true)) $counts['skipped']++;
            if ($result->operation === 'error') $counts['errors']++;
            if ($result->operation === 'skipped_with_warning' || str_contains($result->message, 'Upozorenje:')) $counts['warnings']++;
        }
        return ['total' => $total] + $counts + ['startedAt' => $started, 'finishedAt' => $finished];
    }
}
