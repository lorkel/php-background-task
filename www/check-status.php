<?php
    require __DIR__ . '/../vendor/autoload.php';
    header("Access-Control-Allow-Origin: *");

    $client = new Predis\Client(getenv('REDIS_URL'));
    $response = "";
    if ($_GET["filename"] != null && $_GET["filename"] != "") {
        $url = $client->get("done:" . $_GET["filename"]);

        if ($url != null && $url != "") {
            $response = $url;
        }
    }
    header('Content-type: application/json');
    echo json_encode([$response]);

?>