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
    /** @var array<string, list<array<string, mixed>>>|null */
    private ?array $variationIndex = null;

    /** @var array<string, true> */
    private array $requestedSkus = [];

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

        $this->variationIndex = null;
        $this->requestedSkus = [];
        foreach ($records as $record) {
            $this->requestedSkus[$record->sku] = true;
        }

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

        $summary = $this->summarize($results, count($records), $startedAt, gmdate(DATE_ATOM));
        $this->logger?->summary($summary);
        return ['summary' => $summary, 'results' => $results];
    }

    private function process(ProductRecord $record): ImportResult
    {
        $warnings = [];
        try {
            $products = $record->parentProductId !== null
                ? $this->gateway->findVariationsBySku($record->parentProductId, $record->sku)
                : $this->gateway->findProductsBySku($record->sku);
            if ($record->parentProductId === null && $products === []) {
                $products = $this->variationsBySku($record->sku);
            }
            if (count($products) > 1) {
                throw new \RuntimeException('Kritična greška: više WooCommerce proizvoda ima isti SKU.');
            }
            $existing = $products[0] ?? null;
            if (!$record->active && $record->totalStock > 0) {
                return $this->result($record, 'skipped_with_warning', $existing['id'] ?? null, null, 'Neaktivan proizvod ima pozitivnu zalihu; nije promijenjen.');
            }
            if (!$record->active && $record->totalStock == 0.0 && $existing === null) {
                return $this->result($record, 'skip', null, null, 'Neaktivan proizvod bez zalihe ne postoji; kreiranje je preskočeno.');
            }

            $categoryIds = $this->categories($record, $warnings);
            if ($categoryIds === null) {
                return $this->result($record, 'skipped_with_warning', $existing['id'] ?? null, null, implode(' ', $warnings));
            }
            $brandName = $this->brandMapper->nameFor($record->brandCode);
            if ($record->brandCode !== null && $brandName === null) {
                $warnings[] = "Nepoznat brand {$record->brandCode}; atribut Proizvođač nije upisan.";
                if ($this->unknownBrandBehavior === 'skip_product') {
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
            $response = $record->parentProductId !== null || ($existing['type'] ?? 'simple') === 'variation'
                ? (new VariationService($this->gateway))->update($record, $existing, $payload)
                : $this->gateway->updateProduct((int) $existing['id'], $payload, $record->sku);
            return $this->result($record, 'update', isset($response['id']) ? (int) $response['id'] : (int) $existing['id'], 200, $this->message('Proizvod je ažuriran.', $warnings));
        } catch (Throwable $exception) {
            $status = $exception instanceof GatewayException ? $exception->httpStatus : null;
            return $this->result($record, 'error', null, $status, ImportLogger::sanitize($exception->getMessage()));
        }
    }

    /** @return list<array<string, mixed>> */
    private function variationsBySku(string $sku): array
    {
        if ($this->variationIndex === null) {
            $this->variationIndex = $this->buildVariationIndex();
        }
        return $this->variationIndex[$sku] ?? [];
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function buildVariationIndex(): array
    {
        $index = [];
        $perPage = 100;
        $productPage = 1;
        do {
            $products = $this->gateway->productsPage($productPage++, $perPage);
            foreach ($products as $product) {
                if (($product['type'] ?? null) !== 'variable' || !is_numeric($product['id'] ?? null)) {
                    continue;
                }
                $parentId = (int) $product['id'];
                $variationPage = 1;
                do {
                    $variations = $this->gateway->variationsPage($parentId, $variationPage++, $perPage);
                    foreach ($variations as $variation) {
                        $sku = trim((string) ($variation['sku'] ?? ''));
                        if ($sku === '' || !isset($this->requestedSkus[$sku])) {
                            continue;
                        }
                        $variation['type'] = 'variation';
                        $variation['parent_id'] = $parentId;
                        $index[$sku][] = $variation;
                    }
                } while (count($variations) === $perPage);
            }
        } while (count($products) === $perPage);

        return $index;
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
