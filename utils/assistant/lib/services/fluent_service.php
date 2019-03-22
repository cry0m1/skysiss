<?php

require_once("DetectLanguage.php");
require_once("GoogleTranslate.php");

use PhpAmqpLib\Message\AMQPMessage;

/* Fluent service */

function getFluentMQ($sUid, $sParam) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'getFluent',
                'uid' => $sUid, //uid name
                'param' => $sParam,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function getFluent($sUid, $sParam) {
    $oUser = getUser($sUid);

    $sMessage = '';
    $bFoundTrFrom = false;
    $bFoundTrTo = false;

    /* Add memcache for non-registered before this negotiation user */
    if ($sOpponentUid = memcacheGet("fluent$sUid")) {
        $oOpponent = getUser($sOpponentUid);
        $aOpponentFluent = memcacheGet("fluent$oOpponent->rec_id");
        memcacheSet("fluent$oUser->rec_id", array($sOpponentUid, $aOpponentFluent[2], $aOpponentFluent[1]), 1200);
        memcacheDelete("fluent$sUid");
    }

    //command: !startnew uid de message
    if (preg_match('/!startnew/', $sParam) && count(explode(' ', $sParam)) === 4) {//new 
        $aCommand = explode(' ', $sParam);

        if ($sMyLocale = detectLang($aCommand[3])) {
            if ($aPossibleTr = initLangList()) {
                foreach ($aPossibleTr as $sTr) {
                    if (preg_match('/' . $sMyLocale . '/', $sTr)) {
                        $bFoundTr = true;
                    }
                    if (preg_match('/' . $aCommand[2] . '/', $sTr)) {
                        $bFoundTrTo = true;
                    }
                }
            }
        }

        if ($bFoundTr && $bFoundTrTo) {
            if (preg_match('/^[a-zA-Z][a-zA-Z0-9\.,\-_]{5,31}$/', $aCommand[1]) || preg_match('/^([0-9a-zA-Z].*?@([0-9a-zA-Z].*\.\w{2,4}))$/', $aCommand[1]) || preg_match('/^(\d)+$/', $aCommand[1])) {
                if ($oOpponent = getUser($aCommand[1])) {
                    sendMessage($sUid, _trlt($sUid, '_Successfully started.'));
                    $sMessage = $sUid . ' ' . _trlt($sUid, '_want to contact you with #fluent app (bidirectional translation on-the-fly).') . PHP_EOL
                            . _trlt($sUid, '_Choose #fluent app and continue negotiation.') . PHP_EOL . PHP_EOL
                            . apiTranslate($sMyLocale, $aCommand[2], $aCommand[3]);

                    memcacheSet("fluent$oOpponent->rec_id", array($sUid, $aCommand[2], $sMyLocale), 1200);
                } else {
                    sendMessage($sUid, _trlt($sUid, '_Wait please while opponent finishes registration to continue negotiation.'));
                    $sMessage = $sUid . ' ' . _trlt($sUid, '_want to contact you with #fluent app (bidirectional translation on-the-fly).') . PHP_EOL
                            . _trlt($sUid, '_Accept authorization and wait some seconds to confirm by link from another bot then continue negotiation here after that by choosing #fluent app.') . PHP_EOL . PHP_EOL
                            . _trlt($sUid, '_Opponent sends you: ') . apiTranslate($sMyLocale, $aCommand[2], $aCommand[3]);

                    memcacheSet("fluent" . $aCommand[1], $sUid, 1200);
                }
                sendMessage($aCommand[1], $sMessage);
                memcacheSet("fluent$oUser->rec_id", array($aCommand[1], $sMyLocale, $aCommand[2]), 1200);
            } else {
                sendMessage($sUid, _trlt($sUid, '_invalid command'));
            }
        } else {
            if (!$bFoundTr) {
                sendMessage($sUid, _trlt($sUid, '_your language is not supported'));
            }
            if (!$bFoundTrTo) {
                sendMessage($sUid, _trlt($sUid, '_your opponent language is not supported'));
            }
        }
    } else {//procced old
        if ($aFluent = memcacheGet("fluent$oUser->rec_id")) {
            sendMessage($aFluent[0], apiTranslate($aFluent[1], $aFluent[2], $sParam));
        } else {
            sendMessage($sUid, _trlt($sUid, '_no opponent'));
        }
    }
}

function initLangList() {
    /* Verify lang exists */
    if (!$aPossibleTr = memcacheGet('fluent_aPossibleTr')) {
        $sJsonString = file_get_contents("https://translate.yandex.net/api/v1.5/tr.json/getLangs?key=trnsl.1.1.20140619T084228Z.0a9fb688b7677729.67cf6a48e050e0cdc91139237d9cbdb24d8774fa");
        $mParsedJson = json_decode($sJsonString);

        if ($mParsedJson->dirs == null) {
            sendMessage($sUid, _trlt($sUid, '_3rd party API error'));
            return false;
        } else {
            $aPossibleTr = $mParsedJson->dirs;
            memcacheSet('fluent_aPossibleTr', $mParsedJson->dirs, 148000);
        }
    }

    return $aPossibleTr;
}

function detectLang($sMess) {
    $oDetectLanguage = new DetectLanguage('51dc18b7bb611a27b4e811573b52bd12');
    if (isset($oDetectLanguage->detect($sMess)[0]['language'])) {
        return $oDetectLanguage->detect($sMess)[0]['language'];
    } else {
        sendMessage($sUid, _trlt($sUid, '_Your language is not detected'));
    }
    return false;
}

function apiTranslate($sFromLocation, $sToLocation, $sText) {
    $oGoogleTranslate = new GoogleTranslate();

    $oGoogleTranslate->setLangFrom($sFromLocation);
    $oGoogleTranslate->setLangTo($sToLocation);
    return $oGoogleTranslate->translate($sText);
}
