<?php
/**
 * User: dd
 * Date: 10.03.2018.
 * Time: 23:31
 */

// Standardizovana putanja - koristi lokalni fajl umjesto URL-a
// Ovo je mnogo brže jer nema mrežnog zahtjeva i ne ovisi o domeni
if (!isset($json_datoteka)) {
    $json_datoteka = __DIR__ . '/../uploads/datoteka.json';
}

function Zapisi($json, $i, $result, $giveresult=true) {
    if (ZapisiDatoteku($json)):
        array_push($result, $i);
        $v=json_encode($result);
        echo $v;
        if ($giveresult) { return true; };
    else:
        array_push($result,'#*#Greška kod zapisivanja u datoteku');
        echo json_encode($result);
        if ($giveresult) { return false; };
    endif;
}

function ZapisiDatoteku($json = array()) {
    // zapisati JSON
    $jsondata = json_encode($json, JSON_PRETTY_PRINT);
    if (file_put_contents($GLOBALS['json_datoteka'], $jsondata)) {
        return true;
    }
    else
        return false;
}

// parsiranje
function parse_json( $file ) {
    // Read JSON file
    $json = file_get_contents($file);
    $json_data = json_decode( $json, true );

    if ( is_array( $json_data ) && !empty( $json_data ) ) :
        return $json_data;
    else :
        //die( '###Greška kod parsiranja ' . $file . ' datoteke.' );
        if (!file_exists($file)):
            die( 'Nije pronađena datoteka: '.$file.')' );
        else:
            die( 'GGreška kod parsiranja datoteke.');
        endif;

    endif;
}

// atributi
function get_attributes_from_json( $json ) {
    $product_attributes = array();

    foreach( $json as $key => $pre_product ) :
        if ( !empty( $pre_product['attribute_name'] ) && !empty( $pre_product['attribute_value'] ) ) :
            $product_attributes[$pre_product['attribute_name']]['terms'][] = $pre_product['attribute_value'];
        endif;
    endforeach;

    return $product_attributes;

}

// vađenje produkata

/**
 * Get products from JSON and make them ready to import according WooCommerce API properties.
 *
 * @param  array $json
 * @param  array $added_attributes
 * @return array
 */
function get_products_from_json( $json, $added_attributes ) {

    $product = array();

    foreach ( $json as $key => $pre_product ) :

        $product[$key]['_product_id'] = (string) $pre_product['kodRobe'];

        $product[$key]['name'] = (string) $pre_product['nazivRobe'];
        $product[$key]['description'] = (string) $pre_product['jedinicaMjere'];
        $product[$key]['regular_price'] = (float) $pre_product['MPC'];

        //$product[$key]['popust'] = (float) $pre_product['popust'];
        //$product[$key]['artiklNaAkciji'] = (string) $pre_product['artiklNaAkciji'];
        //$product[$key]['artiklNaRasprodaji'] = (string) $pre_product['artiklNaRasprodaji'];

        // STOCK
        if ($pre_product['stanje']>0) :
            $product[$key]['in_stock'] = (bool) true;
            $product[$key]['stock_quantity'] = (float) $pre_product['stanje'];
        else:
            $product[$key]['in_stock'] = (bool) false;
            $product[$key]['stock_quantity'] = (float) 0.0;
        endif;

        /*
        $product[$key]['gdjeSeNalazi'] = (string) $pre_product['gdjeSeNalazi'];
        $product[$key]['velicinaRame'] = (string) $pre_product['velicinaRame'];
        $product[$key]['velicinaKotaca'] = (string) $pre_product['velicinaKotaca'];
        $product[$key]['spol'] = (string) $pre_product['spol'];
        $product[$key]['kodGrupe'] = (string) $pre_product['kodGrupe'];
        $product[$key]['kodGrupe2'] = (string) $pre_product['kodGrupe2'];
        $product[$key]['brand'] = (string) $pre_product['brand'];
        */

    endforeach;

    $data['products'] = $product;
    return $data;
}

/**
 * ORIGINAL
 * Get products from JSON and make them ready to import according WooCommerce API properties.
 *
 * @param  array $json
 * @param  array $added_attributes
 * @return array
 */
function get_products_and_variations_from_json2( $json, $added_attributes ) {

    $product = array();
    $product_variations = array();

    foreach ( $json as $key => $pre_product ) :

        if ( $pre_product['type'] == 'simple' ) :
            $product[$key]['_product_id'] = (string) $pre_product['product_id'];

            $product[$key]['name'] = (string) $pre_product['name'];
            $product[$key]['description'] = (string) $pre_product['description'];
            $product[$key]['regular_price'] = (string) $pre_product['regular_price'];

            // Stock
            $product[$key]['manage_stock'] = (bool) $pre_product['manage_stock'];

            if ( $pre_product['stock'] > 0 ) :
                $product[$key]['in_stock'] = (bool) true;
                $product[$key]['stock_quantity'] = (int) $pre_product['stock'];
            else :
                $product[$key]['in_stock'] = (bool) false;
                $product[$key]['stock_quantity'] = (int) 0;
            endif;

        elseif ( $pre_product['type'] == 'variable' ) :
            $product[$key]['_product_id'] = (string) $pre_product['product_id'];

            $product[$key]['type'] = 'variable';
            $product[$key]['name'] = (string) $pre_product['name'];
            $product[$key]['description'] = (string) $pre_product['description'];
            $product[$key]['regular_price'] = (string) $pre_product['regular_price'];

            // Stock
            $product[$key]['manage_stock'] = (bool) $pre_product['manage_stock'];

            if ( $pre_product['stock'] > 0 ) :
                $product[$key]['in_stock'] = (bool) true;
                $product[$key]['stock_quantity'] = (int) $pre_product['stock'];
            else :
                $product[$key]['in_stock'] = (bool) false;
                $product[$key]['stock_quantity'] = (int) 0;
            endif;

            $attribute_name = $pre_product['attribute_name'];

            $product[$key]['attributes'][] = array(
                'id' => (int) $added_attributes[$attribute_name]['id'],
                'name' => (string) $attribute_name,
                'position' => (int) 0,
                'visible' => true,
                'variation' => true,
                'options' => $added_attributes[$attribute_name]['terms']
            );

        elseif ( $pre_product['type'] == 'product_variation' ) :

            $product_variations[$key]['_parent_product_id'] = (string) $pre_product['parent_product_id'];

            $product_variations[$key]['description'] = (string) $pre_product['description'];
            $product_variations[$key]['regular_price'] = (string) $pre_product['regular_price'];

            // Stock
            $product_variations[$key]['manage_stock'] = (bool) $pre_product['manage_stock'];

            if ( $pre_product['stock'] > 0 ) :
                $product_variations[$key]['in_stock'] = (bool) true;
                $product_variations[$key]['stock_quantity'] = (int) $pre_product['stock'];
            else :
                $product_variations[$key]['in_stock'] = (bool) false;
                $product_variations[$key]['stock_quantity'] = (int) 0;
            endif;

            $attribute_name = $pre_product['attribute_name'];
            $attribute_value = $pre_product['attribute_value'];

            $product_variations[$key]['attributes'][] = array(
                'id' => (int) $added_attributes[$attribute_name]['id'],
                'name' => (string) $attribute_name,
                'option' => (string) $attribute_value
            );

        endif;
    endforeach;

    $data['products'] = $product;
    $data['product_variations'] = $product_variations;

    return $data;
}

/**
 * Merge products and variations together.
 * Used to loop through products, then loop through product variations.
 *
 * @param  array $product_data
 * @param  array $product_variations_data
 * @return array
 */
function merge_products_and_variations( $product_data = array(), $product_variations_data = array() ) {
    foreach ( $product_data as $k => $product ) :
        foreach ( $product_variations_data as $k2 => $product_variation ) :
            if ( $product_variation['_parent_product_id'] == $product['_product_id'] ) :

                // Unset merge key. Don't need it anymore
                unset($product_variation['_parent_product_id']);

                $product_data[$k]['variations'][] = $product_variation;

            endif;
        endforeach;

        // Unset merge key. Don't need it anymore
        unset($product_data[$k]['_product_id']);
    endforeach;

    return $product_data;
}

/**
 * Print status message.
 *
 * @param  string $message
 * @return string
 */
function status_message( $message ) {
    echo $message . "\r\n";
}

function Ispis_JSON($json = array()) {
    echo '<pre>';
    echo json_encode($json, JSON_PRETTY_PRINT);
    echo '</pre>';
}

?>