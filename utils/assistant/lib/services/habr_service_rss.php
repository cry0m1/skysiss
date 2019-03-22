<?php

use PhpAmqpLib\Message\AMQPMessage;

//include(ROOT_PATH . '/library/ganon.php');

/* Habr service */

function habrParseMQ($sUid) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'habrParse',
                'uid' => $sUid, //uid name
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function habrParse($sUid) {
    sendMessage($sUid, _trlt($sUid, '_wait please, we prepare data'));
    if ($aHabraData = memcacheGet('habr')) {
        renderHabr($sUid, $aHabraData);
    } else {
        $oXml = new SimpleXmlElement(file_get_contents('http://habrahabr.ru/rss/hubs/'));

        $aHabraTitles = $aHabraSnippets = $aHabraLinks = array();
        foreach ($oXml->channel->item as $oEntry) {
            $aHabraTitles[] = $oEntry->title;
            $aHabraLinks[] = $oEntry->link;
            $aHabraSnippets[] = str_replace("\n", ' ', strip_tags($oEntry->description));
        }

        $aHabraData = array();
        foreach ($aHabraTitles as $iKey => $sValue) {
            $aHabraData[$iKey]['title'] = trim($sValue);
            $aHabraData[$iKey]['link'] = trim($aHabraLinks[$iKey]);

            $sSnippet = trim(str_replace('Читать дальше →', '', $aHabraSnippets[$iKey]));
            if (strlen($sSnippet) > 320) {
                $iSpacePos = strpos($sSnippet, ' ', 300);
            } else {
                $iSpacePos = strpos($sSnippet, ' ', 75);
            }
            $aHabraData[$iKey]['snippet'] = substr($sSnippet, 0, $iSpacePos) . '...';
        }
//        $fHtml = file_get_dom('http://habrahabr.ru/', true, false, null, true);
//        $aHabraTitles = $aHabraSnippets = $aHabraLinks = array();
//
//        $iPages = 0;
//        foreach ($fHtml->select('ul[id="nav-pages"] li') as $oElement) {
//            $iPages++;
//        }
//
//        foreach ($fHtml->select('a[class]') as $oElement) {
//            if ('post_title' === $oElement->getAttribute('class')) {
//                $aHabraTitles[] = $oElement->getInnerText();
//                $aHabraLinks[] = $oElement->getAttribute('href');
//            }
//        }
//
//        foreach ($fHtml->select('div[class]') as $oElement) {
//            if ('content html_format' === $oElement->getAttribute('class')) {
//                $aHabraSnippets[] = strip_tags($oElement->getInnerText());
//            }
//        }
//
//        for ($iInd = 2; $iInd <= $iPages; $iInd++) {
//            $fHtml = file_get_dom("http://habrahabr.ru/page$iInd/", true, false, null, true);
//            foreach ($fHtml->select('a[class]') as $oElement) {
//                if ('post_title' === $oElement->getAttribute('class')) {
//                    $aHabraTitles[] = $oElement->getInnerText();
//                    $aHabraLinks[] = $oElement->getAttribute('href');
//                }
//            }
//
//            foreach ($fHtml->select('div[class]') as $oElement) {
//                if ('content html_format' === $oElement->getAttribute('class')) {
//                    $aHabraSnippets[] = strip_tags($oElement->getInnerText());
//                }
//            }
//        }
//
//        $aHabraData = array();
//        foreach ($aHabraTitles as $iKey => $sValue) {
//            $aHabraData[$iKey]['title'] = trim($sValue);
//            $aHabraData[$iKey]['link'] = trim($aHabraLinks[$iKey]);
//
//            $sSnippet = trim(str_replace('Читать дальше →', '', $aHabraSnippets[$iKey]));
//            if (strlen($sSnippet) > 320) {
//                $iSpacePos = strpos($sSnippet, ' ', 300);
//            } else {
//                $iSpacePos = strpos($sSnippet, ' ', 75);
//            }
//            $aHabraData[$iKey]['snippet'] = substr($sSnippet, 0, $iSpacePos) . '...';
//        }

        if (!count($aHabraData)) {
            sendMessage($sUid, _trlt($sUid, '_error0, wtf?'));
            return false;
        } else {
            memcacheSet('habr', $aHabraData, 14400); //4h
            renderHabr($sUid, $aHabraData);
        }
    }
}

function renderHabr($sUid, $aHabraData) {
    $iMessagesAtAll = (int) ceil(count($aHabraData) / 10);

    for ($i = 0; $i < $iMessagesAtAll; $i++) {
        $sMess = '';
        for ($j = 0; $j < 10; $j++) {
            if (isset($aHabraData[$j + 10 * $i]['title'])) {
                $sMess .= PHP_EOL;
                $sMess .= $aHabraData[$j + 10 * $i]['title'] . PHP_EOL;
                $sMess .= $aHabraData[$j + 10 * $i]['link'] . PHP_EOL;
                $sMess .= $aHabraData[$j + 10 * $i]['snippet'] . PHP_EOL;
                $sMess .= '------------------------';
            } else {
                break;
            }
        }
        sendMessage($sUid, $sMess);
    }
}
