<?php

use PhpAmqpLib\Message\AMQPMessage;

/* Weather service */

function getHashMQ($sUid, $sParam) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'hashMaker',
                'uid' => $sUid, //uid name
                'param' => $sParam,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function hashMaker($sUid, $sExpression) {
    $sMess = PHP_EOL;
    foreach (hash_algos() as $sHashAlgo) {
        $sHash = hash($sHashAlgo, $sExpression, false);
        $sMess .= "$sHashAlgo ( " . strlen($sHash) . " ): $sHash" . PHP_EOL;
    }
    sendMessage($sUid, $sMess);
}

//function hashMaker($sUid, $sExpression) {
//    if (preg_match('/^\d\s(.)+/', $sExpression)) {
//        $sSalt = '';
//        $sString = substr($sExpression, 2);
//
//        if (preg_match('/(\|){3}/', $sString)) {
//            $sSalt = explode('|||', $sString)[1];
//            $sString = explode('|||', $sString)[0];
//        }
//
//        switch ($sExpression[0]) {
//            case '1'://md5
//                sendMessage($sUid, md5($sString));
//                break;
//            case '2'://md5
//                sendMessage($sUid, md5($sString));
//                break;
//            default:
//                sendMessage($sUid, _trlt($sUid, '_Not supported expression'));
//                break;
//        }
//    } else {
//        sendMessage($sUid, _trlt($sUid, '_Not supported expression'));
//    }
//}
