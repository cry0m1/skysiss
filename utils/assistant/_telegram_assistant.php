<?php

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once(dirname(__FILE__) . "/Bootstrap_assistant.php");

use Telegram\Bot\Api as TgBot;

/* TELEGRAM
 *
 *
 *
 *
 */

$oTelegram = new TgBot('134029499:AAEHLXVbStMa1PE7tGDeDtgoFc8dzZqoZJM');
Zend_Registry::set('$oTelegram', $oTelegram);

//Add rabbitMQ queue
$oConnection = new AMQPConnection($aConfig['rabbitmq_server_ip'], $aConfig['rabbitmq_server_port'], $aConfig['rabbitmq_server_user'], $aConfig['rabbitmq_server_pass']);
$oChannel = $oConnection->channel();
$oChannel->exchange_declare('skysiss_exchange', 'topic', false, false, false);
Zend_Registry::set('$oChannel', $oChannel);

$fnHandler = function($oMessages) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');
    $oDbAdapter->getConnection();

    foreach ($oMessages as $oMessage) {
        if ($oMessage['message']) {
            $aMessage = $oMessage['message']->all();
            $sOldUid = '@' . $aMessage['from']->all()['username'];
            $sUid = '@' . $aMessage['from']->all()['id'];
            getUserTelegramChatId($sUid, $aMessage['chat']->all()['id'], $sOldUid);

            $sMessage = $aMessage['text'];
            receiveMessage($sUid, explode('@skysisstant_bot', $sMessage)[0], 'TG');
        }
    }

    $oDbAdapter->closeConnection();
    return false;
};

$iUpdateId = NULL;
while (true) {
//    try {
    if ($iUpdateId) {
        $fnHandler(
                $oTelegram->getUpdates(
                        [
                            'offset' => $iUpdateId
                        ]
                )
        );
        $iUpdateId = NULL;
    } else {
        $oUpdates = $oTelegram->getUpdates();
        if (0 === count($oUpdates)) {

        } else {
            $iUpdateId = end($oUpdates)->all()['update_id'] + 1;
            $fnHandler($oUpdates);
        }
    }
//    } catch (\Exception $e) {
//        var_dump($e);
//        sleep($aConfig['timeout_const'] * 5);
//    }

    sleep($aConfig['timeout_const']);

    restartTimer('TG');
}
