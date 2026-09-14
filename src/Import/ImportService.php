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

    /**
     * @return array{
     *   summary: array<string, mixed>,
     *   results: list<ImportResult>,
     *   sourceTotal: int,
     *   nextOffset: int,
     *   hasMore: bool
     * }
     */
    public function import(
        string $path,
        ?callable $onResult = null,
        ?callable $shouldCancel = null,
        ?callable $onProgress = null,
        int $offset = 0,
        ?int $limit = null,
    ): array
    {
        $startedAt = gmdate(DATE_ATOM);
        $raw = $this->reader->read($path);
        $this->validator->validate($raw);
        $records = [];
        foreach ($raw as $index => $item) {
            $records[] = $this->normalizer->normalize($item, $index);
        }
        $sourceTotal = count($records);
        if ($offset < 0) {
            throw new \InvalidArgumentException('Početni zapis importa nije valjan.');
        }
        if ($limit !== null && $limit < 1) {
            throw new \InvalidArgumentException('Veličina paketa importa mora biti veća od nule.');
        }
        if ($offset > 0 || $limit !== null) {
            $records = array_slice($records, $offset, $limit);
        }
        $this->progress($onProgress, [
            'stage' => 'ready',
            'total' => $sourceTotal,
            'offset' => $offset,
            'chunkTotal' => count($records),
        ]);

        $this->updatedVariationParentIds = [];

        // Početna pozicija namjerno nema gornju granicu. Ako je iza kraja
        // datoteke, import uredno završava bez povezivanja na WooCommerce.
        if ($records === []) {
            $finishedAt = gmdate(DATE_ATOM);
            $summary = $this->summarize([], 0, $startedAt, $finishedAt);
            $this->logger?->summary($summary);
            return [
                'summary' => $summary,
                'results' => [],
                'sourceTotal' => $sourceTotal,
                'nextOffset' => $offset,
                'hasMore' => false,
            ];
        }

        // Nijedan gateway poziv ne smije se dogoditi prije potpune validacije/normalizacije.
        $this->gateway->checkConnection();
        $results = [];
        foreach (array_chunk($records, 100) as $batch) {
            $this->throwIfCancelled($shouldCancel, count($results));
            $this->progress($onProgress, [
                'stage' => 'resolving',
                'from' => $batch[0]->index + 1,
                'to' => $batch[array_key_last($batch)]->index + 1,
                'total' => $sourceTotal,
            ]);
            try {
                $existingBySku = $this->gateway->resolveProductsBySku(array_map(
                    static fn (ProductRecord $record): string => $record->sku,
                    $batch,
                ));
                $batchResolved = true;
            } catch (Throwable) {
                // Ne dopusti da kvar optimiziranog endpointa prekine cijeli
                // import. Pojedinacni resolver ispod zadrzava gresku po SKU-u.
                $existingBySku = [];
                $batchResolved = false;
            }
            $pendingUpdates = [];
            foreach ($batch as $record) {
                $this->throwIfCancelled($shouldCancel, count($results));
                $this->progress($onProgress, [
                    'stage' => 'record', 'record' => $record->index + 1,
                    'sku' => $record->sku, 'total' => $sourceTotal,
                ]);
                $prepared = $this->prepare($record, $existingBySku[$record->sku] ?? null, $batchResolved);
                if ($prepared instanceof PendingProductUpdate) {
                    $pendingUpdates[] = $prepared;
                    if (count($pendingUpdates) < 10) {
                        continue;
                    }
                } else {
                    foreach ($this->flushUpdates($pendingUpdates, $onProgress, $sourceTotal) as $result) {
                        $this->recordResult($result, $results, $onResult);
                    }
                    $pendingUpdates = [];
                    $this->recordResult($prepared, $results, $onResult);
                    continue;
                }

                foreach ($this->flushUpdates($pendingUpdates, $onProgress, $sourceTotal) as $result) {
                    $this->recordResult($result, $results, $onResult);
                }
                $pendingUpdates = [];
            }
            $this->throwIfCancelled($shouldCancel, count($results));
            foreach ($this->flushUpdates($pendingUpdates, $onProgress, $sourceTotal) as $result) {
                $this->recordResult($result, $results, $onResult);
            }
        }
        $this->updateVariationParentStocks();

        $summary = $this->summarize($results, count($records), $startedAt, gmdate(DATE_ATOM));
        $this->logger?->summary($summary);
        $nextOffset = $offset + count($records);
        return [
            'summary' => $summary,
            'results' => $results,
            'sourceTotal' => $sourceTotal,
            'nextOffset' => $nextOffset,
            'hasMore' => $nextOffset < $sourceTotal,
        ];
    }

    private function throwIfCancelled(?callable $shouldCancel, int $processed): void
    {
        if ($shouldCancel !== null && $shouldCancel()) {
            throw new ImportCancelledException($processed);
        }
    }

    private function progress(?callable $onProgress, array $progress): void
    {
        if ($onProgress !== null) {
            $onProgress($progress);
        }
    }

    private function prepare(ProductRecord $record, ?array $existing, bool $alreadyResolved): ImportResult|PendingProductUpdate
    {
        $warnings = [];
        try {
            if (floor($record->totalStock) !== $record->totalStock) {
                $warnings[] = sprintf(
                    'Decimalna ERP zaliha %s prilagođena je na %d jer WooCommerce prihvaća cijeli broj.',
                    $this->quantity($record->totalStock),
                    (int) floor($record->totalStock),
                );
            }
            if (!$alreadyResolved) {
                $existing = $this->gateway->resolveProductBySku($record->sku);
            }
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
                return $this->result($record, 'update', isset($response['id']) ? (int) $response['id'] : (int) $existing['id'], 200, $this->message('Proizvod je ažuriran.', $warnings));
            }
            $this->applyInactiveMarker($record, $existing, $payload, $warnings);
            return new PendingProductUpdate($record, (int) $existing['id'], $payload, $warnings);
        } catch (Throwable $exception) {
            $status = $exception instanceof GatewayException ? $exception->httpStatus : null;
            return $this->result($record, 'error', null, $status, ImportLogger::sanitize($exception->getMessage()));
        }
    }

    /** @param list<PendingProductUpdate> $updates @return list<ImportResult> */
    private function flushUpdates(array $updates, ?callable $onProgress, int $total): array
    {
        if ($updates === []) {
            return [];
        }
        $this->progress($onProgress, [
            'stage' => 'updating_batch',
            'from' => $updates[0]->record->index + 1,
            'to' => $updates[array_key_last($updates)]->record->index + 1,
            'skus' => array_map(static fn (PendingProductUpdate $update): string => $update->record->sku, $updates),
            'total' => $total,
        ]);
        try {
            $responses = $this->gateway->updateProductsBatch(array_map(
                static fn (PendingProductUpdate $update): array => [
                    'id' => $update->productId,
                    'sku' => $update->record->sku,
                    'payload' => $update->payload,
                ],
                $updates,
            ));
            if (count($responses) !== count($updates)) {
                throw new \RuntimeException('Broj rezultata skupnog ažuriranja ne odgovara broju proizvoda.');
            }
            $results = [];
            foreach ($updates as $index => $update) {
                $response = $responses[$index];
                if (($response['success'] ?? false) === true) {
                    $results[] = $this->result(
                        $update->record,
                        'update',
                        isset($response['id']) ? (int) $response['id'] : $update->productId,
                        200,
                        $this->message('Proizvod je ažuriran.', $update->warnings),
                    );
                } else {
                    $results[] = $this->result(
                        $update->record,
                        'error',
                        null,
                        isset($response['httpStatus']) ? (int) $response['httpStatus'] : null,
                        ImportLogger::sanitize((string) ($response['message'] ?? 'WooCommerce nije prihvatio ažuriranje proizvoda.')),
                    );
                }
            }
            return $results;
        } catch (Throwable) {
            // Ako skupni endpoint ili odgovor nije pouzdan, ponovi svaki PUT
            // zasebno kako bi rezultat i greska ostali vezani uz pravi SKU.
            $results = [];
            foreach ($updates as $update) {
                $this->progress($onProgress, [
                    'stage' => 'updating_one', 'record' => $update->record->index + 1,
                    'sku' => $update->record->sku, 'total' => $total,
                ]);
                try {
                    $response = $this->gateway->updateProduct($update->productId, $update->payload, $update->record->sku);
                    $results[] = $this->result(
                        $update->record,
                        'update',
                        isset($response['id']) ? (int) $response['id'] : $update->productId,
                        200,
                        $this->message('Proizvod je ažuriran.', $update->warnings),
                    );
                } catch (Throwable $exception) {
                    $status = $exception instanceof GatewayException ? $exception->httpStatus : null;
                    $results[] = $this->result($update->record, 'error', null, $status, ImportLogger::sanitize($exception->getMessage()));
                }
            }
            return $results;
        }
    }

    private function recordResult(ImportResult $result, array &$results, ?callable $onResult): void
    {
        $results[] = $result;
        $this->logger?->result($result);
        if ($onResult !== null) {
            $onResult($result);
        }
    }

    private function applyInactiveMarker(ProductRecord $record, array $existing, array &$payload, array &$warnings): void
    {
        // Novija verzija UPP WordPress dodatka vraca ove detalje vec u SKU
        // resolveru. Fallback cuva kompatibilnost tijekom postupne objave.
        $current = is_string($existing['name'] ?? null) && trim($existing['name']) !== ''
            ? $existing
            : $this->gateway->getProduct((int) $existing['id'], $record->sku);
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

    private function quantity(float $quantity): string
    {
        $formatted = rtrim(rtrim(number_format($quantity, 6, '.', ''), '0'), '.');
        return str_replace('.', ',', $formatted);
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
