<?php
/**
 * Plugin Name: UPP Warehouse Stock
 * Description: Sprema ERP stanje artikla po poslovnicama/skladištima uz opcionalni prikaz kupcima.
 * Version: 1.2.0
 * Author: UPP
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * Text Domain: upp-warehouse-stock
 */

defined('ABSPATH') || exit;

final class UPP_Warehouse_Stock
{
    private const VERSION = '1.2.0';
    private const META_KEY = 'upp_warehouse_stock';
    private const VISIBILITY_OPTION = 'upp_warehouse_stock_visible';

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
        add_action('rest_api_init', [self::class, 'register_rest_routes']);
        add_filter('woocommerce_rest_pre_insert_product_object', [self::class, 'replace_imported_stock'], 10, 3);
        add_filter('woocommerce_rest_pre_insert_product_variation_object', [self::class, 'replace_imported_stock'], 10, 3);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_menu', [self::class, 'register_settings_page']);

        // Frontend hookovi nisu potrebni dok je prikaz isključen. Import ostaje aktivan.
        if (!self::is_stock_visible()) {
            return;
        }

        add_filter('woocommerce_available_variation', [self::class, 'variation_data'], 10, 3);
        add_action('woocommerce_single_product_summary', [self::class, 'render_product_stock'], 25);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function activate(): void
    {
        // Nova instalacija ne prikazuje zalihu dok korisnik to izričito ne uključi.
        add_option(self::VISIBILITY_OPTION, 'no');
    }

    public static function register_settings(): void
    {
        register_setting('upp_warehouse_stock', self::VISIBILITY_OPTION, [
            'type' => 'string',
            'sanitize_callback' => static function ($value): string {
                return $value === 'yes' ? 'yes' : 'no';
            },
            'default' => 'no',
        ]);

        add_settings_section(
            'upp_warehouse_stock_display',
            __('Prikaz zaliha', 'upp-warehouse-stock'),
            static function (): void {
                echo '<p>' . esc_html__('Ova postavka utječe samo na prikaz kupcima. REST ruta i import zaliha uvijek ostaju aktivni.', 'upp-warehouse-stock') . '</p>';
            },
            'upp-warehouse-stock'
        );

        add_settings_field(
            self::VISIBILITY_OPTION,
            __('Vidljivost na proizvodu', 'upp-warehouse-stock'),
            [self::class, 'render_visibility_field'],
            'upp-warehouse-stock',
            'upp_warehouse_stock_display'
        );
    }

    public static function register_settings_page(): void
    {
        add_submenu_page(
            'woocommerce',
            __('UPP zalihe', 'upp-warehouse-stock'),
            __('UPP zalihe', 'upp-warehouse-stock'),
            'manage_woocommerce',
            'upp-warehouse-stock',
            [self::class, 'render_settings_page']
        );
    }

    public static function render_visibility_field(): void
    {
        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr(self::VISIBILITY_OPTION) . '" value="yes" '
            . checked(self::is_stock_visible(), true, false) . '> ';
        echo esc_html__('Prikaži dostupnost po poslovnicama na javnoj stranici proizvoda', 'upp-warehouse-stock');
        echo '</label>';
    }

    public static function render_settings_page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('UPP zalihe', 'upp-warehouse-stock') . '</h1>';
        echo '<form action="options.php" method="post">';
        settings_fields('upp_warehouse_stock');
        do_settings_sections('upp-warehouse-stock');
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public static function register_rest_routes(): void
    {
        register_rest_route('wc/v3', '/upp/product-by-sku', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'resolve_product_by_sku'],
            'permission_callback' => static fn (): bool => current_user_can('manage_woocommerce'),
            'args' => [
                'sku' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
        register_rest_route('wc/v3', '/upp/products-by-sku', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'resolve_products_by_sku'],
            'permission_callback' => static fn (): bool => current_user_can('manage_woocommerce'),
            'args' => [
                'skus' => [
                    'required' => true,
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'minItems' => 1,
                    'maxItems' => 100,
                ],
            ],
        ]);
    }

    public static function resolve_product_by_sku(WP_REST_Request $request): WP_REST_Response
    {
        $sku = trim((string) $request->get_param('sku'));
        return new WP_REST_Response(self::product_data_by_sku($sku), 200);
    }

    public static function resolve_products_by_sku(WP_REST_Request $request): WP_REST_Response
    {
        $results = [];
        foreach ((array) $request->get_param('skus') as $requestedSku) {
            $requestedSku = trim((string) $requestedSku);
            $results[] = ['requested_sku' => $requestedSku] + self::product_data_by_sku($requestedSku);
        }
        return new WP_REST_Response($results, 200);
    }

    private static function product_data_by_sku(string $sku): array
    {
        $id = $sku === '' ? 0 : (int) wc_get_product_id_by_sku($sku);
        $product = $id > 0 ? wc_get_product($id) : false;
        if (!$product instanceof WC_Product) {
            return ['found' => false];
        }

        $parentId = (int) $product->get_parent_id();
        $categoryIds = array_map('intval', $product->get_category_ids());
        return [
            'found' => true,
            'id' => $product->get_id(),
            'sku' => $product->get_sku(),
            'type' => $product->get_type(),
            'parent_id' => $parentId > 0 ? $parentId : null,
            // Importer ove podatke treba za sigurnu obradu oznake #0#.
            // Njihovim vracanjem iz resolvera uklanja se dodatni REST GET za
            // svaki postojeci jednostavni proizvod.
            'name' => $product->get_name(),
            'categories' => array_map(
                static fn (int $categoryId): array => ['id' => $categoryId],
                $categoryIds
            ),
        ];
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
        wp_enqueue_style('upp-warehouse-stock', $url . 'assets/warehouse-stock.css', [], self::VERSION);
        wp_enqueue_script('upp-warehouse-stock', $url . 'assets/warehouse-stock.js', ['jquery'], self::VERSION, true);
    }

    private static function is_stock_visible(): bool
    {
        return get_option(self::VISIBILITY_OPTION, 'no') === 'yes';
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

register_activation_hook(__FILE__, [UPP_Warehouse_Stock::class, 'activate']);

add_action('plugins_loaded', static function (): void {
    if (class_exists('WooCommerce')) {
        UPP_Warehouse_Stock::boot();
    }
});
