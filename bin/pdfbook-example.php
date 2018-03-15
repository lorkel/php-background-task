<?php
ini_set('memory_limit', '500M');

if ($_SERVER["HTTP_HOST"]=="localhost" || $_SERVER["HTTP_HOST"]=="192.168.1.6" || $_SERVER["HTTP_HOST"]=="127.0.0.1") {
  $reportPrefix = 'localr';
  $relativePath = '../';
} else {
  $relativePath = __DIR__ . '/../';
  $reportPrefix = 'report';
}

require_once($relativePath . 'www/_inc/conn.php');
require_once($relativePath . 'vendor/autoload.php');
$conn = getConnection();

header("Access-Control-Allow-Origin: *");

$s3 = Aws\S3\S3Client::factory([
  'version' => '2006-03-01',
  'region' => 'us-west-2'
]);

$bucket = getenv('AWS_S3_REPORT_BUCKET')?: die('No "AWS_S3_REPORT_BUCKET" config var in found in env!');
$client = new Predis\Client(getenv('REDIS_URL'));

// $client->set($reportPrefix.":654-project-text", " AND status='A'----");

while(1) {
  $responses = $client->keys($reportPrefix.':*');
  foreach($responses as $response) {
    $filterValue = $client->get($response);

    // debugging
    // $response = $reportPrefix.':654-project-text';
    // $filterValue = " AND status='A'----";
    // $filterValue = " AND status='A'-- AND (plotTypeID='15697')--";
    // $filterValue = " AND (installType='New') AND status='A'-- AND (plotTypeID='15697')--";

    $reportValue = substr($response, 7);
    $reportPieces = explode('-', $reportValue);
    $filterPieces = explode('--', $filterValue);

    $filterQuery = $filterPieces[0];
    $filterSignTypeQuery = $filterPieces[1];
    $filterBeaconTypeQuery = $filterPieces[2];

    $ret = $client->set($reportPrefix.":" . substr($response, 7), 0);

    //  ___
    // | _ \__ _ _ _ __ _ _ __  ___
    // |  _/ _` | '_/ _` | '  \(_-<
    // |_| \__,_|_| \__,_|_|_|_/__/
    //

    if(isset($reportPieces[0])) {
    	$reportID = intval('0'.$reportPieces[0]);
    } else {
      echo 'Need report id.';
      die();
    }

    if(isset($reportPieces[1])) {
      $reportType = $reportPieces[1];
    } else {
    	echo 'Need report type.';
      die();
    }

    if(isset($reportPieces[2])) {
      $reportLayout = $reportPieces[2];
    } else {
    	echo 'Need report layout.';
      die();
    }

    //  ___                   _     _____
    // | _ \___ _ __  ___ _ _| |_  |_   _|  _ _ __  ___
    // |   / -_) '_ \/ _ \ '_|  _|   | || || | '_ \/ -_)
    // |_|_\___| .__/\___/_|  \__|   |_| \_, | .__/\___|
    //         |_|                       |__/|_|

    if($reportType=='project') {
      // get all project data
      $projectID = $reportID;
      include $relativePath.'www/project/reports/includes/get-project.php';
      include $relativePath.'www/project/reports/includes/get-company.php';
      include $relativePath.'www/_inc/get-project-signs.php';
      include $relativePath.'www/_inc/get-project-groups.php';
      include $relativePath.'www/_inc/get-project-plans.php';
      include $relativePath.'www/_inc/get-plot-type-info.php';
      // include $relativePath.'www/_inc/get-project-beacons.php'; // placeholder until these are activated
      $beaconArray = array(); // placeholder until these are activated

      $mergedArray = array_merge($signArray,$beaconArray);

      $plotArray = array();

      foreach ($groupArray as $gK => $gV) {
        foreach ($planArray as $plK => $plV) {
          if($plV['groupID'] == $gV['id']) {
            foreach ($mergedArray as $pK => $pV) {
              if($pV['planID'] == $plV['id']) {
                $pV['groupName'] = $gV['groupName'];
                $pV['planName'] = $plV['planName'];
                $plotArray[] = $pV;
              }
            }
          }
        }
      }

      $reportTypeText = '';
    } else {
      // just get single plan data
      $planID = $reportID;
      include $relativePath.'www/_inc/get-plan-photos.php';
      include $relativePath.'www/_inc/get-project-plan.php';
      include $relativePath.'www/_inc/get-project-group.php';
      include $relativePath.'www/project/reports/includes/get-project.php';
      include $relativePath.'www/project/reports/includes/get-company.php';
      include $relativePath.'www/_inc/get-project-plan-info.php';
      include $relativePath.'www/_inc/get-plot-type-info.php';
      include $relativePath.'www/_inc/get-project-signs.php';
      // include $relativePath.'www/_inc/get-project-beacons.php'; // placeholder until these are activated
      $beaconArray = array(); // placeholder until these are activated

      // combine arrays
      $mergedArray = array_merge($signArray,$beaconArray);

      $plotArray = array();

      foreach ($mergedArray as $pK => $pV) {
        $pV['groupName'] = $groupName;
        $pV['planName'] = $planName;
        $plotArray[] = $pV;
      }

      // then sort by plot name
      // can use usort because it's just one plan
      usort($plotArray, function($a, $b) {
        return $a['plotName'] <=> $b['plotName'];
      });

      $reportTypeText = '<h1>'.$groupName.'</h1><h1>'.$planName.'</h1>';
    }


    // debugging -- just output 5 records for faster testing
    // $plotArray = array_slice($plotArray,0,20);

    $numberOfPlots = count($plotArray);

    //  ___ _     _     _____
    // | _ \ |___| |_  |_   _|  _ _ __  ___ ___
    // |  _/ / _ \  _|   | || || | '_ \/ -_|_-<
    // |_| |_\___/\__|   |_| \_, | .__/\___/__/
    //                       |__/|_|

    $plotTypesArray = array();


    foreach ($libraryArray as $key => $val) {
      foreach ($val['itemList'] as $iK => $iV) {
        $newItem = array(
          'id' => $iV['typeID'],
          'type' => $iV['type'],
          'typeCode' => $iV['typeCode'],
          'typeDescription' => $iV['typeDescription']
        );

        $plotTypesArray['S-'.$iV['typeID']] = $newItem;
      }
    }

    //  _                       _     _____
    // | |   __ _ _  _ ___ _  _| |_  |_   _|  _ _ __  ___
    // | |__/ _` | || / _ \ || |  _|   | || || | '_ \/ -_)
    // |____\__,_|\_, \___/\_,_|\__|   |_| \_, | .__/\___|
    //            |__/                     |__/|_|

    if($reportLayout=='text') {
      $reportLayoutText = '';
      $pageWidth = 216;
      $pageHeight = 279;
    } else if($reportLayout=='master') {
      $reportLayoutText = '<h1>Master Document</h1>';
      $pageWidth = 216;
      $pageHeight = 279;
    } else if($reportLayout=='survey') {
      $reportLayoutText = '<h1>Survey Document</h1>';
      $pageWidth = 216;
      $pageHeight = 279;
    } else {
      echo 'Invalid report type.';
      die();
    }

    // 11 x 17:
    // $pageWidth = 279;
    // $pageHeight = 432;

    //  __  __ _          ___                   _   _   _
    // |  \/  (_)___ __  | __|__ _ _ _ __  __ _| |_| |_(_)_ _  __ _
    // | |\/| | (_-</ _| | _/ _ \ '_| '  \/ _` |  _|  _| | ' \/ _` |
    // |_|  |_|_/__/\__| |_|\___/_| |_|_|_\__,_|\__|\__|_|_||_\__, |
    //                                                        |___/

    if(!empty($companyAddress2)) {
      $companyAddress2 = ', '.$companyAddress2;
    }

    if($filterValue!=" AND status='A'----") {
      $filterText = '<h1>Filtered Data</h1>';
      $filterFooter = ' - Filtered Data';
    } else {
      $filterText = '';
      $filterFooter = '';
    }

    //   ___                       _               ___ ___  ___
    //  / __|___ _ _  ___ _ _ __ _| |_ ___   _ __ | _ \   \| __|
    // | (_ / -_) ' \/ -_) '_/ _` |  _/ -_) | '  \|  _/ |) | _|
    //  \___\___|_||_\___|_| \__,_|\__\___| |_|_|_|_| |___/|_|
    //

    $defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];

    $defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => [$pageWidth, $pageHeight],
        'fontDir' => array_merge($fontDirs, [
            $relativePath . 'www/_fonts/',
        ]),
        'fontdata' => $fontData + [
            'roboto' => [
                'R' => 'Roboto-Regular.ttf',
                'B' => 'Roboto-Medium.ttf'
            ]
        ],
        'default_font' => 'roboto',
        'setAutoBottomMargin' => 'stretch',
        'shrink_tables_to_fit' => 1
    ]);

    //   ___                   ___
    //  / __|_____ _____ _ _  | _ \__ _ __ _ ___
    // | (__/ _ \ V / -_) '_| |  _/ _` / _` / -_)
    //  \___\___/\_/\___|_|   |_| \__,_\__, \___|
    //                                 |___/

    $mpdf->WriteHTML('<link href="'.$relativePath.'www/_css/report-styles.css" rel="stylesheet">');
    $mpdf->SetHTMLFooter('
    <table width="100%" class="footer">
        <tr>
            <td width="25%" valign="bottom">
              '.$projectName.$filterFooter.'<br />
              '.$companyName.'<br />
              '.$companyAddress1.$companyAddress2.'<br />
              '.$companyCity.', '.$companyState.' '.$companyZip.'
            </td>
            <td width="50%" align="center" valign="bottom">
              <img src="'.$companyLogoColor.'" class="company-logo" /><br />
              &copy; {DATE Y} '.$companyName.'. All rights reserved.
            </td>
            <td width="25%" align="right" valign="bottom">{PAGENO}</td>
        </tr>
    </table>');

    $mpdf->WriteHTML('
      <div id="title">
        <h1>'.$projectName.'</h1>
        '.$reportTypeText.'
        '.$reportLayoutText.'
        '.$filterText.'
      </div>
    ');

    //  ___                     ___                 _
    // |_ _|_ _  _ _  ___ _ _  | _ \__ _ __ _ ___  | |   ___  ___ _ __
    //  | || ' \| ' \/ -_) '_| |  _/ _` / _` / -_) | |__/ _ \/ _ \ '_ \
    // |___|_||_|_||_\___|_|   |_| \__,_\__, \___| |____\___/\___/ .__/
    //                                  |___/                    |_|

    if($reportLayout=='text') {
      require_once($relativePath.'www/project/reports/layouts/text.php');
    } else if($reportLayout=='master') {
      require_once($relativePath.'www/project/reports/layouts/master.php');
    } else if($reportLayout=='survey') {
      require_once($relativePath.'www/project/reports/layouts/survey.php');
    }

    // debugging
    // $mpdf->Output();

    $content = $mpdf->Output('', 'S');
    try {
      $upload = $s3->upload($bucket, substr($response, 7) . ".pdf", $content, 'public-read');
      $link = htmlspecialchars($upload->get('ObjectURL'));

      $ret = $client->set($reportPrefix.":" . substr($response, 7), $link);
      $client->disconnect();
    } catch(Exception $e) {
      echo 'err';
      die();
    }
  }
  sleep(5);
}
?>
