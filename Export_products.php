<?php
/**
 * User: dd
 * Date: 26.05.2018.
 */
require __DIR__ . '/login_check.php';
require __DIR__ . '/lib/config.php';

// biblioteka vlastitih funkcija
require_once __DIR__ . "/lib/upplib.php";

// WooCommerce
require __DIR__ . '/vendor/autoload.php';
use Automattic\WooCommerce\Client;
// WP REST API integration (WooCommerce 2.6 or later)
try {
    $woocommerce = new Client(
        upp_config('woocommerce_url'),
        upp_config('woocommerce_consumer_key'),
        upp_config('woocommerce_consumer_secret'),
        [
            //'wp_api' => true,
            'verify_ssl' => (bool) upp_config('woocommerce_verify_ssl', true)
        ]
    );
}
catch (Exception $e) {
    echo '#greska kod spajanja';
    exit('[#*#'.$e->getMessage().']');
}

$a=$woocommerce->get('products/');
if (isset($a) and count($a['products'])):
    // pronađeni su svi artikli - zapis ih u JSON datoteku
    $encodedString = json_encode($a);
    file_put_contents(__DIR__ . "/uploads/svi_artikli.json", $encodedString);
endif;

?>
