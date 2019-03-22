<?php

use PhpAmqpLib\Message\AMQPMessage;

//include(ROOT_PATH . '/library/ganon.php');

/* Weather service */

function statParseMQ($sUid) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'statParse',
                'uid' => $sUid, //uid name
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function statParse($sUid) {
    $oUser = getUser($sUid);

    if ($sMess = memcacheGet('stat' . $oUser->rec_id)) {
        sendMessage($sUid, $sMess);
    } else {
        $oStats = getServerStats($oUser->rec_id);
        $sMess = PHP_EOL;

        if (count($oStats)) {
            foreach ($oStats as $oStat) {
                $sMess .= '====== [' . long2ip($oStat->ip) . ' (' . $oStat->hostname . ')' . ' - ' . timeSince(strtotime($oStat->now) - strtotime($oStat->date_updated)) . '] ======' . PHP_EOL;
                $sMess .= str_replace(array('_', '*', '()'), array(' ', PHP_EOL, PHP_EOL), $oStat->status) . PHP_EOL;
            }
        } else {
            $sMess .= _trlt($sUid, '_No servers');
        }

        memcacheSet('stat' . $oUser->rec_id, $sMess, 10); //10s
        sendMessage($sUid, $sMess);
    }
}
