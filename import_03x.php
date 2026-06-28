<?php

/**

 * User: dd

 * Date: 13.05.2018.

 * Time: 21:24

 */

//require $_SERVER["DOCUMENT_ROOT"].'/upp/login_check.php';

require __DIR__.'/login_check.php';

?>

<!DOCTYPE html>

<html lang="hr">

<head>

    <meta charset="utf-8">

    <title>Import podataka iz JSON datoteke</title>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

    <script type="text/javascript">



        function sleep(milliseconds) {

            let start = new Date().getTime();

            for (let i = 0; i < 1e7; i++) {

                if ((new Date().getTime() - start) > milliseconds){

                    break;

                }

            }

        }



        let

            radnje='?+-!=#<',

            gresaka=0;



        function Prebaci(){
            let broj=$("#broj").val();

            // nakon svakih 100 zapisa pričekaj 10 sekundi
            if (broj%100==0) {
                sleep(10000);
            }
            //$('#result tr:last').after('<tr><td>=></td><td>'+broj+'</td></tr>');
            if (isNaN(broj)) {
                //$("#result").html($("#result").html() + '<br>Pogrešan tip podataka!');
                $('#result tr:last').after('<tr><td>'+$("#broj").val()+'</td><td>= pogrešan tip podatka</td></tr>');
                return false;
            }
            console.log('import_03.php '+broj);
            $.ajax({
                type: "POST",
                crossOrigin: true,
                url: 'import_03.php',
                data: {broj: Number(broj)},
                success: function(data){
                    //alert(data);
                    if (data=="" || data=="[null]") {
                        //$("#result").val('NULL');
                        $('#result tr:last').after('<tr><td>???</td><td>NULL</td></tr>');
                        return false;
                    }
                    $("#broj").val(Number($("#broj").val())+1);
                    //$("#result").html($("#result").html()+data+'<br>');
                    let operacija='None';
                    switch (data[0]){
                        case '<':
                            operacija='Preskočen';
                            break;
                        case '+':
                            operacija='Dodan';
                            break;
                        case '=':
                            operacija='Ažuriran';
                            break;
                        case '-':
                            operacija='Nije ažuriran';
                            break;
                        case '?':
                            operacija='Nije pronađen';
                            break;
                        case '~':
                            operacija='KRAJ';
                            break;
                        case '#':
                            operacija='GREŠKA';
                            break;
                        default:
                            operacija=data[0];
                    }
                    $('#result tr:last').after('<tr><td>'+operacija+'</td><td>'+broj+'. '+data.slice(1)+'</td></tr>');
                    if (radnje.includes(data[0])) {
                        gresaka=0;
                        Prebaci();
                    }
                },

                error: function(data) {

                    //$("#result").html('<b>GREŠKA</b>');

                    $('#result tr:last').after('<tr><td>'+''+'</td><td>GREŠKA</td></tr>');

                    //alert('Greška: '+data.message);

                    gresaka++;

                    // ako ima manje od 10 uzastopnih grešaka

                    if (gresaka<=10) {

                        // pričekaj 10 sekundi pa

                        sleep(10000);

                        // nastavi

                        Prebaci;

                    }

                }

            });

        }



        $(document).ready(function(){

            $("button").click(function(){

                gresaka=0;

                Prebaci();

            });

        });

    </script>

    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">

</head>

<body>

    <div class="w3-bar w3-green">

        <a class="w3-bar-item w3-button" href="index.php">HOME</a>

        <a class="w3-bar-item w3-button" href="/upp/pregled_json.php">Pregled JSON datoteke</a>

        <a class="w3-bar-item w3-button" href="rest.php" target="_blank">Skidanje cijele JSON datoteke</a>

    </div>



    <div style="height: 60px"><p>broj početka zapisa:

        <input id="broj" type="number" min="0" max="9999" value="0" size="4" maxlength="4" style="width:70px"></p>

    </div>

    <div style="height: 60px">

        <button type="button" class="w3-btn w3-white w3-border w3-border-red w3-round-large">Importiraj podatke</button>

    </div>

    <br>

    <div>

        <div>

            <h2>Rezultat obrade:</h2>

        </div>

        <table id="result" class="w3-table-all w3-card-4">

            <tbody>

                <tr class="w3-blue">

                    <td style="width: 150px">operacija</td>

                    <td style="width: 400px">kodRobe</td>

                </tr>

            </tbody>

        </table>

    </div>

</body>

</html>
