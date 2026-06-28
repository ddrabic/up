<?php
/**
 * Created by PhpStorm.
 * User: rip
 * Date: 16.04.2018.
 * Time: 22:10
 */
session_start();
session_destroy();
header('Location: login.php');
?>