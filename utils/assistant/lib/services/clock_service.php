<?php

use PhpAmqpLib\Message\AMQPMessage;

/* Weather service */

function getClockMQ($sUid, $sParam) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'getClock',
                'uid' => $sUid, //uid name
                'param' => $sParam,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function getClock($sUid, $sCityName) {
    if ($sClockData = memcacheGet('clock' . $sCityName)) {
        /* Show weather */
        sendMessage($sUid, ucfirst($sCityName) . ':' . PHP_EOL . $sClockData);
    } else {
        list($aLocations, $sLocationMess) = searchClockLocation($sCityName);
        if ('error' === $sLocationMess) {
            sendMessage($sUid, _trlt($sUid, '_Invalid city, wtf?'));
            return true;
        }

        if (count($aLocations) > 1) {
            /* Show location list */
            sendMessage($sUid, PHP_EOL . _trlt($sUid, '_Choose one from:') . $sLocationMess);
            return true;
        }

        /* Show clock */
        if (!$sClockData = getClockByLocation($sUid, $aLocations)) {
            sendMessage($sUid, _trlt($sUid, '_Error while getting clock'));
            return true;
        } else {
            /* Save clock to cache */
            memcacheSet('clock' . $sCityName, $sClockData, 300);
            sendMessage($sUid, ucfirst($sCityName) . ':' . PHP_EOL . $sClockData);
            return true;
        }
    }
}

function searchClockLocation($sLocation) {
    /* Search city */
    $sJsonString = file_get_contents("http://maps.google.com/maps/api/geocode/json?address=" .
            urlencode($sLocation) . "&sensor=false");
    $mParsedJson = json_decode($sJsonString);

    if ($mParsedJson->status == 'ZERO_RESULTS') {
        $aData = array();
        $sMess = 'error';
    } else {
        $sMess = '' . PHP_EOL;
        foreach ($mParsedJson->results as $iKey => $oValue) {
            $aData[$iKey]['city'] = $oValue->address_components[0]->long_name;
            $aData[$iKey]['area '] = $oValue->address_components[1]->long_name;
            $aData[$iKey]['country'] = $oValue->address_components[2]->long_name;
            $aData[$iKey]['region'] = $oValue->address_components[3]->long_name;


            $aData[$iKey]['geometry_lat'] = $oValue->geometry->location->lat;
            $aData[$iKey]['geometry_lng'] = $oValue->geometry->location->lng;

            $sMess .= $oValue->address_components[0]->long_name .
                    ", " . $oValue->address_components[1]->long_name .
                    ", " . $oValue->address_components[2]->long_name .
                    ", " . $oValue->address_components[3]->long_name . PHP_EOL;
        }
    }

    return array($aData, $sMess);
}

function getClockByLocation($sUid, $aLocations) {
    /* 5 day forecast */
    $sJsonString = file_get_contents("http://ws.geonames.org/timezoneJSON?lat=" .
            $aLocations[0]['geometry_lat'] . "&lng=" .
            $aLocations[0]['geometry_lng'] . "&username=soska4u&style=full");

    $mParsedJson = json_decode($sJsonString);

    if (isset($mParsedJson->status)) {
        $aWData = 'error';
    } else {
        $sMess = '' . PHP_EOL;
        $sMess .= _trlt($sUid, '_Time') . ': ' . $mParsedJson->time . PHP_EOL;
        $sMess .= _trlt($sUid, '_Timezone') . ': ' . $mParsedJson->timezoneId . PHP_EOL;
        $sMess .= _trlt($sUid, '_DST offset (hours)') . ': ' . $mParsedJson->dstOffset . PHP_EOL;
        $sMess .= _trlt($sUid, '_GMT offset (hours)') . ': ' . $mParsedJson->gmtOffset . PHP_EOL;
        $sMess .= _trlt($sUid, '_Sunrise') . ': ' . $mParsedJson->sunrise . PHP_EOL;
        $sMess .= _trlt($sUid, '_Sunset') . ': ' . $mParsedJson->sunset . PHP_EOL;
    }

    return $sMess;
}
