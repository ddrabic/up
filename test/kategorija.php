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
  ?>
  <form action="kategorija.php" method="get">
    <label for="kat_name"></label><br>
    <input type="text" name="kat_name" id="kat_name">
    <input type="submit" value="Prikaz">
  </form>
  <?php
    require_once( __DIR__.'/../../wp-includes/category.php');
    if (isset($_GET['kat_name'])):
      echo 'kategorija: '.$_GET['kat_name'].'<br>';
      try {
        print('info:<br>');
        echo '<pre>';
        $cat=get_term_by( 'slug', 'brisati', 'category' );
        if (is_wp_error($cat)):
          echo 'greška<br>';
        else:
          print_r($cat->name);
          echo '</pre>';
        endif;
        echo '<h1>KRAJ</h1>';
      }
      catch (Exception $e) {
        echo '<br>error: '.$e->getMessage().'<br>';
      }
      echo '<br>kraj<br>';
    endif;
  ?>
</body>
</html>
