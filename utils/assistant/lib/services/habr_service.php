<?php

use PhpAmqpLib\Message\AMQPMessage;
use PHPHtmlParser\Dom;

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
    $oDom = new Dom;

    sendMessage($sUid, _trlt($sUid, '_wait please, we prepare data'));
    if ($aHabraData = memcacheGet('habr')) {
        renderHabr($sUid, $aHabraData);
    } else {
        $aHabraTopic = $aHabraTitles = $aHabraSnippets = $aHabraLinks = array();

        /* INTERESTING */
//        $iPages = 10;
//        if ($iPages > 0) {
//            for ($iInd = 1; $iInd <= $iPages; $iInd++) {
//                $oDom->loadFromUrl("https://habrahabr.ru/interesting/page$iInd/");
//
//                foreach ($oDom->find('.post__flow') as $oElement) {
//                    $aHabraTopic[] = $oElement->text();
//                }
//
//                foreach ($oDom->find('.post__title_link') as $oElement) {
//                    $aHabraTitles[] = $oElement->text();
//                    $aHabraLinks[] = $oElement->getAttribute('href');
//                }
//
//                foreach ($oDom->find('.html_format') as $oElement) {
//                    $aHabraSnippets[] = strip_tags($oElement->text());
//                }
//            }
//        }

        /* TOP */
        $oDom->loadFromUrl('https://habrahabr.ru/top/weekly/');
        $iPages = 0;

        foreach ($oDom->find('ul[id="nav-pagess"] li') as $oElement) {
            $iPages++;
        }
        $iPages = $iPages > 6 ? 6 : $iPages;
        if ($iPages > 0) {
            for ($iInd = 1; $iInd <= $iPages; $iInd++) {
                $oDom->loadFromUrl("https://habrahabr.ru/top/weekly/page$iInd/");

                foreach ($oDom->find('.post_preview') as $oPost) {
                    $aHabraTopic[] = strip_tags(trim($oPost->find('.inline-list__item_hub')->outerHTML));
                    $aHabraTitles[] = strip_tags(trim($oPost->find('.post__title_link')->outerHTML));
                    $aHabraLinks[] = $oPost->find('.post__title_link')->getAttribute('href');
                    $aHabraSnippets[] = strip_tags(trim($oPost->find('.post__body')->outerHTML));


                }
//
//                foreach ($oDom->find('.inline-list__item_hub') as $oElement) {
//                    $aHabraTopic[] = strip_tags(trim($oElement->outerHTML));
//                }
//
//                foreach ($oDom->find('.post__title_link') as $oElement) {
//                    $aHabraTitles[] = strip_tags(trim($oElement->outerHTML));
//                    $aHabraLinks[] = $oElement->getAttribute('href');
//                }
//
//                foreach ($oDom->find('.post__body') as $oElement) {
////                    if (!substr(strip_tags(trim($oElement->text())), 0, 10)){
////                        echo "<pre>";
////                        var_dump(trim($oElement->text()));
////                        var_dump(trim($oElement->outerHTML));
////                        echo "</pre>";
////                    }
//                    $aHabraSnippets[] = strip_tags(trim($oElement->outerHTML));
//                }
            }
        }

        /* Exclude duplicates */
        $aHabraTmpData = array();
        foreach ($aHabraTitles as $iKey => $sValue) {
            $aHabraTmpData[md5(trim($sValue))]['title'] = trim($aHabraTopic[$iKey] . ': ' . $sValue);
            $aHabraTmpData[md5(trim($sValue))]['link'] = trim($aHabraLinks[$iKey]);

            $sSnippet = trim(str_replace('Читать дальше →', '', $aHabraSnippets[$iKey]));

            if (strlen($sSnippet) > 320) {
                $iSpacePos = strpos($sSnippet, ' ', 300) ? strpos($sSnippet, ' ', 300) : 0;
            } else {
                $iSpacePos = strpos($sSnippet, ' ', 75) ? strpos($sSnippet, ' ', 75) : 0;
            }

            $aHabraTmpData[md5(trim($sValue))]['snippet'] = substr($sSnippet, 0, $iSpacePos) . '...';
        }

        $aHabraData = array();
        foreach ($aHabraTmpData as $aValue) {
            $aHabraData[] = array(
                'title' => $aValue['title'],
                'link' => $aValue['link'],
                'snippet' => $aValue['snippet'],
            );
        }

        if (!count($aHabraData)) {
            sendMessage($sUid, _trlt($sUid, '_error0, wtf?'));
            return false;
        } else {
            memcacheSet('habr', $aHabraData, 28800); //8h
            renderHabr($sUid, $aHabraData);
        }

        unset($aHabraData);
        unset($aHabraTmpData);
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

