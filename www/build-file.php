<?php
    require __DIR__ . '/../vendor/autoload.php';
    header("Access-Control-Allow-Origin: *");

    $client = new Predis\Client(getenv('REDIS_URL'));

    if ($_POST["filename"] != null && $_POST["filename"] != "") {
        $responses = $client->set("file:" . $_POST["filename"], $_POST["content"]);
    }
    header('Content-type: application/json');
    echo json_encode(["success"]);

?>