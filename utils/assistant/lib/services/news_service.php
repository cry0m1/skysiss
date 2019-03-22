<?php

use PhpAmqpLib\Message\AMQPMessage;

/* News service */

function newsParseMQ($sUid) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'newsParse',
                'uid' => $sUid, //uid name
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function newsParse($sUid) {
    $oUser = getUser($sUid);

    sendMessage($sUid, _trlt($sUid, '_wait please, we prepare data'));
    if ($aNewsData = memcacheGet('news' . $oUser->lang_short_name)) {
        renderNews($sUid, $aNewsData);
    } else {
        switch ($oUser->lang_short_name) {
            case 'en':
                $oXml = new SimpleXmlElement(file_get_contents('http://downloads.bbc.co.uk/podcasts/worldservice/globalnews/rss.xml'));
                break;
            case 'ru':
                $oXml = new SimpleXmlElement(file_get_contents('http://news.rambler.ru/rss/head/'));
                break;
            case 'de':
                $oXml = new SimpleXmlElement(file_get_contents('http://www.spiegel.de/schlagzeilen/tops/index.rss'));
                break;
            case 'es':
                $oXml = new SimpleXmlElement(file_get_contents('http://www.bbc.co.uk/mundo/temas/internacional/index.xml'));
                break;
        }

        $aNewsTitles = $aNewsDates = $aNewsSnippets = $aNewsLinks = array();

        switch ($oUser->lang_short_name) {
            case 'en':
            case 'ru':
            case 'de':
                foreach ($oXml->channel->item as $oEntry) {
                    $aNewsDates[] = $oEntry->pubDate;
                    $aNewsTitles[] = $oEntry->title;
                    $aNewsLinks[] = $oEntry->link;
                    $aNewsSnippets[] = str_replace("\n", ' ', strip_tags($oEntry->description));
                }
                break;
            case 'es':
                foreach ($oXml->entry as $oEntry) {
                    $aNewsDates[] = $oEntry->published;
                    $aNewsTitles[] = $oEntry->title;
                    $aNewsLinks[] = (string) $oEntry->link['href'];
                    $aNewsSnippets[] = str_replace("\n", ' ', strip_tags($oEntry->summary));
                }
                break;
        }


        $aNewsData = array();
        foreach ($aNewsTitles as $iKey => $sValue) {
            $aNewsData[$iKey]['title'] = trim($sValue);
            $aNewsData[$iKey]['date'] = trim($aNewsDates[$iKey]);
            $aNewsData[$iKey]['link'] = trim($aNewsLinks[$iKey]);

            $sSnippet = trim($aNewsSnippets[$iKey]);
            if (strlen($sSnippet) > 320) {
                $iSpacePos = strpos($sSnippet, ' ', 300);
                $aNewsData[$iKey]['snippet'] = substr($sSnippet, 0, $iSpacePos) . '...';
            } else {
                $aNewsData[$iKey]['snippet'] = $sSnippet;
            }
        }

        if (!count($aNewsData)) {
            sendMessage($sUid, _trlt($sUid, '_error0, wtf?'));
            return false;
        } else {
            memcacheSet('news' . $oUser->lang_short_name, $aNewsData, 14400); //4h
            renderNews($sUid, $aNewsData);
        }
    }
}

function renderNews($sUid, $aNewsData) {
    $iMessagesAtAll = (int) ceil(count($aNewsData) / 10);

    for ($i = 0; $i < $iMessagesAtAll; $i++) {
        $sMess = '';
        for ($j = 0; $j < 10; $j++) {
            if (isset($aNewsData[$j + 10 * $i]['title'])) {
                $sMess .= PHP_EOL;
                $sMess .= $aNewsData[$j + 10 * $i]['title'] . PHP_EOL;
                $sMess .= $aNewsData[$j + 10 * $i]['date'] . PHP_EOL;
                $sMess .= $aNewsData[$j + 10 * $i]['link'] . PHP_EOL;
                $sMess .= $aNewsData[$j + 10 * $i]['snippet'] . PHP_EOL;
                $sMess .= '------------------------';
            } else {
                break;
            }
        }
        sendMessage($sUid, $sMess);
    }
}
