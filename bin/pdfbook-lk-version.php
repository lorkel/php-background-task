<?php
header("Access-Control-Allow-Origin: *");
ini_set('memory_limit', '1024M');

require_once($relativePath . 'www/_inc/conn.php');
require_once($relativePath . 'vendor/autoload.php');
$conn = getConnection();

require_once($relativePath . 'www/project/reports/_inc/functions.php');
require_once($relativePath . 'www/project/reports/layouts/text.php');
require_once($relativePath . 'www/project/reports/layouts/master.php');
require_once($relativePath . 'www/project/reports/layouts/survey.php');
// require_once($relativePath . 'www/project/reports/layouts/quantity.php');

$s3 = Aws\S3\S3Client::factory([
  'version' => '2006-03-01',
  'region' => 'us-west-2'
]);

$bucket = getenv('AWS_S3_REPORT_BUCKET')?: die('No "AWS_S3_REPORT_BUCKET" config var in found in env!');
$client = new Predis\Client(getenv('REDIS_URL'));

while(1) {
  $responses = $client->keys('report:*');
  foreach($responses as $response) {
    $filterValue = $client->get($response);

    if(strpos($filterValue, 'https://') !== false) {
      echo 'Report successfully generated.';
      $ret = $client->del($response);
      exit();
    }

    // debugging
    // $response = 'report:654-project-text';
    // $response = 'report:3529-plan-text';
    // $response = 'report:5109-plan-survey';
    // $filterValue = '';
    // $filterValue = " AND status='A'----";
    // $filterValue = " AND status='A'-- AND (plotTypeID='15697')--";
    // $filterValue = " AND (installType='New') AND status='A'-- AND (plotTypeID='15697')--";

    $reportValue = substr($response, 7);
    $reportPieces = explode('-', $reportValue);
    $filterPieces = explode('--', $filterValue);

    $filterQuery = $filterPieces[0];
    $filterSignTypeQuery = $filterPieces[1];
    $filterBeaconTypeQuery = $filterPieces[2];

    if ($_SERVER["HTTP_HOST"]!="localhost" && $_SERVER["HTTP_HOST"]!="192.168.1.6" && $_SERVER["HTTP_HOST"]!="127.0.0.1") {
      $ret = $client->set("report:" . substr($response, 7), 0);
    }

    //  ___
    // | _ \__ _ _ _ __ _ _ __  ___
    // |  _/ _` | '_/ _` | '  \(_-<
    // |_| \__,_|_| \__,_|_|_|_/__/
    //

    if(isset($reportPieces[0])) {
      // projectID or planID
    	$reportID = intval('0'.$reportPieces[0]);
    } else {
      echo 'Need report id.';
      $ret = $client->del($response);
      exit();
    }

    if(isset($reportPieces[1])) {
      // project or plan
      $reportType = $reportPieces[1];
    } else {
    	echo 'Need report type.';
      $ret = $client->del($response);
      exit();
    }

    if(isset($reportPieces[2])) {
      // text, master, survey, or planInfo
      $reportLayout = $reportPieces[2];
    } else {
    	echo 'Need report layout.';
      $ret = $client->del($response);
      exit();
    }

    //  ___                   _     _____
    // | _ \___ _ __  ___ _ _| |_  |_   _|  _ _ __  ___
    // |   / -_) '_ \/ _ \ '_|  _|   | || || | '_ \/ -_)
    // |_|_\___| .__/\___/_|  \__|   |_| \_, | .__/\___|
    //         |_|                       |__/|_|

    if($reportType=='project') {
      getProject($reportID, $conn);
      getCompany($companyID, $conn);
      getSigns('project', $reportID, $filterQuery, $filterSignTypeQuery, $conn);
      getBeacons('project', $reportID, $filterQuery, $filterSignTypeQuery, $conn);
      getGroups('project', $reportID, $conn);
      getPlans('project', $reportID, $conn);
      getPlotTypes($reportID, $libraryArray, $conn);

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
      getPlans('plan', $reportID, $conn);
      getGroups('plan', $planArray[0]['groupID'], $conn);
      getProject($projectID, $conn);
      getCompany($companyID, $conn);
      getPlotTypes($reportID, $libraryArray, $conn);
      getSigns('plan', $reportID, $filterQuery, $filterSignTypeQuery, $conn);
      getBeacons('plan', $reportID, $filterQuery, $filterSignTypeQuery, $conn);

      if($reportLayout=='master') {
        getPlanPhotos('all', $reportID, $conn);
      } else if ($reportLayout=='survey') {
        getPlanPhotos('all', $reportID, $conn);
      } else if ($reportLayout=='planInfo') {
        getPlanInfo('plan', $reportID, $conn);
      }

      // combine arrays
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

      // debugging
      // echo '<pre>';
      // // print_r(array_keys(get_defined_vars()));
      // // print_r($planPhotoArray);
      // echo '</pre>';
      // die();

      // then sort by plot name
      // can use usort because it's just one plan
      usort($plotArray, function($a, $b) {
        return $a['plotName'] <=> $b['plotName'];
      });

      $reportTypeText = '<h1>'.$groupArray[0]['groupName'].'</h1><h1>'.$planArray[0]['planName'].'</h1>';
      $reportFilename = preg_replace("/[^A-Za-z0-9]/", "", $projectName).'-'.preg_replace("/[^A-Za-z0-9]/", "", $groupArray[0]['groupName']).'-'.preg_replace("/[^A-Za-z0-9]/", "", $planArray[0]['planName']);

    }

    // debugging -- just output 10 records for faster testing
    // $plotArray = array_slice($plotArray,0,5);

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
          'typeDescription' => $iV['typeDescription'],
          'typeThumbnail' => $iV['typeThumbnail'],
          'recordOrigin' => $iV['recordOrigin']
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
      $reportFilename = $reportFilename.'-TextOnly';
      $pageWidth = 216;
      $pageHeight = 279;
    } else if($reportLayout=='master') {
      $reportLayoutText = '<h1>Master Document</h1>';
      $reportFilename = $reportFilename.'-Master';
      $pageWidth = 216;
      $pageHeight = 279;
    } else if($reportLayout=='survey') {
      $reportLayoutText = '<h1>Survey Document</h1>';
      $reportFilename = $reportFilename.'-Survey';
      $pageWidth = 216;
      $pageHeight = 279;
    } else if($reportLayout=='quantity') {
      $reportLayoutText = '<h1>Quantities</h1>';
      $reportFilename = $reportFilename.'-Quantities';
      $pageWidth = 216;
      $pageHeight = 279;
    } else {
      echo 'Invalid report type.';
      $ret = $client->del($response);
      exit();
    }

    // 11 x 17:
    // $pageWidth = 279;
    // $pageHeight = 432;

    //  __  __ _          ___                   _   _   _
    // |  \/  (_)___ __  | __|__ _ _ _ __  __ _| |_| |_(_)_ _  __ _
    // | |\/| | (_-</ _| | _/ _ \ '_| '  \/ _` |  _|  _| | ' \/ _` |
    // |_|  |_|_/__/\__| |_|\___/_| |_|_|_\__,_|\__|\__|_|_||_\__, |
    //                                                        |___/

    // timezone
    date_default_timezone_set('America/Los_Angeles');

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

    // allow for non-english characters
    $mpdf->autoScriptToLang = true;
    $mpdf->autoLangToFont = true;

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
              <img src="https://s3-us-west-2.amazonaws.com/resources.mustardsquare.com/'.$companyLogoColor.'" class="company-logo" /><br />
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
      createTextPDF($mpdf, $client, $response, $labelArray, $plotTypesArray, $plotArray, $customFieldArray);
    } else if($reportLayout=='master') {
      createMasterPDF($mpdf, $client, $response, $projectName, $groupArray[0]['groupName'], $planArray[0]['planName'], $labelArray, $plotTypesArray, $plotArray, $customFieldArray, $planPhotoArray);
    } else if($reportLayout=='survey') {
      createSurveyPDF($mpdf, $client, $response, $projectName, $groupArray[0]['groupName'], $planArray[0]['planName'], $labelArray, $plotTypesArray, $plotArray, $customFieldArray, $planPhotoArray);
    } else if($reportLayout=='quantity') {
      // createSurveyPDF($mpdf, $client, $labelArray, $plotArray, $customFieldArray, $planPhotoArray);
    }

    // debugging
    // $mpdf->Output($reportFilename, 'I');

    $content = $mpdf->Output('', 'S');
    try {
      $upload = $s3->upload($bucket, $reportFilename . ".pdf", $content, 'public-read');
      $link = htmlspecialchars($upload->get('ObjectURL'));

      if ($_SERVER["HTTP_HOST"]!="localhost" && $_SERVER["HTTP_HOST"]!="192.168.1.6" && $_SERVER["HTTP_HOST"]!="127.0.0.1") {
        $ret = $client->set("report:" . substr($response, 7), $link);
        $client->disconnect();
      }

      clearstatcache();

    } catch(Exception $e) {
      echo 'Error uploading to S3.';
      $ret = $client->del($response);
      exit();
    }
  }
  sleep(5);
}
?>
