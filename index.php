<?php
require 'login_check.php';
require __DIR__ . '/lib/web.php';
$csrf = upp_csrf_token();
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
</head>
<body>
<div class="w3-bar w3-purple">
    <a class="w3-bar-item w3-button" href="pregled_json.php">Pregled JSON datoteke</a>
    <a class="w3-bar-item w3-button" href="import_03x.php">Import podataka iz JSON datoteke</a>
    <a class="w3-bar-item w3-button w3-right" href="logout.php">Odjava</a>
    <span class="w3-bar-item w3-right">WP domena: <?php echo upp_escape((string) upp_config('target_domain')); ?></span>
</div>
<form action="upload.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo upp_escape($csrf); ?>">
    <h1>Učitavanje datoteke</h1>
    <div>
        <p>Odaberi JSON datoteku:</p>
        <input type="file" name="file" accept=".json,application/json,text/plain" style="color: #9c27b0" required />
    </div>
    </br>
    <div>
        <input type="submit" value="Učitaj datoteku" class="w3-btn w3-white w3-border w3-border-red w3-round-large"/>
    </div>
</form>
</body>
</html>
