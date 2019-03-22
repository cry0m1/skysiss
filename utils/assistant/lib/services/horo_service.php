<?php

use PhpAmqpLib\Message\AMQPMessage;

/* Horoscope service */

function getHoroMQ($sUid, $sParam) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'getHoro',
                'uid' => $sUid, //uid name
                'param' => $sParam,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function getHoro($sUid, $iSign) {
    if (!preg_match('/(\d){1,2}/', $iSign)) {
        sendMessage($sUid, _trlt($sUid, '_error0, wtf?'));
        return true;
    }

    $aSigns = array(
        1 => 'aries', //Овен
        2 => 'leo', //Лев 
        3 => 'sagittarius', //Стрелец 
        4 => 'taurus', //Телец 
        5 => 'virgo', //Дева 
        6 => 'capricorn', //Козерог 
        7 => 'gemini', //Близнецы 
        8 => 'libra', //Весы 
        9 => 'aquarius', //Водолей 
        10 => 'cancer', //Рак 
        11 => 'scorpio', //Скорпион 
        12 => 'pisces', //Рыбы
    );

    foreach ($aSigns as $iKey => $sSign) {
        if ($iKey === (int) $iSign) {
            break;
        }
    }

    $oUser = getUser($sUid);

    if ($sHoroData = memcacheGet('horo' . $oUser->lang_short_name . $iSign)) {
        /* Show horo */
        sendMessage($sUid, ucfirst(_trlt($sUid, "_$sSign")) . PHP_EOL . $sHoroData);
    } else {
        if (!getAllHoro($sUid)) {
            sendMessage($sUid, _trlt($sUid, '_error1, wtf?'));
            return true;
        }

        if ($sHoroData = memcacheGet('horo' . $oUser->lang_short_name . $iSign)) {
            /* Show horo */
            sendMessage($sUid, ucfirst(_trlt($sUid, "_$sSign")) . PHP_EOL . $sHoroData);
        } else {
            sendMessage($sUid, _trlt($sUid, '_error2, wtf?'));
        }
        return true;
    }
}

function getAllHoro($sUid) {
    $oUser = getUser($sUid);

    switch ($oUser->lang_short_name) {
        case 'ru':
            $aSigns = array(
                1 => 'aries', //Овен
                2 => 'leo', //Лев 
                3 => 'sagittarius', //Стрелец 
                4 => 'taurus', //Телец 
                5 => 'virgo', //Дева 
                6 => 'capricorn', //Козерог 
                7 => 'gemini', //Близнецы 
                8 => 'libra', //Весы 
                9 => 'aquarius', //Водолей 
                10 => 'cancer', //Рак 
                11 => 'scorpio', //Скорпион 
                12 => 'pisces', //Рыбы
            );

            $sXmlString = simplexml_load_string(file_get_contents("http://img.ignio.com/r/export/utf/xml/daily/com.xml"));
            $mParsedXml = json_decode(json_encode($sXmlString));

            if (isset($mParsedXml->aries)) {
                foreach ($aSigns as $iKey => $sSign) {
                    $sMess = '' . PHP_EOL;
                    $sMess .= _trlt($sUid, '_Today: ') . $mParsedXml->{$sSign}->today . PHP_EOL;
                    $sMess .= _trlt($sUid, '_Tomorrow: ') . $mParsedXml->{$sSign}->tomorrow . PHP_EOL;
                    $sMess .= _trlt($sUid, '_The day after tomorrow: ') . $mParsedXml->{$sSign}->tomorrow02;

                    /* Save to cache */
                    memcacheSet('horo' . $oUser->lang_short_name . $iKey, $sMess, 43200); //12h
                }
                return true;
            } else {
                return false;
            }
            break;
        case 'es':
            $aSigns = array(
                1 => 'Áries',
                4 => 'Touro',
                7 => 'Gêmeos',
                10 => 'Câncer',
                2 => 'Leão',
                5 => 'Virgem',
                8 => 'Libra',
                11 => 'Escorpião',
                3 => 'Sagitário',
                6 => 'Capricórnio',
                9 => 'Aquário',
                12 => 'Peixes',
            );

            $sJsonString = file_get_contents("http://developers.agenciaideias.com.br/horoscopo/json");
            $mParsedJson = json_decode($sJsonString);

            if (isset($mParsedJson->data)) {
                foreach ($mParsedJson->signos as $oSign) {
                    $sMess = '' . PHP_EOL;
                    $sMess .= _trlt($sUid, '_Tomorrow: ') . $oSign->msg;

                    /* Save to cache */
                    foreach ($aSigns as $iKey => $sSign) {
                        if (strtolower($sSign) == strtolower($oSign->nome)) {
                            break;
                        }
                    }
                    memcacheSet('horo' . $oUser->lang_short_name . $iKey, $sMess, 43200); //12h
                }
                return true;
            } else {
                return false;
            }
            break;
        default:
            $aSigns = array(
                1 => 'aries', //Овен
                2 => 'leo', //Лев 
                3 => 'sagittarius', //Стрелец 
                4 => 'taurus', //Телец 
                5 => 'virgo', //Дева 
                6 => 'capricorn', //Козерог 
                7 => 'gemini', //Близнецы 
                8 => 'libra', //Весы 
                9 => 'aquarius', //Водолей 
                10 => 'cancer', //Рак 
                11 => 'scorpio', //Скорпион 
                12 => 'pisces', //Рыба 
            );

            $sXmlString = simplexml_load_file("http://www.astrology.com/horoscopes/daily-horoscope.rss");
            foreach ($sXmlString->channel->item as $aItem) {

                foreach ($aSigns as $iKey => $sSign) {
                    if (preg_match("/$sSign/", strtolower($aItem->title))) {
                        $sOrigDesc = (string) $aItem->description;

                        $sMess = '' . PHP_EOL;
                        $sMess .= _trlt($sUid, '_Tomorrow: ') . strip_tags(substr($sOrigDesc, 0, strpos($sOrigDesc, 'More horoscopes')));

                        /* Save to cache */
                        memcacheSet('horo' . $oUser->lang_short_name . $iKey, $sMess, 43200); //12h
                    }
                }
            }
            return true;
            break;
    }
}
