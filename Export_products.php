<?php
declare(strict_types=1);
require __DIR__ . '/login_check.php';
require __DIR__ . '/vendor/autoload.php';

use Upp\Config\Config;
use Upp\Logging\ImportLogger;
use Upp\WooCommerce\RestWooCommerceGateway;

try {
    $config = Config::load(__DIR__);
    $gateway = RestWooCommerceGateway::fromCredentials(
        UPP_TARGET_DOMAIN,
        (string) $config->get('woocommerce_consumer_key', ''),
        (string) $config->get('woocommerce_consumer_secret', ''),
        (bool) $config->get('woocommerce_verify_ssl', true),
    );
    $gateway->checkConnection();
    $products = [];
    $page = 1;
    do {
        $batch = $gateway->productsPage($page++);
        array_push($products, ...$batch);
    } while (count($batch) === 100);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="woocommerce-products.json"');
    echo json_encode($products, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(502);
    echo ImportLogger::sanitize($exception->getMessage());
}
