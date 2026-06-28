<!DOCTYPE html>
<html lang="hr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PHP info</title>
</head>
<body>
  <?php
    require_once __DIR__ . '/../lib/config.php';
    if (!upp_config('enable_test_scripts', false)) {
      http_response_code(403);
      exit('Test skripte su onemogućene.');
    }
    echo 'Test skripta je omogućena, ali phpinfo nije dostupan kroz projekt.';
  ?>
</body>
</html>
