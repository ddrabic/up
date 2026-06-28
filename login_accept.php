<?php
ini_set('default_charset','utf-8');
require __DIR__ . '/lib/config.php';
session_start();
if (isset($_POST['username']) and isset($_POST['password'])){
    $username = $_POST['username'];
    $password = $_POST['password'];
    $csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $sessionToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    $csrfValid = false;
    if ($sessionToken !== '' && $csrfToken !== '') {
        $csrfValid = function_exists('hash_equals')
            ? hash_equals($sessionToken, $csrfToken)
            : $sessionToken === $csrfToken;
    }
    if ($csrfValid && $username === upp_config('app_username') && password_verify($password, upp_config('app_password_hash'))){
        session_regenerate_id(true);
        $_SESSION['username'] = $username;
        unset($_SESSION['csrf_token']);
        echo 'uspjeh';
        echo '<div><h1>Uspješno ste logirani</h1><p><a href="/upp/index.php">Početna stranica</a></div></p>';
    }
    else {
        echo 'neuspjeh';
        //echo $username.' - '.$password.'<br>';
        echo '<div>Greška kod logiranja, ponovite prijavu!</div>';
        echo '<div><a href="login.php">Stranica za logiranje</a></div>';
    }
}
?>
