<?php

use PhpAmqpLib\Message\AMQPMessage;

/* Weather service */

function getWeatherMQ($sUid, $sParam) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'getWeather',
                'uid' => $sUid, //uid name
                'param' => $sParam,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function getWeather($sUid, $sCityName) {
    /*
     * if nothing, search User table for a city - search weather
     * if error, try to get city list from memcache (exists? get weather and save to memcache)
     * if no, search for locations (save if > 1 to memcache),
     * create list if needed and save it to memcache 'choose_city_'
     * if no list, get weather and save to memcache
     *
     * if int, get memcache 'choose_city_' and get weather and save to memcache
     *
     * if string(try to get city list from memcache)
     * if no, search for locations(save if > 1 to memcache),
     * create list if needed and save it to memcache 'choose_city_'
     *
     * save city???
     *
     */
    $oUser = getUser($sUid);

    if ($aWeatherData = memcacheGet('weather' . $sCityName)) {
        /* Show weather */
        sendMessage($sUid, _trlt($sUid, '_Your last city is:') . ' ' . ucfirst($sCityName));
        sendMessage($sUid, ucfirst($sCityName) . ': ');
        sendMessage($sUid, generateWeatherMessage($sUid, $aWeatherData));
    } else {
        $aExplodedCityName = explode(',', $sCityName);
        if (count($aExplodedCityName) == 1 && $sLocationMess = memcacheGet('weather_location' . $sCityName)) {
            sendMessage($sUid, $sLocationMess);
            return true;
        } else {
            $bValidCity = false;
            for ($iIndex = 0; $iIndex < 10; $iIndex++) {
                if ($sCityName == memcacheGet('weather_location_item' . $oUser->rec_id . $iIndex)) {
                    $bValidCity = true;
                    break;
                }
            }

            if ($bValidCity) {
                
            } else {
                list($aLocations, $sLocationMess) = searchWeatherLocation($sCityName);
                if ('error' === $sLocationMess) {
                    if (tryGetWeather($sUid, $sCityName)) {
                        return true;
                    }

                    sendMessage($sUid, _trlt($sUid, '_Invalid city, wtf?'));
                    return true;
                }

                if (count($aLocations) > 1) {
                    /* Show location list */
                    memcacheSet('weather_location' . $sCityName, $sLocationMess, 148000);

                    foreach ($aLocations as $iKey => $aValue) {
                        memcacheSet('weather_location_item' . $oUser->rec_id . $iKey, strtolower($aValue['city'] . ", " . $aValue['country'] . " (" . $aValue['region'] . ")"));
                    }

                    sendMessage($sUid, $sLocationMess);
                    return true;
                }
            }
        }

        /* Show weather */
        return tryGetWeather($sUid, $sCityName);
    }
}

function tryGetWeather($sUid, $sCityName) {
    $oUser = getUser($sUid);
    $aWeatherData = getWeatherByLocation($sCityName);

    if (!is_array($aWeatherData)) {
        sendMessage($sUid, _trlt($sUid, '_Error while getting weather, wtf?'));
        return true;
    } else {
        /* Save weather to cache */
        memcacheSet('weather' . $sCityName, $aWeatherData);

        sendMessage($sUid, ucfirst($sCityName) . ':' . PHP_EOL . generateWeatherMessage($sUid, $aWeatherData));
        return true;
    }
}

function searchWeatherLocation($sLocation) {
    /* Search city */
    $sJsonString = file_get_contents("http://api.worldweatheronline.com/free/v1/search.ashx?q=" .
            urlencode($sLocation) . "&format=json&popular=yes&key=4zx2xptcuhvrv34khm74ck3c");
    $mParsedJson = json_decode($sJsonString);

    if ($mParsedJson->search_api == null) {
        $aData = array();
        $sMess = 'error';
    } else {
        if (array_key_exists('data', $mParsedJson)) {
            $aData = array();
            $sMess = 'error';
        } else {
            $sMess = '' . PHP_EOL;
            foreach ($mParsedJson->search_api->result as $iKey => $oValue) {
                $aData[$iKey]['city'] = $oValue->areaName[0]->value;
                $aData[$iKey]['country'] = $oValue->country[0]->value;
                $aData[$iKey]['region'] = $oValue->region[0]->value;

                //$sMess .= "$iKey. " . $oValue->areaName[0]->value .
                $sMess .= $oValue->areaName[0]->value .
                        ", " . $oValue->country[0]->value .
                        " (" . $oValue->region[0]->value . ")" . PHP_EOL;
            }
        }
    }

    return array($aData, $sMess);
}

function getWeatherByLocation($sLocation) {
    /* 5 day forecast */
    $sJsonString = file_get_contents("http://api.worldweatheronline.com/free/v1/weather.ashx?q=" .
            urlencode($sLocation) . "&format=json&num_of_days=5&key=4zx2xptcuhvrv34khm74ck3c");

    $mParsedJson = json_decode($sJsonString);
    if (isset($mParsedJson->data->error)) {
        $aWData = 'error';
    } else {
        $aWData['current']['cloud'] = $mParsedJson->data->current_condition[0]->cloudcover;
        $aWData['current']['humidity'] = $mParsedJson->data->current_condition[0]->humidity;
        $aWData['current']['c'] = $mParsedJson->data->current_condition[0]->temp_C;
        $aWData['current']['f'] = $mParsedJson->data->current_condition[0]->temp_F;
        $aWData['current']['weatherDesc'] = $mParsedJson->data->current_condition[0]->weatherDesc[0]->value;

        foreach ($mParsedJson->data->weather as $oWeather) {
            $aWData['forecast'][$oWeather->date]['max_c'] = $oWeather->tempMaxC;
            $aWData['forecast'][$oWeather->date]['max_f'] = $oWeather->tempMaxF;
            $aWData['forecast'][$oWeather->date]['min_c'] = $oWeather->tempMinC;
            $aWData['forecast'][$oWeather->date]['min_f'] = $oWeather->tempMinF;
            $aWData['forecast'][$oWeather->date]['weatherDesc'] = $oWeather->weatherDesc[0]->value;
        }
    }

    return $aWData;
}

function generateWeatherMessage($sUid, $aWeatherData) {
    $sImType = NULL;
    if (preg_match('/@/', $sUid)) {
        
    } else {
        $sImType = 'SK';
    }

    $sMess = '' . PHP_EOL;
    $sMess .= _trlt($sUid, '_Cloud ') . $aWeatherData['current']['cloud'] . '%' . PHP_EOL;
    $sMess .= _trlt($sUid, '_Humidity ') . $aWeatherData['current']['humidity'] . '%' . PHP_EOL;
    $sMess .= $aWeatherData['current']['c'] . 'C (' . $aWeatherData['current']['f'] . 'F) ' . _trlt($sUid, '_' . $aWeatherData['current']['weatherDesc']);
    if ($sImType) {
        if ('Sunny' === $aWeatherData['current']['weatherDesc']) {
            $sMess .= ' (sun)';
        } elseif (in_array($aWeatherData['current']['weatherDesc'], array('Light drizzle'))) {
            $sMess .= ' (rain)';
        }
    }
    $sMess .= PHP_EOL . PHP_EOL;
    $sMess .= _trlt($sUid, '_5 day forecast:') . PHP_EOL;

    $i = 0;
    foreach ($aWeatherData['forecast'] as $sDate => $aWeather) {
        switch ($i) {
            case 0:
                $sMess .= _trlt($sUid, '_Today: ');
                break;
            case 1:
                $sMess .= _trlt($sUid, '_Tomorrow: ');
                break;
            default:
                $sMess .= _trlt($sUid, '_' . date('l', strtotime($sDate))) . ' (' . $sDate . '): ';
                break;
        }

        $sMess .= $aWeather['min_c'] . 'С..' . $aWeather['max_c'] . 'С (' . $aWeather['min_f'] . 'F..' . $aWeather['max_f'] . 'F) ' . _trlt($sUid, '_' . $aWeather['weatherDesc']);
        if ($sImType) {
            if ('Sunny' === $aWeather['weatherDesc']) {
                $sMess .= ' (sun)';
            } elseif (in_array($aWeather['weatherDesc'], array('Light drizzle'))) {
                $sMess .= ' (rain)';
            }
        }
        $sMess .= PHP_EOL;
        $i++;
    }

    return $sMess;
}
