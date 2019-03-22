<?php

/* Call as 'php api_worker.php ASSISTANT_SHORT_NAME' */

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once(dirname(dirname(dirname(__FILE__))) . "/Bootstrap_worker.php");

$oConnection = new AMQPConnection($aConfig['rabbitmq_server_ip'], $aConfig['rabbitmq_server_port'], $aConfig['rabbitmq_server_user'], $aConfig['rabbitmq_server_pass']);
$oChannel = $oConnection->channel();

$oChannel->exchange_declare('skysiss_exchange', 'topic', false, false, false);
//list($sQueueName,, ) = $oChannel->queue_declare("", false, false, false, false);
$oChannel->queue_declare(API_WORKER_QUEUE, false, true, false, false);
$oChannel->queue_bind(API_WORKER_QUEUE, 'skysiss_exchange', API_WORKER_ROUTING);

Zend_Registry::set('$oChannel', $oChannel);

$fCallback = function($oMsg) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');
    $oDbAdapter->closeConnection();
    $oDbAdapter->getConnection();

    $oMsgBody = json_decode($oMsg->body);

    $aConfig = Zend_Registry::get('$aConfig');
//    $oMemcache = Zend_Registry::get('$oMemcache');
//    $oMemcache->connect($aConfig['memcache_server_ip'], $aConfig['memcache_server_port']) or die("Could not connect");
    if ($aConfig['debug']) {
        error_log(PHP_EOL . print_r($oMsg->delivery_info['routing_key'] . ':' . $oMsg->body, true), 3, ROOT_PATH . '/log/assistant_' . date('Y-m-d') . '.log');
        error_log("\n===================================================", 3, ROOT_PATH . '/log/assistant_' . date('Y-m-d') . '.log');
        echo PHP_EOL, ': [x] ', $oMsg->delivery_info['routing_key'], ':', $oMsg->body, PHP_EOL;
    }

    switch ($oMsgBody->api_uri) {
        case 'account/create_account':
            $sPass = randomPassword();
            $sRestUrl = $aConfig['api_url'] .
                    $oMsgBody->api_uri .
                    '?api_key=' . $aConfig['api_key'] .
                    '&response=json' .
                    '&platform=' . $oMsgBody->uid_type .
                    '&browser=none' .
                    '&browser_version=none' .
                    '&ip=127.0.0.1' .
                    '&lang_short_name=en' .
                    '&password=' . $sPass .
                    '&confirm_password=' . $sPass .
                    '&uid=' . $oMsgBody->uid;

            foreach ($oMsgBody->params as $sKey => $sValue) {
                $sRestUrl .= '&' . $sKey . '=' . $sValue;
            }

            if ($aConfig['debug']) {
                echo PHP_EOL . API_WORKER_ROUTING, ': ', $sRestUrl, PHP_EOL;
            }

            $aResponse = Zend_Json::decode(file_get_contents($sRestUrl));
            $aResponse = $aResponse['response'];

            if ('success' == (string) $aResponse['status']) {
                if ('TG' == $oMsgBody->uid_type) {
                    iAmConnected($oMsgBody->uid);
                }
            } else {
                if ($aConfig['debug']) {
                    echo PHP_EOL . API_WORKER_ROUTING, ': ', (string) $aResponse['status'], PHP_EOL;
                }
            }
            break;
        case 'account/delete':
            $sRestUrl = $aConfig['api_url'] .
                    $oMsgBody->api_uri .
                    '?api_key=' . $aConfig['api_key'] .
                    '&response=json' .
                    '&internal=true' .
                    '&uid_type=' . $oMsgBody->uid_type .
                    '&requestor=im';

            foreach ($oMsgBody->params as $sKey => $sValue) {
                $sRestUrl .= '&' . $sKey . '=' . $sValue;
            }

            if ($aConfig['debug']) {
                echo PHP_EOL . API_WORKER_ROUTING, ': ', $sRestUrl, PHP_EOL;
            }

            $aResponse = Zend_Json::decode(file_get_contents($sRestUrl));
            $aResponse = $aResponse['response'];

            if ('success' == (string) $aResponse['status']) {
                
            } else {
                if ($aConfig['debug']) {
                    echo PHP_EOL . API_WORKER_ROUTING, ': ', (string) $aResponse['status'], PHP_EOL;
                }
            }
            break;
        case 'account/temporary_link':
            $oUser = getUser($oMsgBody->uid);

            $sSecret = md5($oUser->hash + time());
            addTemporaryLink(array(
                'user_hash' => $oUser->hash,
                'secret' => $sSecret,
            ));

            switch ($oMsgBody->params->link_type) {
                case 'account/login/activate':
                    sendMessage($oMsgBody->uid, _trlt($oMsgBody->uid, '_Here is your link to activate account: ') .
                            $aConfig['web_url'] . '/' .
                            $oMsgBody->params->link_type . '?uh=' . $oUser->hash .
                            '&ph=' . $oUser->password .
                            '&sh=' . $sSecret .
                            '&ut=' . $oMsgBody->uid_type);
                    break;
                case 'account/login/imlogin':
                    sendMessage($oMsgBody->uid, _trlt($oMsgBody->uid, '_Here is your link to login: ') .
                            $aConfig['web_url'] . '/' .
                            $oMsgBody->params->link_type . '?uh=' . $oUser->hash .
                            '&ph=' . $oUser->password .
                            '&sh=' . $sSecret .
                            '&ut=' . $oMsgBody->uid_type);
                    break;
            }
            break;
        case 'service/connected':
            $oUser = recacheUser($oMsgBody->uid);
            if ($oUser) {
                $aData = array();
                $aData['status'] = 'AC';
                updateUser($oUser->rec_id, $aData);

                if ('IA' === $oUser->status) {
                    generateLink($oMsgBody->uid, 'account/login/activate');
                }
                sendMessage($oMsgBody->uid, _trlt($oMsgBody->uid, '_type #help for any help'));
            } else {
                sendMessage($oMsgBody->uid, _trlt($oMsgBody->uid, '_Type #registerme'));
            }
            break;
        case 'helpMe':
            helpMe($oMsgBody->uid, $oMsgBody->param);
            break;
        case 'getWeather':
            getWeather($oMsgBody->uid, $oMsgBody->param);
            break;
        case 'towerHanoi':
            towerHanoi($oMsgBody->uid, $oMsgBody->param);
            break;
        case 'magic8':
            magic8($oMsgBody->uid, $oMsgBody->param);
            break;
        case 'calcExpression':
            calcExpression($oMsgBody->uid, $oMsgBody->param);
            break;
        case 'getCurrencyRates':
            getCurrencyRates($oMsgBody->uid, $oMsgBody->param);
            break;
        case 'changeLang':
            changeLang($oMsgBody->uid, $oMsgBody->param);
            break;
        case 'addLog':
            addLog($oMsgBody->uid, $oMsgBody->param);
            break;
        case 'addLogOut':
            addLogOut($oMsgBody->uid, $oMsgBody->param);
            break;
        case 'getClock':
            getClock($oMsgBody->uid, $oMsgBody->param);
            break;
        case 'getHoro':
            getHoro($oMsgBody->uid, $oMsgBody->param);
            break;
        case 'hashMaker':
            hashMaker($oMsgBody->uid, $oMsgBody->param);
            break;
        case 'habrParse':
            habrParse($oMsgBody->uid);
            break;
        case 'statParse':
            statParse($oMsgBody->uid);
            break;
        case 'newsParse':
            newsParse($oMsgBody->uid);
            break;
        case 'ssh':
            ssh($oMsgBody->uid, $oMsgBody->param);
            break;
        case 'getFluent':
            getFluent($oMsgBody->uid, $oMsgBody->param);
            break;
    }

    $oMsg->delivery_info['channel']->basic_ack($oMsg->delivery_info['delivery_tag']);
    //$oMemcache->close();
};

$oChannel->basic_qos(null, 1, null);
$oChannel->basic_consume(API_WORKER_QUEUE, '', false, false, false, false, $fCallback);
//$oChannel->basic_consume($sQueueName, '', false, true, false, false, $fCallback);

while (count($oChannel->callbacks)) {
    $oChannel->wait();
}

$oChannel->close();
$oConnection->close();
