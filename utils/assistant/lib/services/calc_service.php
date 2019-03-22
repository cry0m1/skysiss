<?php

use PhpAmqpLib\Message\AMQPMessage;

/* Weather service */

function getCalcMQ($sUid, $sParam) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'calcExpression',
                'uid' => $sUid, //uid name
                'param' => $sParam,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function calcExpression($sUid, $sExpression) {
    $sExpression = str_replace(" ", "", str_replace(",", ".", strip_tags($sExpression)));
    $oUser = getUser($sUid);
    //$sExpression = str_replace("/", "\/", $sExpression);

    /* Operation with value from memory */
    if (preg_match('/m|M/', $sExpression)) {
        $iMemVal = memcacheGet($oUser->rec_id . 'calc');
        $sExpression = preg_replace('/m|M/', $iMemVal, $sExpression);
    }

    //if (preg_match('/^([m\(\)\-\+\/*\^]*\d+(\.\d+)?)*$/', str_replace('.', '', $sExpression))) {
    if (preg_match('/^([m\(\)\-\+\/*\^]*\d+(\.\d+\))?[m\(\)\-\+\/*\^]*)*$/', str_replace('.', '', $sExpression))) {
        $sEval = '=' . $sExpression;
        eval('$evald' . $sEval . ';');
        memcacheSet($oUser->rec_id . 'calc', $evald, 72000);

        sendMessage($sUid, "$sExpression = " . number_format_unlimited_precision($evald));
    } else {
        sendMessage($sUid, _trlt($sUid, '_Not supported expression'));
    }
}

function number_format_unlimited_precision($number, $decimal = '.') {
    $broken_number = explode($decimal, $number);
    return number_format($broken_number[0]) . $decimal . $broken_number[1];
}
