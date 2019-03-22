<?php

use PhpAmqpLib\Message\AMQPMessage;

function getTowerMQ($sUid, $sParam) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'towerHanoi',
                'uid' => $sUid, //uid name
                'param' => $sParam,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

//array(5) {
//  [1] => array(4) {
//    [0] => string(6) "000000"
//    [1] => string(6) "001100"
//    [2] => string(6) "011110"
//    [3] => string(6) "111111"
//  }
//  [2] => array(4) {
//    [0] => string(6) "000000"
//    [1] => string(6) "000000"
//    [2] => string(6) "000000"
//    [3] => string(6) "000000"
//  }
//  [3] => array(4) {
//    [0] => string(6) "000000"
//    [1] => string(6) "000000"
//    [2] => string(6) "000000"
//    [3] => string(6) "000000"
//  }
//  ["turn_count"] => int(0)
//  ["level"] => int(3)
//}
function towerHanoi($sUid, $sMessage) {
    if (preg_match("/^[1-9]{1}$/", $sMessage)) {
        if ((int) $sMessage > 9) {
            /* Invalid turn */
            sendMessage($sUid, _trlt($sUid, '_Invalid game start'));
        } else {
            /* Start new game */
            newGame($sUid, $sMessage);
        }
    } else {
        if (preg_match("/^[123]-[123]$/", str_replace(' ', '', $sMessage))) {
            /* New turn */
            $aInput = explode('-', $sMessage);
            $oUser = getUser($sUid);

            if ($aGame = memcacheGet('tower' . $oUser->rec_id)) {
                $aGame = move($sUid, $aInput, $aGame);

                /* Render&send */
                sendMessage($sUid, render($sUid, $aGame));

                /* Save to cache */
                memcacheSet('tower' . $oUser->rec_id, $aGame);
            } else {
                sendMessage($sUid, _trlt($sUid, '_Start new game, last has ended'));
            }
        } else {
            /* Invalid turn */
            sendMessage($sUid, _trlt($sUid, '_Invalid turn'));
        }
    }
}

function render($sUid, $aGame) {
    $sAscii = PHP_EOL;
    $iLevel = $aGame['level'];

    $aSmile = array(':)', ':(', ':D');
    $iCenter = strlen($aGame[1][0]) / 2;
    $sColumn = $aGame[1][0];
    $sColumn[$iCenter] = 'I';

    //jabber
    if (preg_match('/@/', $sUid)) {
        for ($iI = 0; $iI <= $iLevel; $iI++) {
            $sAscii .= str_replace(array('0', '1'), array('        ', $aSmile[rand(0, 2)]), $aGame[1][$iI]);
            $sAscii .= str_replace(array('0', '1'), array('        ', $aSmile[rand(0, 2)]), $aGame[2][$iI]);
            $sAscii .= str_replace(array('0', '1'), array('        ', $aSmile[rand(0, 2)]), $aGame[3][$iI]);
            $sAscii .= PHP_EOL;
        }

        $sAscii .= str_replace(array('0', '1'), array('         ', $aSmile[rand(0, 2)]), $sColumn);
        $sAscii .= str_replace(array('0', '1'), array('         ', $aSmile[rand(0, 2)]), $sColumn);
        $sAscii .= str_replace(array('0', '1'), array('         ', $aSmile[rand(0, 2)]), $sColumn);
        $sAscii .= PHP_EOL;
    } else {//skype
        for ($iI = 0; $iI <= $iLevel; $iI++) {
            $sAscii .= str_replace(array('0', '1'), array('      ', $aSmile[rand(0, 2)]), $aGame[1][$iI]);
            $sAscii .= str_replace(array('0', '1'), array('      ', $aSmile[rand(0, 2)]), $aGame[2][$iI]);
            $sAscii .= str_replace(array('0', '1'), array('      ', $aSmile[rand(0, 2)]), $aGame[3][$iI]);
            $sAscii .= PHP_EOL;
        }

        $sAscii .= str_replace(array('0', '1'), array('       ', $aSmile[rand(0, 2)]), $sColumn);
        $sAscii .= str_replace(array('0', '1'), array('       ', $aSmile[rand(0, 2)]), $sColumn);
        $sAscii .= str_replace(array('0', '1'), array('       ', $aSmile[rand(0, 2)]), $sColumn);
        $sAscii .= PHP_EOL;
    }

    return $sAscii;
}

function addNulls($sMess, $iNullsCount) {
    for ($iI = 0; $iI < $iNullsCount; $iI++) {
        $sMess = '0' . $sMess . '0';
    }
    return $sMess;
}

function addNotNuls($sMess, $iNotNullsCount) {
    for ($iI = 0; $iI < $iNotNullsCount; $iI++) {
        $sMess = '1' . $sMess . '1';
    }

    return $sMess;
}

function newGame($sUid, $iLevel) {
    for ($iI = 0; $iI <= $iLevel; $iI++) {
        $aGame[1][$iI] = addNulls(addNotNuls('', $iI), $iLevel - $iI);
        $aGame[2][$iI] = addNulls('', $iLevel);
        $aGame[3][$iI] = addNulls('', $iLevel);
    }
    $aGame['turn_count'] = 0;
    $aGame['level'] = $iLevel;

    /* Render&send */
    sendMessage($sUid, render($sUid, $aGame));

    /* Save to cache */
    $oUser = getUser($sUid);
    memcacheSet('tower' . $oUser->rec_id, $aGame);
}

function move($sUid, $aInput, $aGame) {
    /* turn */
    $iFrom = $aInput[0];
    $iTo = $aInput[1];
    $iSizeFromMove = $iSizeToMove = 0;
    $bHasValidFrom = $bHasValidTo = false;

    /* validate  */
    foreach ($aGame[$iFrom] as $iKeyFrom => $sValue) {
        if ($iSizeFromMove = substr_count($sValue, '1')) {
            $bHasValidFrom = true;
            break;
        }
    }

    if (!$bHasValidFrom) {
        sendMessage($sUid, _trlt($sUid, '_Invalid turn'));
        return $aGame;
    }

    foreach ($aGame[$iTo] as $iKeyTo => $sValue) {
        if ($iSizeToMove = substr_count($sValue, '1')) {
            if ($iSizeFromMove < $iSizeToMove) {
                $bHasValidTo = true;
            }
            break;
        }
    }
    if (!$iSizeToMove) {
        $bHasValidTo = true;
    }

    if (!$bHasValidTo) {
        sendMessage($sUid, _trlt($sUid, '_Invalid turn'));
        return $aGame;
    }

    /* move */
    if (!$iSizeToMove) {
        $sDsikFrom = $aGame[$iFrom][$iKeyFrom];
        $sDsikTo = $aGame[$iTo][$iKeyTo];
        $aGame[$iFrom][$iKeyFrom] = $sDsikTo;
        $aGame[$iTo][$iKeyTo] = $sDsikFrom;
    } else {
        $sDsikFrom = $aGame[$iFrom][$iKeyFrom];
        $sDsikTo = $aGame[$iTo][$iKeyTo - 1];
        $aGame[$iFrom][$iKeyFrom] = $sDsikTo;
        $aGame[$iTo][$iKeyTo - 1] = $sDsikFrom;
    }
    $aGame['turn_count'] += 1;

    /* win */
    if ((substr_count($aGame[1][$aGame['level']], '0') === $aGame['level'] * 2 && substr_count($aGame[2][$aGame['level']], '0') === $aGame['level'] * 2) ||
            (substr_count($aGame[1][$aGame['level']], '0') === $aGame['level'] * 2 && substr_count($aGame[3][$aGame['level']], '0') === $aGame['level'] * 2)) {
        sendMessage($sUid, _trlt($sUid, '_you win in ') . $aGame['turn_count'] . _trlt($sUid, ' _turns'));

        /* Start new game */
//            newGame($sUid, count($aGame[0]));
    }

    return $aGame;
}
