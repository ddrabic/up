<?php
require 'login_check.php';
require __DIR__ . '/lib/web.php';
?>
<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="UTF-8">
    <title>Pregled JSON datoteke</title>
    <link rel='stylesheet' type='text/css' href='https://code.jquery.com/ui/1.10.3/themes/redmond/jquery-ui.css' />
    <link rel='stylesheet' type='text/css' href='plugins/trirand/ui.jqgrid.css' />
    <link rel="stylesheet" href="https://www.w3schools.com/w3css/4/w3.css">

    <script src="https://code.jquery.com/jquery-1.10.1.min.js"></script>
    <script type='text/javascript' src='plugins/trirand/jquery-ui-custom.min.js'></script>
    <script type='text/javascript' src='plugins/trirand/grid.locale-en.js'></script>
    <script type='text/javascript' src='plugins/trirand/jquery.jqGrid.js'></script>
    <script>
        $(document).ready(function () {
            $("#list_records").jqGrid({
                url: "get_json.php",
                datatype: "json",
                mtype: "GET",
                colNames: ["kodRobe","nazivRobe","jedinicaMjere","MPC","popust","artiklNaAkciji","artiklNaRasprodaji","stanje","aktivan","gdjeSeNalazi","velicinaRame","velicinaKotaca","spol","kodGrupe","kodGrupe2","brand"],
                colModel: [
                    { name: "kodRobe",width:"100px"},
                    { name: "nazivRobe",width:"200px"},
                    { name: "jedinicaMjere",width:"20px"},
                    { name: "MPC",align:"right",width:"60px"},
                    { name: "popust",align:"right",width:"60px"},
                    { name: "artiklNaAkciji",width:"50px"},
                    { name: "artiklNaRasprodaji",width:"50px"},
                    { name: "stanje",align:"right",width:"100px"},
                    { name: "aktivan",align:"right",width:"30px"},
                    { name: "gdjeSeNalazi",width:"50px"},
                    { name: "velicinaRame",align:"right",width:"50px"},
                    { name: "velicinaKotaca",align:"right",width:"50px"},
                    { name: "spol",width:"20px"},
                    { name: "kodGrupe",width:"50px"},
                    { name: "kodGrupe2",width:"50px"},
                    { name: "brand",width:"50px"}
                ],
                pager: "#perpage",
                rowNum: 500,
                rowList: [10,20,50,100,500,1000,10000],
                sortname: "kodRobe",
                sortorder: "asc",
                height: 'auto',
                viewrecords: true,
                gridview: true,
                caption: ""
            });
        });
    </script>

</head>
    <body>
    <div class="w3-bar w3-green">
        <a class="w3-bar-item w3-button" href="index.php">HOME</a>
        <a class="w3-bar-item w3-button" href="rest.php" target="_blank">Skidanje cijele JSON datoteke</a>
        <a class="w3-bar-item w3-button w3-right" href="logout.php">Odjava</a>
        <span class="w3-bar-item w3-right">WP domena: <?php echo upp_escape((string) upp_config('target_domain')); ?></span>
    </div>
        <table id="list_records"><tr><td></td></tr></table>
        <div id="perpage"></div>
    </body>
</html>
