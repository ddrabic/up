<?php
require 'login_check.php';
?>
<html lang="hr">
    <head>
        <meta charset="UTF-8">
        <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">
    </head>
    <body>
    <?php
        if (empty($_FILES))
            echo "nema datoteka... ";
        else {
            // Provjera greške uploada
            if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                die("Greška kod uploada datoteke: " . $_FILES['file']['error']);
            }
            
            if (isset($_FILES["file"]["name"])) {
                $name = $_FILES["file"]["name"];
                $tmp_name = $_FILES['file']['tmp_name'];
                $error = $_FILES['file']['error'];

                if (!empty($name)) {
                    // VALIDACIJA: Provjerite veličinu datoteke
                    $max_size = 50 * 1024 * 1024;  // 50MB limit
                    if ($_FILES['file']['size'] > $max_size) {
                        die("<div class='w3-panel w3-red'><h3>Greška</h3><p>Datoteka je prevelika (maksimalno 50MB)</p></div>");
                    }

                    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $blocked_extensions = ['php', 'phtml', 'phar', 'html', 'htm', 'js'];
                    if ($extension !== 'json' || in_array($extension, $blocked_extensions, true)) {
                        die("<div class='w3-panel w3-red'><h3>Greška</h3><p>Dozvoljene su samo .json datoteke</p></div>");
                    }
                    
                    // VALIDACIJA: Provjerite MIME tip
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $tmp_name);
                    finfo_close($finfo);
                    
                    if ($mime !== 'application/json') {
                        die("<div class='w3-panel w3-red'><h3>Greška</h3><p>Datoteka nije JSON format (detektovano: $mime)</p></div>");
                    }
                    
                    // VALIDACIJA: Provjerite JSON strukturu
                    $json_content = file_get_contents($tmp_name);
                    $json_decoded = json_decode($json_content, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        die("<div class='w3-panel w3-red'><h3>Greška</h3><p>JSON nije validan: " . json_last_error_msg() . "</p></div>");
                    }
                    
                    if (!is_array($json_decoded)) {
                        die("<div class='w3-panel w3-red'><h3>Greška</h3><p>JSON mora sadržavati niz zapisa</p></div>");
                    }
                    
                    // VALIDACIJA: Provjerite obavezna polja u svakom zapisu
                    $required_fields = ['kodRobe', 'nazivRobe', 'MPC', 'stanje'];
                    foreach ($json_decoded as $idx => $record) {
                        if (!is_array($record)) {
                            die("<div class='w3-panel w3-red'><h3>Greška</h3><p>Zapis #$idx nije objekat</p></div>");
                        }
                        foreach ($required_fields as $field) {
                            if (!isset($record[$field])) {
                                die("<div class='w3-panel w3-red'><h3>Greška</h3><p>Zapis #$idx nedostaje obavezno polje: <strong>$field</strong></p></div>");
                            }
                        }
                    }
                    
                    // Svi validacijski testovi su prošli - čuva datoteku
                    $location = __DIR__.'/uploads/';
                    if (move_uploaded_file($tmp_name, $location . 'datoteka.json')) {
                        echo '<div class="w3-bar w3-green">';
                        echo '<a class="w3-bar-item w3-button" href="/upp/index.php">Početna stranica</a>';
                        echo '<a class="w3-bar-item w3-button" href="import_03x.php">Import prebačene datoteke</a>';
                        echo '<a class="w3-bar-item w3-button" href="pregled_json.php">Pregled prebačene datoteke</a>';
                        echo '</div>';
                        echo '<h1>✓ Datoteka je uspješno uploadovana i validirana</h1>';
                        echo '<p>Broj zapisa: ' . count($json_decoded) . '</p>';
                    }

                } else {
                    echo '<a href="/upp/index.php">Početna stranica</a>';
                }
            }
        }
        ?>
    </body>
</html>
