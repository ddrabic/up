<?php

declare(strict_types=1);

namespace Upp\Import;

use Upp\Config\Config;
use Upp\Inventory\StockCalculator;
use Upp\Logging\ImportLogger;
use Upp\Mapping\AttributeMapper;
use Upp\Mapping\BrandMapper;
use Upp\Mapping\CategoryMapper;
use Upp\WooCommerce\RestWooCommerceGateway;

final class ImportServiceFactory
{
    public static function create(Config $config, ImportLogger $logger): ImportService
    {
        $gateway = RestWooCommerceGateway::fromCredentials(
            UPP_TARGET_DOMAIN,
            (string) $config->get('woocommerce_consumer_key', ''),
            (string) $config->get('woocommerce_consumer_secret', ''),
            (bool) $config->get('woocommerce_verify_ssl', true),
            $logger,
        );
        return new ImportService(
            new JsonFileReader(),
            new JsonValidator(),
            new ProductRecordNormalizer(new StockCalculator(), (array) $config->get('stock_included_warehouses', [])),
            new ProductPayloadFactory(
                new AttributeMapper(),
                (string) $config->get('new_product_status', 'draft'),
                (array) $config->get('warehouse_map', []),
            ),
            new CategoryMapper((array) $config->get('category_map', [])),
            new BrandMapper((array) $config->get('brand_map', [])),
            $gateway,
            $logger,
            (string) $config->get('unknown_category_behavior', 'skip_category'),
            (string) $config->get('unknown_brand_behavior', 'continue'),
        );
    }
}
