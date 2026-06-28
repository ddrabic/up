<?php

/**

 * User: dd

 * Date: 13.05.2018.

 * Time: 21:03

 */



//require dirname($_SERVER['PHP_SELF']). '/login_check.php';

//require $_SERVER["DOCUMENT_ROOT"].'/upp/login_check.php';

require 'login_check.php';



// biblioteka vlastitih funkcija

//require_once dirname($_SERVER['PHP_SELF']). "/lib/Objekti.php";

//require_once $_SERVER["DOCUMENT_ROOT"]."/upp/lib/Objekti.php";

require_once __DIR__."/lib/Objekti.php";



//echo 'pocetak';

try

{

    // kreiranje objekta

    $shop=new \wc\wcImport();

}

catch (Exception $e) {

    echo $e->getMessage();

    exit($e->getMessage());

}



// spajanje

if (!$shop->connect()) {

    echo '#Greška kod spajanja';

    exit("nisam se spojio");

}



// definiranje broja od kojeg se počinje vršiti obrada

if ($_SERVER["REQUEST_METHOD"] == "POST"):

    $broj=(int)$_POST['broj'];

elseif ($_SERVER["REQUEST_METHOD"] == "GET"):

    $broj=(int)$_GET['broj'];

else:

    #echo '#greska u zahtjevu';

    #exit('[#*#Pogresan zahtjev]');

    $broj=0;

endif;



//$broj=1;



try {
    //__DIR__ . "/uploads/datoteka.json";
    //$shop->json_datoteka=$_SERVER["DOCUMENT_ROOT"]."/upp/uploads/datoteka.json";
    #$shop->json_datoteka=$_SERVER["DOCUMENT_ROOT"] . "/upp/uploads/datoteka.json";
    $shop->json_datoteka = __DIR__ . "/uploads/datoteka.json";
    //$shop->json_datoteka=__DIR__."/uploads/datoteka.json";
    //echo $shop->json_datoteka;
    // obrada zapisa
    if ($shop->obradiZapis($broj)):
        // uspješno obrađeno
        echo $shop->poruka;
    else:
        echo '#'.$shop->errorMessage;
    endif;

}

catch (Exception $e) {

    echo '#'.$e->getMessage();

    //exit($e->getMessage());

}

$shop=null;



?>
