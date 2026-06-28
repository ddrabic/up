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
    try {
      // We want to find the ID to this slug.
      $cat=get_category_by_slug('brisati');
      echo 'cat: '.$cat.'<br>';
      echo '<br>2.<br>';
      $args = array(
        'taxonomy' => 'product',
        'slug' => 'RM140046'
      );
      $terms = get_terms( 'product', $args );
      var_dump( $terms );
      echo '<br>3.<br>';
      $cat=get_term_by( 'slug', 'brisati', 'category' );
      echo 'cat: '.$cat.'<br>';
      var_dump($cat);
    }
    catch (Exception $e) {
      echo '<br>error: '.$e->getMessage().'<br>';
    }
    echo '<br>kraj<br>';
  ?>
</body>
</html>
