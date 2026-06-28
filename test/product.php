<!DOCTYPE html>
<html lang="hr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>izlist kategorije</title>
</head>
<body>
  <?php
    require_once __DIR__ . '/../lib/config.php';
    if (!upp_config('enable_test_scripts', false)) {
      http_response_code(403);
      exit('Test skripte su onemogućene.');
    }

    $file_name=$_SERVER['DOCUMENT_ROOT'].'/wp-load.php';
    echo $file_name.'<br>';
    echo 'file exist: '.file_exists($file_name).'<br>';
    require_once($file_name);
    require_once($_SERVER['DOCUMENT_ROOT'].'/wp-includes/category.php');
    require_once($_SERVER['DOCUMENT_ROOT'].'/wp-includes/category-template.php');
    require $_SERVER['DOCUMENT_ROOT'] . '/upp/vendor/autoload.php';

    use Automattic\WooCommerce\Client;

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
      // We want to find the ID to this slug.
      $params = [
        'filter' => [
            'sku' => 'RM140046'
        ]
      ];
      $product = $woocommerce->get('products/', $params);
      var_dump($product);
    }
    catch (Exception $e) {
      echo '<br>error: '.$e->getMessage().'<br>';
    }
    echo '<br>kraj<br>';
  ?>
</body>
</html>
