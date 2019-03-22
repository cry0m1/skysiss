<?php

// OLD PHP PATH=/usr/bin/php

/* Call as 'php user_operations_worker.php ASSISTANT_SHORT_NAME' */

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once(dirname(dirname(dirname(__FILE__))) . "/Bootstrap_worker.php");

$oConnection = new AMQPConnection($aConfig['rabbitmq_server_ip'], $aConfig['rabbitmq_server_port'], $aConfig['rabbitmq_server_user'], $aConfig['rabbitmq_server_pass']);
$oChannel = $oConnection->channel();

$oChannel->exchange_declare('skysiss_exchange', 'topic', false, false, false);
//list($sQueueName,, ) = $oChannel->queue_declare("", false, false, true, false);
$oChannel->queue_declare(USER_OPERATIONS_WORKER_QUEUE, false, true, false, false);
$oChannel->queue_bind(USER_OPERATIONS_WORKER_QUEUE, 'skysiss_exchange', USER_OPERATIONS_WORKER_ROUTING);

Zend_Registry::set('$oChannel', $oChannel);

$fCallback = function($oMsg) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');
    $oDbAdapter->closeConnection();
    $oDbAdapter->getConnection();

    $oMsgBody = json_decode($oMsg->body);

    $aConfig = Zend_Registry::get('$aConfig');
    if ($aConfig['debug']) {
        error_log(PHP_EOL . print_r($oMsg->delivery_info['routing_key'] . ':' . $oMsg->body, true), 3, ROOT_PATH . '/log/assistant_' . date('Y-m-d') . '.log');
        error_log("\n===================================================", 3, ROOT_PATH . '/log/assistant_' . date('Y-m-d') . '.log');
        echo PHP_EOL, ' [x] ', $oMsg->delivery_info['routing_key'], ':', $oMsg->body, PHP_EOL;
    }

    switch ($oMsgBody->action_type) {
        case 'add_user':
            $oUser = recacheUser($oMsgBody->uid);

            //Call helper to add im client
            cmd_exec("/usr/bin/php " . CURR_PATH . "/lib/helper/_" . $oMsgBody->uid_type . "_helper.php" .
                    " " . $oMsgBody->action_type .
                    " " . $oMsgBody->uid .
                    " " . $oMsgBody->requestor, $stdout, $stderr);
            break;
        case 'delete_user':
            sendMessage($oMsgBody->uid, _trlt($oMsgBody->uid, '_Bye :)'));
            sleep(2);

            //Call helper to delete im client
            cmd_exec("/usr/bin/php " . CURR_PATH . "/lib/helper/_" . $oMsgBody->uid_type . "_helper.php" .
                    " " . $oMsgBody->action_type .
                    " " . $oMsgBody->uid .
                    " " . $oMsgBody->requestor, $stdout, $stderr);
            break;
    }

    $oMsg->delivery_info['channel']->basic_ack($oMsg->delivery_info['delivery_tag']);
};

$oChannel->basic_qos(null, 1, null);
$oChannel->basic_consume(USER_OPERATIONS_WORKER_QUEUE, '', false, false, false, false, $fCallback);
//$oChannel->basic_consume($sQueueName, '', false, true, false, false, $fCallback);

while (count($oChannel->callbacks)) {
    $oChannel->wait();
}

$oChannel->close();
$oConnection->close();

function cmd_exec($cmd, &$stdout, &$stderr) {
    $aConfig = Zend_Registry::get('$aConfig');
    if ($aConfig['debug']) {
        echo PHP_EOL . USER_OPERATIONS_WORKER_ROUTING, ': ', $cmd, PHP_EOL;
    }

    $outfile = tempnam(".", "cmd");
    $errfile = tempnam(".", "cmd");
    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("file", $outfile, "w"),
        2 => array("file", $errfile, "w")
    );
    $proc = proc_open($cmd, $descriptorspec, $pipes);

    if (!is_resource($proc))
        return 255;

    fclose($pipes[0]);    //Don't really want to give any input

    $exit = proc_close($proc);
    $stdout = file($outfile);
    $stderr = file($errfile);

    $aConfig = Zend_Registry::get('$aConfig');

    unlink($outfile);
    unlink($errfile);
    return $exit;
}

//start worker. take from config service_hash and listen appropriate queue
//queue param: type=add|delete uid_type=skype|jabber client_name
// call helper _jabber.php -add -jabber_name -service_hash
// call helper _jabber.php -add(delete) -jabber_name -service_hash
// call helper _skype.php -add -jabber_name -service_hash
// call helper _skype.php -add(delete) -skype_name -service_hash