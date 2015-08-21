<?php

date_default_timezone_set('UTC');
require_once 'vendor/autoload.php';

/* Get filepath from GET or CLI */
header('Content-Type: text/plain');
if (isset($argv[1])) {
    $sExcelFilepath = $argv[1];
} elseif (isset($_GET['File'])) {
    $sExcelFilepath = $_GET['File'];
} else {
    if (php_sapi_name() == 'cli') {
        echo 'Please specify filename as the first argument' . PHP_EOL;
    } else {
        echo 'Please specify filename as a HTTP GET parameter "File", e.g., "/index.php?File=test.xlsx"';
    }
    exit;
}

/* tgz Archivation */
try {
    $sArchiveName = 'archive' . time() . '.tar';
    $oPharData = new PharData($sArchiveName);
    $oPharData->addFile($sExcelFilepath);
    $oPharData->compress(Phar::GZ); // copies to /path/to/my.phar.gz
    unset($oPharData);
    unlink($sArchiveName);
} catch (Exception $e) {
    echo "Exception : " . $e;
}

/* FTP transmitting */
//try {
//    $oFtp = new Ftp;
//    $oFtp->connect($host); // replace by real
//    $oFtp->login($username, $password); // replace by real
//    $oFtp->put($sExcelFilepath, $sExcelFilepath, FTP_BINARY);
//    $oFtp->tryDelete($sExcelFilepath);
//    $oFtp->close();
//} catch (FtpException $e) {
//    echo 'Exception: ', $e->getMessage();
//}

/* Excel parsing */
$sStartMem = memory_get_usage();
echo '---------------------------------' . PHP_EOL;
echo 'Starting memory: ' . $sStartMem . PHP_EOL;
echo '---------------------------------' . PHP_EOL;

try {
    $oSpreadsheet = new SpreadsheetReader($sExcelFilepath);
    $sBaseMem = memory_get_usage();

    $oSheets = $oSpreadsheet->Sheets();

    echo '---------------------------------' . PHP_EOL;
    echo 'Spreadsheets:' . PHP_EOL;
    print_r($oSheets);
    echo '---------------------------------' . PHP_EOL;
    echo '---------------------------------' . PHP_EOL;

    foreach ($oSheets as $iIndex => $sName) {
        echo '---------------------------------' . PHP_EOL;
        echo '*** Sheet ' . $sName . ' ***' . PHP_EOL;
        echo '---------------------------------' . PHP_EOL;

        $iTime = microtime(true);

        $oSpreadsheet->ChangeSheet($iIndex);

        foreach ($oSpreadsheet as $iKey => $sRow) {
            echo $iKey . ': ';
            if ($sRow) {
                print_r($sRow);
            } else {
                var_dump($sRow);
            }
            $sCurrentMem = memory_get_usage();

            echo 'Memory: ' . ($sCurrentMem - $sBaseMem) . ' current, ' . $sCurrentMem . ' base' . PHP_EOL;
            echo '---------------------------------' . PHP_EOL;

            if ($iKey && ($iKey % 500 == 0)) {
                echo '---------------------------------' . PHP_EOL;
                echo 'Time: ' . (microtime(true) - $iTime);
                echo '---------------------------------' . PHP_EOL;
            }
        }

        echo PHP_EOL . '---------------------------------' . PHP_EOL;
        echo 'Time: ' . (microtime(true) - $iTime);
        echo PHP_EOL;

        echo '---------------------------------' . PHP_EOL;
        echo '*** End of sheet ' . $sName . ' ***' . PHP_EOL;
        echo '---------------------------------' . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Exception: ', $e->getMessage();
}