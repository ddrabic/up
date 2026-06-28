<?php

namespace wc;
use Exception;
$make_log=true;

function add_log($data) {
    global $make_log;
    if (!$make_log) 
        return;
    try {
        $log_dat=fopen('upp_log.txt','a');
        fwrite($log_dat, $data."\n");
    } catch(Exception $e) {
        $make_log=False;
    }
    if (isset($log_dat))
        fclose($log_dat); 
}

// biblioteka vlastitih funkcija

require_once "upplib.php";
require_once __DIR__ . "/config.php";


// WooCommerce

//require $_SERVER["DOCUMENT_ROOT"].'/upp/vendor/autoload.php';

require __DIR__.'/../vendor/autoload.php';
//require_once $_SERVER['DOCUMENT_ROOT'].'/wp-load.php';
require_once($_SERVER['DOCUMENT_ROOT'].'/wp-includes/category.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/wp-includes/category-template.php');

use Automattic\WooCommerce\Client;

use Automattic\WooCommerce\HttpClient\HttpClientException;

// WP REST API integration (WooCommerce 2.6 or later)



const
    ARTIKL_NOT_FOUNDED=0,
    ARTIKL_FOUNDED=1,
    ARTIKL_FOUND_ERROR=2,
    ARTIKL_NEAKTIVAN=3,
    ARTIKL_FOUNDED_NEAKTIVAN_BEZSTANJA=4,
    STATUS_READ=0,
    STATUS_UPDATE=1,
    STATUS_INSERT=2,
    STATUS_SKIP=3,
    ARTIKL_SIMPLE=0,
    ARTIKL_VARIANT=1;


class wcImport
{
    private
        $url='',
        $consumerKey='',
        $consumerSecret='',
        $woocommerce=null;

    public
        $json_datoteka="",  // Postavlja se u konstruktoru
        $connected=False, // da li je konektiran na WC
        $datoteka_JSON=null,
        $errorMessage='', // pamti greške
        $artikl_JSON=null,   // pronalazi artikl
        $artikl_type=null,  // tip artikla
        $artikl_Woo=null,   // artikl za WooCommerce
        $data=['product' => [] ], // podaci za ažuriranje
        $result=array(),
        $status=STATUS_READ,
        $neaktivan=false,
        $poruka='';

    /* konstruktor */
    public function __construct($_url='', $_consumerKey='', $_consumerSecret='',$_json_datoteka='')
    {
        // Učitaj iz okružnih varijabli ako parametri nisu prosleđeni
        if (empty($_url)) $_url = \upp_config('woocommerce_url', getenv('WOO_URL') ?: '');
        if (empty($_consumerKey)) $_consumerKey = \upp_config('woocommerce_consumer_key', getenv('WOO_CONSUMER_KEY') ?: '');
        if (empty($_consumerSecret)) $_consumerSecret = \upp_config('woocommerce_consumer_secret', getenv('WOO_CONSUMER_SECRET') ?: '');
        if (empty($_json_datoteka)) $_json_datoteka = __DIR__ . '/../uploads/datoteka.json';
        
        $this->url = $_url;
        $this->consumerKey = $_consumerKey;
        $this->consumerSecret = $_consumerSecret;
        $this->json_datoteka = $_json_datoteka;
    }

    public function artikl_Found($broj)
    {
        if (!$this->connected) {
            add_log('artikl_Found: connected == False');
            return false;
        }
        // učitavanje vrijednosti zapisa iz JSON import datoteke
        if (!$this->ucitaj_ArtiklFromJSON($broj)){
            // ako nije pronađen
            add_log('artikl_Found: ARTIKL_FOUND_ERROR');
            return ARTIKL_FOUND_ERROR;
        }
        elseif ((int)$this->artikl_JSON['aktivan']!=1 && (int)$this->artikl_JSON['stanje']!=0)
        {
            add_log('artikl_Found: ARTIKL_NEAKTIVAN');
            return ARTIKL_NEAKTIVAN;
        }
        // pronalazak zapisa u WooCommerce temeljem kodRobe polja
        // definiranje filtera za traženje
        $params = [
            'filter' => [
                'sku' => $this->artikl_JSON['kodRobe']
            ]
        ];

        try
        {
            // čita produkt temeljem SKU-a
            $this->artikl_Woo = $this->woocommerce->get('products/', $params);
            if (isset($this->artikl_Woo) and count($this->artikl_Woo['products'])) {
                // pronađen je zapis
                // odredi da li je simple ili variant
                add_log('artikl_Found: pronađen je zapis');
                if ($this->artikl_Woo['products']["type"] == "simple")
                {   
                    add_log('artikl_Found: artikl_type=ARTIKL_SIMPLE');
                    $this->artikl_type=ARTIKL_SIMPLE;
                }
                else
                {
                    add_log('artikl_Found: artikl_type=ARTIKL_VARIANT');
                    $this->artikl_type=ARTIKL_VARIANT;
                }
                if ((int)$this->artikl_JSON['aktivan']==0 && (int)$this->artikl_JSON['stanje']==0):
                    add_log('artikl_Found: return ARTIKL_FOUNDED_NEAKTIVAN_BEZSTANJA');
                    return ARTIKL_FOUNDED_NEAKTIVAN_BEZSTANJA;
                else:
                    add_log('artikl_Found: return ARTIKL_FOUNDED');
                    return ARTIKL_FOUNDED;
                endif;
            }
            else
            {
                // zapis nije pronađen
                add_log('artikl_Found: return ARTIKL_NOT_FOUNDED');
                add_log('artikl_Found: $params: '.print_r($params,true));
                return ARTIKL_NOT_FOUNDED;
            }
        }
        catch (Exception $e)
        {
            // greška kod pronalaska
            add_log('artikl_Found: #greska kod pronalaska artikla');
            $this->errorMessage='#greska kod pronalaska artikla: '.$e->getMessage(); // poruka greške
            return ARTIKL_FOUND_ERROR;
        }
    }

    /* spajanje na WooCommerce*/
    public function connect()
    {

        $this->connected=false;

        try {

            $this->woocommerce = new Client(

                $this->url,

                $this->consumerKey,

                $this->consumerSecret,

                [

                    'verify_ssl' => (bool) \upp_config('woocommerce_verify_ssl', true),
                    'timeout' => 30        // Timeout od 30 sekundi

                ]

            );

            $this->connected=true;

            return true;

        }

        catch (Exception $e) {

            $this->woocommerce=null;

            $this->errorMessage='#greska kod spajanja: '.$e->getMessage();

        }

        return false;

    }



    public function getproductAtributesNames($articulos)

    {

        $keys = array();

        foreach ($articulos as $articulo) {

            $terms = $articulo['config'];

            foreach ($terms as $key => $term) {

                array_push($keys, $key);

            }

        }

        /* remove repeted keys*/

        $keys = array_unique($keys);

        $configlist = array_column($articulos, 'config');

        $options = array();

        foreach ($keys as $key) {

            $attributes = array(

                array(

                    'name' => $key,

                    'slug' => 'attr_' . $key,

                    'visible' => true,

                    'variation' => true,

                    'options' => getTermsByKeyName($key, $configlist)

                )

            );

        }

        return $attributes;

    }


    public function ucitaj_ArtiklFromJSON($broj) {
        $this->artikl_JSON=null;

        // PRIPREMA JSON datoteke
        if (is_nan($broj)) {
            add_log('ucitaj_ArtiklFromJSON: #greska dohvata artikla: Nije proslijeđen broj zapisa');
            $this->errorMessage='#greska dohvata artikla: Nije proslijeđen broj zapisa'; // poruka greške
            return false;
        }

        try {
            $this->datoteka_JSON=parse_json($this->json_datoteka);
            $this->artikl_JSON=$this->datoteka_JSON[$broj];
            return $this->artikl_JSON!=null; // ako nije null -> uspješno pročitano (true), otherwise (false)
        }
        catch (Exception $e){
            // ne može se učitati zapis pa se prekida izvršenje
            add_log('ucitaj_ArtiklFromJSON: Exception = '.$e->getMessage());
            $this->errorMessage='#greska dohvata artikla: '.$e->getMessage(); // poruka greške
        }
        return false;
    }

    public function popuni_Zalihu()

    {

        $this->data['product']['managing_stock']=$this->artikl_JSON['aktivan'] != '0' and ((float)$this->artikl_JSON['stanje']) > 0;

        $this->data['product']['in_stock'] =$this->artikl_JSON['aktivan'] != '0';

        $this->data['product']['stock_quantity'] = (float)$this->artikl_JSON['stanje'];

    }



    /*

    public function setAttribute($atributi,$NazivAtributa,$value){

        if ($value===null) return false;

        $upisano=false;

        switch ($NazivAtributa){

            case 'Veličina':

                $this->data['product']['attributes'][]=array('name'=>'Veličina','slug'=>'velicina','option'=>strtolower($value));

                $upisano=true;

                break;

            case 'Veličina kotača':

                $this->data['product']['attributes'][]=array('name'=>'Veličina kotača','slug'=>'velicina-kotaca','option'=>strtolower($value));

                $upisano=true;

                break;

        }

        return $upisano=true;

    }

    */



    public function getBrand($k){

        $brand=["DI"=>"	Dinamic",

            "FA"=>"Falcon",

            "JY"=>"Joytech",

            "KM"=>"KMC",

            "MA"=>"Marcon",

            "ME"=>"Messingschlager",

            "MG"=>"Monte Grappa",

            "MT"=>"Mitas",

            "MI"=>"Mighty",

            "HT"=>"HTP",

            "PT"=>"Park Tool",

            "RM"=>"Rock Machine",

            "RX"=>"Remerx",

            "RS"=>"RMS",

            "SA"=>"Saccon",

            "SC"=>"Scott",

            "SH"=>"Shimano",

            "SI"=>"Sigma",

            "SP"=>"Spring",

            "SW"=>"Schwalbe",

            "SY"=>"Syncros",

            "TH"=>"Thun",

            "TL"=>"Trelock",

            "UN"=>"Unior",

            "VA"=>"VAR",

            "VE"=>"Velosteel",

            "VL"=>"Velo",

            "WB"=>"WOB",

            "ZO"=>"Zoom"];

        return $brand[$k];

    }



    public function getCategoryIdByCode($categorySlug)

    {

        $cat_slug=$this->getKategorijaSlug($categorySlug);

        $wc_categories = $this->woocommerce->get('products/categories/');

        foreach ($wc_categories as $a=>$b){

            foreach ($b as $c)

                if (strtoupper($c["slug"])==strtoupper($cat_slug))

                    return $c["id"];

        }

        return 0;

    }

    public function getCategoryIdBySlug($slugName) 
    {
        try {
            $args = array(
                'taxonomy' => 'product_cat',
                'slug' => 'brisati'
              );
              $terms = get_terms( 'product_cat', $args );
              return (int)$term->term_id;
        }
        catch (Exception $e) {
            return 0;
        }
    }



    public function getKategorijaSlug($k){

        $kategorija=[

            "0801"=>"Ležajevi pogona, BB osovine",

            "0802"=>"Ležajevi volana, vilica",

            "0804"=>"Glave i dijelovi glava",

            "0806"=>"Kočnice",

            "0807"=>"Kormila, lule volana, vilice i nastavci v.",

            "0808"=>"Lule sjedala, šelne sjedala, vijci",

            "0810"=>"Kotači",

            "0812"=>"Lanci, spojnice",

            "0813"=>"Lančanici prednji, kurble",

            "0814"=>"Lančanici zadnji",

            "0815"=>"Mjenjači",

            "0816"=>"Ručice mjenjača",

            "0818"=>"Obruči, žbice, niple, trake",

            "0820"=>"Ostali dijelovi",

            "0822"=>"Pedale, blokeji",

            "0824"=>"Ručice (gripovi), trake volana",

            "0826"=>"Sajle, bužiri",

            "0830"=>"Sjedala i navlake sjedala",

            "0902"=>"Alati",

            "0904"=>"Bidoni i nosači bidona",

            "0905"=>"Prtljažnici",

            "0906"=>"Blatobrani, lancobrani",

            "0908"=>"Kacige",

            "0907"=>"Rukavice",

            "0909"=>"Naočale",

            "0910"=>"Odjeća, obuća, marame",

            "0911"=>"Košarice i dijelovi košarica",

            "0912"=>"Ciklokompjutori, satovi",

            "0914"=>"Lokoti",

            "0916"=>"Ostala oprema",

            "0920"=>"Podupirači, poćni kotači, nosači bicikla",

            "0922"=>"Pumpe, pribori za krpanje, CO2, tubeless",

            "0924"=>"Dječje sjedalice",

            "0926"=>"svijetla-katadiopteri",

            "0928"=>"torbice-i-bisage",

            "0930"=>"zvona-trube-retrovizori",

            "1082"=>"spring",

            "1083"=>"scott",

            "1085"=>"rock-machine",

            "1086"=>"wob",

            "1088"=>"djecji",

            "108T"=>"testni",

            "brisati"=>"Brisati"

        ];

        return $kategorija[$k];

    }



    public function getAttribute($slug){

        // u fazi testiranja

        $params = [

            'filter' => [

                'slug' => 'pa_'.$slug

            ]

        ];

        $wc_attribute = $this->woocommerce->get('products/attributes/', $params);

        $wc_attribute2 = $this->woocommerce->get('products/attributes/5/terms/');

    }



    public function dodaj_Atribute(){

        // ATRIBUTI - samo kod ažuriranja

        if ($this->status!=STATUS_INSERT or $this->artikl_type!=ARTIKL_SIMPLE)

            return;



        // inicijalizacija

        $this->data['product']["attributes"]=[];

        $stavka=0;



        // velicinaRame

        if (isset($this->artikl_JSON['velicinaRame']) && !empty($this->artikl_JSON['velicinaRame'])) {

            //$this->getAttribute_ID('velicina');

            $this->data['product']["attributes"][]=[

                "name"=>"Veličina",

                "slug"=>"velicina",

                "position"=>$stavka,

                //"id"=>4,

                "visible"=>true,

                "variation"=>false,

                "options"=>[0=>$this->artikl_JSON['velicinaRame']]];

            $stavka++;

            //$this->data['product']["attributes"][$stavka]=["name" => "Veličina", "slug" => "velicina", "option" => strtolower($this->artikl_JSON['VelicinaRame'])];

            //$this->setAttribute($this->artikl_Woo['products'][0]['attributes'], 'Veličina',$this->artikl_JSON['velicinaRame']);

        }



        // velicinaKotaca

        switch ($this->artikl_JSON['velicinaKotaca']){

            case null:

                $kotac=null;

                break;

            case "26_plus":

                $kotac="26"."\"+";

                break;

            case "27_5":

                $kotac="27.5"."\"";

                break;

            case "27_5_plus":

                $kotac="27.5"."\"+";

                break;

            default:

                $kotac=$this->artikl_JSON['velicinaKotaca']."\"";

                break;

        }

        if (!empty($kotac)) {

            $this->data['product']["attributes"][] = [

                "name" => "Veličina",

                "slug" => "velicina-kotaca",

                "position"=>$stavka,

                //"id"=>3,

                "visible" => true,

                "variation"=>false,

                "options" => [0 => $kotac]

            ];

            $stavka++;

        }



        // Spol

        if (isset($this->artikl_JSON['spol']) && !empty($this->artikl_JSON['spol'])) {

            $this->data['product']["attributes"][] = [

                "name" => "Spol",

                "slug" => "spol",

                "position" =>$stavka,

                //"id"=>5,

                "visible" => true,

                "variation"=>false,

                "option" => [0 => ucfirst($this->artikl_JSON['spol'])]

            ];

            $stavka++;

        }

        // brand - proizvođač

        if (isset($this->artikl_JSON['brand']) && !empty($this->artikl_JSON['brand'])) {

            $this->data['product']["attributes"][] = [

                "name" => "Proizvođač",

                "slug" => "proizvodac",

                "position"=>$stavka,

                "visible" => true,

                "variation"=>false,

                "option" => [0 => $this->getBrand($this->artikl_JSON['brand'])]

            ];

            $stavka++;

        }



    }



    public function dodaj_Kategorije(){

        if ($this->status!=STATUS_INSERT)

            return;

        // kategorije

        // - kodGrupe mora imati zapisan

        

        if ($this->artikl_JSON['aktivan']==0 && $this->artikl_JSON['stanje']==0):

            $this->data['product']["categories"][]=['id' => $this->getCategoryIdByCode('brisati')];
            $this->data['product']["status"]="draft";

        else:
            $kat=$this->getCategoryIdByCode($this->artikl_JSON['kodGrupe']);
            if ($kat!=0):
                $this->data['product']["categories"][]=['id' => $kat];
            endif;

        endif;

        // - kodGrupe2 ako je definiran odredi kategoriju

        if ( !empty($this->artikl_JSON['kodGrupe2']) ) {

            $kat=$this->getCategoryIdByCode($this->artikl_JSON['kodGrupe2']);

            if ($kat!=0):

                $this->data['product']['categories'][]=['id' => $kat];

            endif;

        }

    }

    public function popuni_Polja()
    {
        try
        {
            
            if ($this->status==STATUS_INSERT)
            {
                add_log('popuni_Polja: status==STATUS_INSERT');
                // novi artikl definiraj kao skicu
                $this->data['product']["status"]="draft";

                $this->data['product']['sku']=$this->artikl_JSON['kodRobe'];
                //  nazivRobe
                $this->data['product']['title']=$this->artikl_JSON['nazivRobe'];
                $this->data['product']['short_description']=$this->artikl_JSON['nazivRobe'];
                $this->data['product']['description']=$this->artikl_JSON['nazivRobe'];

            }
            if ($this->neaktivan) {
                add_log('popuni_Polja: neaktivan');
                if (strpos($this->data['product']['title'], ' #0#')===false) {
                    // ako nema oznaku, dodaj je
                    add_log('popuni_Polja: dodana oznaka #0#');
                    $this->data['product']['title'].=' #0#';
                }
            } elseif (strpos($this->data['product']['title'], ' #0#') !== false) {
                add_log('popuni_Polja:  artikl ima oznaku #0#, pa je brišem');
                // obriši ' #0#' ako se nalazi unutra
                $this->data['product']['title']=str_replace("% #0#%", "", $this->data['product']['title']);
            }
            
            // kategorije
            $this->dodaj_Kategorije();

            // Zaliha
            $this->popuni_Zalihu();

            // MPC
            $this->data['product']['regular_price']=(float)$this->artikl_JSON['MPC'];
            $this->data['product']['price']=(float)$this->artikl_JSON['MPC'];

            // popust
            /*
            if ((float)$this->artikl_JSON['popust']==0){
                //$this->data['product']['sale_price']=0.0;
            }
            else {
                $this->data['product']['sale_price']=
                    ( (float)$this->artikl_JSON['MPC'] ) * ( 1 - ( (float)$this->artikl_JSON['popust']/100 ) );
            }
            */
            # Ažuriraj cijenu
            $this->data['product']['sale_price']=
                ( (float)$this->artikl_JSON['MPC'] ) * ( 1 - ( (float)$this->artikl_JSON['popust']/100 ) );

            // ažurirati --->>>
            $this->data['product']['date_on_sale_from']=null;
            $this->data['product']['date_on_sale_to']=null;
            if ((float)$this->artikl_JSON['popust']>0){
                #$this->data['product']['sale_price_dates_from']='2019-11-29';
                #$this->data['product']['date_on_sale_from_gmt']='2019-11-27T00:00:01';
                #$this->data['product']['sale_price_dates_to']='2019-11-30';
                #$this->data['product']['date_on_sale_to_gmt']='2019-11-28T23:59:59';
            }
            else {
                $this->data['product']['date_on_sale_from']=null;
                $this->data['product']['date_on_sale_to']=null;
            }
            // ažurirati ---<<<

            // atributi
            $this->dodaj_Atribute();

            // gdjeSeNalazi
            // ???
            add_log('popuni_Polja: return true');
            return true;
        }
        catch (Exception $e)
        {
            $this->evidentirajGresku('Greska kod popunjavanja polja: '.$e->getMessage());
            return false;
        }
    }

    public function evidentirajGresku($poruka)
    {
        if (empty($poruka)) {
            $this->errorMessage=$poruka;
        }
        else {
            $this->errorMessage=$this->errorMessage.'\n'.$poruka;
        }
    }



    public function obradiZapis($broj)
    {
        $this->poruka='';
        // pronalazak artikla
        $pronalazak=$this->artikl_Found($broj);
        //echo '*'.$pronalazak.'*';
        switch ($pronalazak)
        {
            case ARTIKL_FOUNDED:
                // pronađen
                $this->status=STATUS_UPDATE;
                add_log('obradiZapis: status=STATUS_UPDATE');
                break;
            case  ARTIKL_NOT_FOUNDED:
                // nije pronađen
                if ($this->artikl_JSON['aktivan']==0 && $this->artikl_JSON['stanje']==0) {
                    // nema ga u proizvodima, nije aktivan i nema stanje - nemoj ga uplodati
                    $this->status=STATUS_SKIP;
                    add_log('preskociZapis: status=STATUS_SKIP - return true');
                    $this->poruka= '<' . $this->artikl_JSON['kodRobe'];
                    return true;
                }
                $this->status=STATUS_INSERT;
                add_log('obradiZapis: status=STATUS_INSERT');
                break;
            case ARTIKL_NEAKTIVAN:
                $this->poruka= '!' . $this->artikl_JSON['kodRobe'];
                add_log('obradiZapis: ARTIKL_NEAKTIVAN - return true');
                return true;
            case ARTIKL_FOUNDED_NEAKTIVAN_BEZSTANJA:
                $this->status=STATUS_UPDATE;
                add_log('obradiZapis: status=STATUS_UPDATE - return true');
                $this->neaktivan=true;
                break;
            case ARTIKL_FOUND_ERROR:
                $this->poruka= '~';
                add_log('obradiZapis: ARTIKL_FOUND_ERROR - return true');
                return true;
            default:
                // greška kod pronalaska
                $this->status=STATUS_READ;
                $this->errorMessage=$this->artikl_JSON['kodRobe'].' => '.$this->errorMessage;
                add_log('obradiZapis: status=STATUS_READ - return false');
                return false;
        }

        try {
            if (isset($this->artikl_JSON)):
                // pronađen je
                if (!$this->popuni_Polja())
                {
                    // greška kod popunjavanja polja
                    return false;
                }
                // nastavi s procesom spremanja u Woo
                $greske = $this->errorMessage;
                if ($this->status==STATUS_SKIP) {
                    // artikl treba preskočiti - ne unosi se
                    add_log('obradiZapis: SKIP artikla, operacija <');
                    $operacija='<';
                    $b = $this->woocommerce->post('products/', $this->data);
                }
                elseif ($this->status==STATUS_INSERT) {
                    // INSERT
                    add_log('obradiZapis: INSERT artikla, operacija +');
                    $b = $this->woocommerce->post('products/', $this->data);
                    $operacija='+';
                }
                else {
                    // UPDATE
                    add_log('obradiZapis: UPDATE artikla, operacija =');
                    $b = $this->woocommerce->put('products/' . $this->artikl_Woo['products'][0]['id'], $this->data);
                    $operacija='=';
                    if ($this->neaktivan):
                        $greske+='*0*';
                    endif;
                }
                // provjera radi poruke
                if (isset($b)):
                    if ($operacija=='<'):
                        $this->datoteka_JSON[$broj]['PorukaObrade'] = trim('Preskočeno - ' . $this->artikl_JSON['kodRobe'] . ' ' . $greske);
                    elseif ($operacija=='+'):
                        $this->datoteka_JSON[$broj]['PorukaObrade'] = trim('Dodano - ' . $this->artikl_Woo['products'][0]['type'] . ' ' . $greske);
                    else:
                        $this->datoteka_JSON[$broj]['PorukaObrade'] = trim('Ažurirano - ' . $this->artikl_Woo['products'][0]['type'] . ' ' . $greske);
                    endif;
                    $this->poruka=$operacija . $this->artikl_JSON['kodRobe'];
                else:
                    $greske .= ' #1'; // broji greške
                    $this->datoteka_JSON[$broj]['PorukaObrade'] = trim('Nije ažurirano' . ' ' . $greske);
                    $this->poruka='-' . $this->artikl_JSON['kodRobe'];
                endif;
            else:
                $this->datoteka_JSON[$broj]['PorukaObrade'] = 'Nije pronađeno';
                $this->poruka='?' . $this->artikl_JSON['kodRobe'];
            endif;
            $this->evidentirajGresku($greske);
        }
        catch (Exception $e){
            add_log('obradiZapis: Exception: '.$e->getMessage());
            $this->evidentirajGresku('#'.$this->artikl_JSON['kodRobe'].' - greska kod azuriranja artikla: '.$e->getMessage()); // poruka greške
            return false;
        }
        // evidentiraj: PurukaObrade
        ZapisiDatoteku($this->datoteka_JSON);
        return true;
    }

}
