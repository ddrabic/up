<?php
/**
 * Plugin Name: UPP Warehouse Stock
 * Description: Sprema i prikazuje ERP stanje artikla po poslovnicama/skladištima.
 * Version: 1.0.1
 * Author: UPP
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * Text Domain: upp-warehouse-stock
 */

defined('ABSPATH') || exit;

final class UPP_Warehouse_Stock
{
    private const META_KEY = 'upp_warehouse_stock';

    /** @var array<string, array{name: string, address: string}> */
    private const LOCATIONS = [
        '202' => ['name' => 'Čakovec', 'address' => 'Bana Jelačića 4'],
        '204' => ['name' => 'Virovitica', 'address' => 'S. Radića 77'],
        '205' => ['name' => 'Čakovec', 'address' => 'Svetojelenska 15'],
        '208' => ['name' => 'Koprivnica', 'address' => 'B. Radića 23'],
        '209' => ['name' => 'Varaždin', 'address' => 'Optujska 50'],
        '701' => ['name' => 'Skladište', 'address' => ''],
    ];

    public static function boot(): void
    {
        add_filter('woocommerce_rest_pre_insert_product_object', [self::class, 'replace_imported_stock'], 10, 3);
        add_filter('woocommerce_rest_pre_insert_product_variation_object', [self::class, 'replace_imported_stock'], 10, 3);
        add_filter('woocommerce_available_variation', [self::class, 'variation_data'], 10, 3);
        add_action('woocommerce_single_product_summary', [self::class, 'render_product_stock'], 25);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function replace_imported_stock($product, WP_REST_Request $request, bool $creating)
    {
        $metaData = $request->get_param('meta_data');
        if (!is_array($metaData)) {
            return $product;
        }

        foreach ($metaData as $meta) {
            if (!is_array($meta) || ($meta['key'] ?? '') !== self::META_KEY) {
                continue;
            }

            // Snapshot semantika: prethodni zapis nestaje prije upisa svih novih stanja.
            $product->delete_meta_data(self::META_KEY);
            $product->add_meta_data(self::META_KEY, self::normalize_stock($meta['value'] ?? []), true);
            break;
        }

        return $product;
    }

    public static function variation_data(array $data, $product, $variation): array
    {
        $data['upp_warehouse_stock_html'] = self::stock_list_html($variation);
        return $data;
    }

    public static function render_product_stock(): void
    {
        global $product;
        if (!$product instanceof WC_Product) {
            return;
        }

        $html = $product->is_type('variable') ? '' : self::stock_list_html($product);
        $hidden = $html === '' ? ' hidden' : '';
        echo '<section id="upp-warehouse-stock" class="upp-warehouse-stock"' . $hidden . '>';
        echo '<h2>' . esc_html__('Dostupnost po poslovnicama', 'upp-warehouse-stock') . '</h2>';
        echo '<div class="upp-warehouse-stock__content">' . wp_kses_post($html) . '</div>';
        echo '</section>';
    }

    public static function enqueue_assets(): void
    {
        if (!function_exists('is_product') || !is_product()) {
            return;
        }
        $url = plugin_dir_url(__FILE__);
        wp_enqueue_style('upp-warehouse-stock', $url . 'assets/warehouse-stock.css', [], '1.0.1');
        wp_enqueue_script('upp-warehouse-stock', $url . 'assets/warehouse-stock.js', ['jquery'], '1.0.1', true);
    }

    private static function stock_list_html($product): string
    {
        if (!$product instanceof WC_Product) {
            return '';
        }
        $stock = self::normalize_stock($product->get_meta(self::META_KEY, true));
        $items = '';
        foreach (self::LOCATIONS as $code => $location) {
            $quantity = (float) ($stock[$code] ?? 0);
            if ($quantity <= 0) {
                continue;
            }
            $formattedQuantity = floor($quantity) === $quantity
                ? (string) (int) $quantity
                : wc_format_decimal($quantity);
            $label = $location['address'] === ''
                ? $location['name']
                : $location['name'] . ', ' . $location['address'];
            $items .= '<li><span>' . esc_html($label) . '</span><strong>'
                . esc_html(sprintf(_n('%s komad', '%s komada', (int) ceil($quantity), 'upp-warehouse-stock'), $formattedQuantity))
                . '</strong></li>';
        }
        return $items === '' ? '' : '<ul>' . $items . '</ul>';
    }

    /** @return array<string, int|float> */
    private static function normalize_stock($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            return [];
        }

        $stock = [];
        foreach (self::LOCATIONS as $code => $_location) {
            $quantity = $value[$code] ?? 0;
            $quantity = is_numeric($quantity) ? max(0, (float) $quantity) : 0.0;
            $stock[$code] = floor($quantity) === $quantity ? (int) $quantity : $quantity;
        }
        return $stock;
    }
}

add_action('plugins_loaded', static function (): void {
    if (class_exists('WooCommerce')) {
        UPP_Warehouse_Stock::boot();
    }
});
