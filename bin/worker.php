<?php
require __DIR__ . '/../vendor/autoload.php';

/*
$s3 = S3Client::factory(array(
    'credentials' => array(
        'key'    => 'YOUR_AWS_ACCESS_KEY_ID',
        'secret' => 'YOUR_AWS_SECRET_ACCESS_KEY',
    )
));
*/


$s3 = Aws\S3\S3Client::factory();
$bucket = getenv('S3_BUCKET')?: die('No "S3_BUCKET" config var in found in env!');
$client = new Predis\Client(getenv('REDIS_URL'));


while(1) {
    $responses = $client->keys('file:*');

    foreach($responses as $response) {
        $mpdf = new \Mpdf\Mpdf();

        $item = $client->get($response);
        $mpdf->WriteHTML('<p>' . $item . '</p>');

        $content = $mpdf->Output('', 'S');
        try {
            $upload = $s3->upload($bucket, substr($response, 5) . ".pdf", $content, 'public-read');
            $link = htmlspecialchars($upload->get('ObjectURL'));
            $ret = $client->del([$response]);
            $ret = $client->set("done:" . substr($response, 5), $link);
        } catch(Exception $e) {

        }

    }
    sleep(5);
}
?>