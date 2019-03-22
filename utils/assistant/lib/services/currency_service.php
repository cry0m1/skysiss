<?php

use PhpAmqpLib\Message\AMQPMessage;

/* Weather service */

function getCurrencyMQ($sUid, $sParam) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'getCurrencyRates',
                'uid' => $sUid, //uid name
                'param' => $sParam,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

/* Add convercion rates */
/* Add currency convertor depending on source */

function getCurrencyRates($sUid, $sSource) {
    if ($sSource) {
        /* Retrieve data from source */
        switch ($sSource) {
            case 1:
                getConversion($sUid);
                break;
            case 2:
                getIntl($sUid);
                break;
            case 3:
                getYahoo($sUid);
                break;
            case 4:
                getECB($sUid);
                break;
            case 5:
                getOCB($sUid);
                break;
            case 6:
                getCBR($sUid);
                break;
            case 7:
                getNBU($sUid);
                break;
            case 8:
                getNBB($sUid);
                break;
            case 9:
                getBChina($sUid);
                break;
            case 10:
                getBCanada($sUid);
                break;
            case 11:
                getBitcoin($sUid);
                break;
            default:
                sendMessage($sUid, _trlt($sUid, '_Not supported expression'));
                break;
        }
    }
}

function getConversion($sUid) {
    if ($aData = memcacheGet('currencyconversion')) {
        sendMessage($sUid, generateRateMessage($aData));
    } else {
        /* USD -> EUR */
        $sJsonString = file_get_contents("http://rate-exchange.appspot.com/currency?from=USD&to=EUR");
        $mParsedJson = json_decode($sJsonString);
        $aData[$mParsedJson->from . ' -> ' . $mParsedJson->to] = $mParsedJson->rate;

        /* EUR -> USD */
        $sJsonString = file_get_contents("http://rate-exchange.appspot.com/currency?from=EUR&to=USD");
        $mParsedJson = json_decode($sJsonString);
        $aData[$mParsedJson->from . ' -> ' . $mParsedJson->to] = $mParsedJson->rate;

        sendMessage($sUid, generateRateMessage($aData));

        /* Save to cache */
        memcacheSet('currencyconversion', $aData);
    }
}

function getIntl($sUid) {
    /* Account
     * yulik_86@mail.ru / P1a2s3s4w5ord
     */
    if ($aData = memcacheGet('currencyintl')) {
        sendMessage($sUid, generateRateMessage($aData));
    } else {
        /* USD */
        $sJsonString = file_get_contents("http://openexchangerates.org/api/latest.json?app_id=c544cd2e4fb34e608c8679584c9753f1");
        $mParsedJson = json_decode($sJsonString);
        foreach ($mParsedJson->rates as $sKey => $iValue) {
            $aData[$mParsedJson->base . ' -> ' . $sKey] = $iValue;
        }

        sendMessage($sUid, generateRateMessage($aData));

        /* Save to cache */
        memcacheSet('currencyintl', $aData);
    }
}

function getYahoo($sUid) {
    if ($aData = memcacheGet('currencyyahoo')) {
        sendMessage($sUid, generateRateMessage($aData));
    } else {
        /* USD */
        $sXmlString = simplexml_load_string(file_get_contents("http://finance.yahoo.com/webservice/v1/symbols/allcurrencies/quote"));
        $mParsedXml = json_decode(json_encode($sXmlString));
        foreach ($mParsedXml->resources->resource as $iKey => $oValue) {
            $aData[$oValue->field[0]] = $oValue->field[1];
        }

        sendMessage($sUid, generateRateMessage($aData));

        /* Save to cache */
        memcacheSet('currencyyahoo', $aData);
    }
}

function getOCB($sUid) {
    if ($aData = memcacheGet('currencyocb')) {
        sendMessage($sUid, generateRateMessage($aData));
    } else {
        $hConn = curl_init();
        $aOpts = array(
            CURLOPT_URL => 'http://www.ocbc.com/rates/daily_price_fx.html',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLINFO_HEADER_OUT => true,
            CURLOPT_FOLLOWLOCATION => true,
        );
        curl_setopt_array($hConn, $aOpts);
        $sResponse = curl_exec($hConn);

        $iSplitter = 25;
        preg_match_all("/>([\d\.]+|[\d+ \.A-Za-z]+)</", $sResponse, $aMatches);
        foreach ($aMatches[1] as $iKey => $sValue) {
            if ($iSplitter <= $iKey && $iKey <= 125) {
                $aData[$aMatches[1][$iKey + 1] . ' (' . $aMatches[1][$iKey + 2] . ')'] = $aMatches[1][$iKey + 3] . ' | ' . $aMatches[1][$iKey + 4] . ' | ' . $aMatches[1][$iKey + 5];
                $iSplitter += 6;
            }
        }
        $aData['---------'] = '---------';
        $iSplitter = 125;
        foreach ($aMatches[1] as $iKey => $sValue) {
            if ($iSplitter <= $iKey && $iKey > 130 && $iKey < 226) {
                $aData[$aMatches[1][$iKey + 1] . ' (' . $aMatches[1][$iKey + 2] . ')'] = $aMatches[1][$iKey + 3] . ' | ' . $aMatches[1][$iKey + 4] . ' | ' . $aMatches[1][$iKey + 5];
                $iSplitter += 6;
            }
        }

        sendMessage($sUid, generateRateMessage($aData));

        /* Save to cache */
        memcacheSet('currencyocb', $aData);
    }
}

function getECB($sUid) {
    if ($aData = memcacheGet('currencyecb')) {
        sendMessage($sUid, generateRateMessage($aData));
    } else {
        /* USD */
        $sXmlString = simplexml_load_string(file_get_contents("http://www.ecb.int/stats/eurofxref/eurofxref-daily.xml"));
        $mParsedXml = json_decode(json_encode($sXmlString));

        foreach ($mParsedXml->Cube->Cube->Cube as $oValue) {
            $aData['EUR -> ' . $oValue->{'@attributes'}->currency] = $oValue->{'@attributes'}->rate;
        }

        sendMessage($sUid, generateRateMessage($aData));

        /* Save to cache */
        memcacheSet('currencyecb', $aData);
    }
}

function getCBR($sUid) {
    if ($aData = memcacheGet('currencycbr')) {
        sendMessage($sUid, generateRateMessage($aData));
    } else {
        $oCurl = curl_init();
        curl_setopt($oCurl, CURLOPT_URL, "http://www.cbr.ru/scripts/XML_daily.asp");
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($oCurl, CURLOPT_FOLLOWLOCATION, true);
        $sContent = curl_exec($oCurl);
        curl_close($oCurl);

        preg_match_all("#<ValCurs Date=\"(.*)\" name=\"Foreign Currency Market\">#sU", $sContent, $_cur_date);
        preg_match_all("#<Valute ID=\"R(\d)+\">.*<CharCode>(.*)</CharCode>.*<Value>(.*)</Value>.*</Valute>#sU", $sContent, $_currency);

        $aData = array();
        for ($index = 0; $index < count($_currency[1]); $index++) {
            $aData['RUR -> ' . $_currency[2][$index]] = $_currency[3][$index];
        }

        sendMessage($sUid, generateRateMessage($aData));

        /* Save to cache */
        memcacheSet('currencycbr', $aData);
    }
}

function getNBU($sUid) {
    if ($aData = memcacheGet('currencynbu')) {
        sendMessage($sUid, generateRateMessage($aData));
    } else {
        $sHtmlString = file_get_contents("http://sravnibank.com.ua/kurs-nbu/");

        preg_match_all("#\"currency_symbol\">(.*)</td>#sU", $sHtmlString, $_currency);
        preg_match_all("#\"currency_rate\">.*(\d+,\d+)<img.*</td>#sU", $sHtmlString, $_currency_rate);

        $aData = array();
        for ($index = 0; $index < count($_currency[1]); $index++) {
            $aData['UAH -> ' . $_currency[1][$index]] = $_currency_rate[1][$index];
        }

        sendMessage($sUid, generateRateMessage($aData));

        /* Save to cache */
        memcacheSet('currencynbu', $aData);
    }
}

function getNBB($sUid) {
    if ($aData = memcacheGet('currencynbb')) {
        sendMessage($sUid, generateRateMessage($aData));
    } else {
        $sXmlString = file_get_contents("http://www.nbrb.by/Services/XmlExRates.aspx");

        preg_match_all("#<DailyExRates Date=\"(.*)\">#sU", $sXmlString, $_cur_date);
        preg_match_all("#Currency Id=\"(\d)+\">.*<CharCode>(.*)</CharCode>.*<Rate>(.*)</Rate>.*</Currency>#sU", $sXmlString, $_currency);

        $aData = array();
        for ($index = 0; $index < count($_currency[1]); $index++) {
            $aData['BYR -> ' . $_currency[2][$index]] = $_currency[3][$index];
        }

        sendMessage($sUid, generateRateMessage($aData));

        /* Save to cache */
        memcacheSet('currencynbb', $aData);
    }
}

function getBChina($sUid) {
    if ($aData = memcacheGet('currencybchina')) {
        sendMessage($sUid, generateRateMessage($aData));
    } else {
        $sHtmlString = file_get_contents("http://www.bankofchina.com/finadata/brhdatas/sg_exchangeR.htm");

        preg_match_all("/<td>([A-Z])+<\/td>\s{0,10}<td>(\d+\.\d+)<\/td>/", $sHtmlString, $_currency);

        $aData = array();
        for ($index = 0; $index < count($_currency[0]); $index++) {
            $aPair = explode(' ', strip_tags($_currency[0][$index]));
            $aData['CNY -> ' . $aPair[0]] = $aPair[4];
        }

        sendMessage($sUid, generateRateMessage($aData));

        /* Save to cache */
        memcacheSet('currencybchina', $aData);
    }
}

function getBCanada($sUid) {
    if ($aData = memcacheGet('currencybcanada')) {
        sendMessage($sUid, generateRateMessage($aData));
    } else {
        $sHtmlString = file_get_contents("http://www.bankofcanada.ca/stats/noon_five_day");
        preg_match_all("/<td>([A-Za-z\.\s\/])+<\/td>(<td>\d+\.\d+<\/td>){5}<\/tr>/", $sHtmlString, $_currency, PREG_SET_ORDER);

        $aData = array();
        for ($index = 0; $index < count($_currency); $index++) {
            $aData['CAD -> ' . strip_tags(substr($_currency[$index][0], 0, strpos($_currency[$index][0], '</td>')))] = strip_tags($_currency[$index][2]);
        }

        sendMessage($sUid, generateRateMessage($aData));

        /* Save to cache */
        memcacheSet('currencybcanada', $aData);
    }
}

function getBitcoin($sUid) {
    if ($aData = memcacheGet('currencybtc')) {
        sendMessage($sUid, generateRateMessage($aData));
    } else {
        /* Weighted prices */
        $sJsonString = file_get_contents("http://api.bitcoincharts.com/v1/weighted_prices.json");
        $mParsedJson = json_decode($sJsonString);

        foreach ($mParsedJson as $sKey => $oPeriodValue) {
            if ('timestamp' !== $sKey) {
                $aData['BTC -> ' . $sKey] = '24h: ' . $oPeriodValue->{'24h'} . PHP_EOL .
                        '7d: ' . $oPeriodValue->{'7d'} . PHP_EOL .
                        '30d: ' . $oPeriodValue->{'30d'};
            }
        }

        /* Market */
        $sJsonString = file_get_contents("http://api.bitcoincharts.com/v1/markets.json");
        $mParsedJson = json_decode($sJsonString);

        $aData[''] = PHP_EOL;
        $aData['MARKET'] = '';
        foreach ($mParsedJson as $iKey => $oTradeValue) {
            $aData[$oTradeValue->currency . ' [' . $oTradeValue->currency . ']'] = 'high: ' . ($oTradeValue->high ? $oTradeValue->high : '-') . PHP_EOL .
                    'low: ' . ($oTradeValue->low ? $oTradeValue->low : '-') . PHP_EOL .
                    'average: ' . ($oTradeValue->avg ? $oTradeValue->avg : '-') . PHP_EOL .
                    'bid: ' . ($oTradeValue->bid ? $oTradeValue->bid : '-') . PHP_EOL .
                    'ask: ' . ($oTradeValue->ask ? $oTradeValue->ask : '-') . PHP_EOL .
                    'close: ' . ($oTradeValue->close ? $oTradeValue->close : '-') . PHP_EOL .
                    'latest_trade: ' . ($oTradeValue->latest_trade ? $oTradeValue->latest_trade : '-') . PHP_EOL .
                    'volume: ' . ($oTradeValue->volume ? $oTradeValue->volume : '-') . PHP_EOL .
                    'currency_volume: ' . ($oTradeValue->currency_volume ? $oTradeValue->currency_volume : '-');
        }

        sendMessage($sUid, generateRateMessage($aData));

        /* Save to cache */
        memcacheSet('currencybtc', $aData);
    }
}

function generateRateMessage($aData) {
    $sMess = PHP_EOL;
    foreach ($aData as $sKey => $sValue) {
        $sMess .= '[' . $sKey . ']: ' . $sValue . PHP_EOL;
    }

    return $sMess;
}
