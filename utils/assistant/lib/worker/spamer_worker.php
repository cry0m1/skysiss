<?php

// OLD PHP PATH=/usr/bin/php

/* Call as 'php spamer_worker.php ASSISTANT_SHORT_NAME' */

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once(dirname(dirname(dirname(__FILE__))) . "/Bootstrap_worker.php");

$oConnection = new AMQPConnection($aConfig['rabbitmq_server_ip'], $aConfig['rabbitmq_server_port'], $aConfig['rabbitmq_server_user'], $aConfig['rabbitmq_server_pass']);
$oChannel = $oConnection->channel();

$oChannel->exchange_declare('skysiss_exchange', 'topic', false, false, false);
//list($sQueueName,, ) = $oChannel->queue_declare("", false, false, false, false);
$oChannel->queue_declare(SPAMER_WORKER_QUEUE, false, true, false, false);
$oChannel->queue_bind(SPAMER_WORKER_QUEUE, 'skysiss_exchange', SPAMER_WORKER_ROUTING);

$fCallback = function($oMsg) {
    $oMsgBody = json_decode($oMsg->body);

    $aConfig = Zend_Registry::get('$aConfig');
    if ($aConfig['debug']) {
        error_log(PHP_EOL . print_r($oMsg->delivery_info['routing_key'] . ':' . $oMsg->body, true), 3, ROOT_PATH . '/log/assistant_' . date('Y-m-d') . '.log');
        error_log("\n===================================================", 3, ROOT_PATH . '/log/assistant_' . date('Y-m-d') . '.log');
        echo PHP_EOL, ' [x] ', $oMsg->delivery_info['routing_key'], ':', $oMsg->body, PHP_EOL;
    }

    //Call helper to send message to client
    cmd_exec("/usr/bin/php " . CURR_PATH . "/lib/helper/_" . $oMsgBody->uid_type . "_helper.php" .
            " message" .
            " " . $oMsgBody->uid .
            " " . urlencode($oMsgBody->message), $stdout, $stderr);

    $oMsg->delivery_info['channel']->basic_ack($oMsg->delivery_info['delivery_tag']);
};

$oChannel->basic_qos(null, 1, null);
$oChannel->basic_consume(SPAMER_WORKER_QUEUE, '', false, false, false, false, $fCallback);
//$oChannel->basic_consume($sQueueName, '', false, true, false, false, $fCallback);

while (count($oChannel->callbacks)) {
    $oChannel->wait();
}

$oChannel->close();
$oConnection->close();

function cmd_exec($cmd, &$stdout, &$stderr) {
    $aConfig = Zend_Registry::get('$aConfig');
    if ($aConfig['debug']) {
        echo PHP_EOL . SPAMER_WORKER_ROUTING, ': ', $cmd, PHP_EOL;
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
    if ($aConfig['debug']) {
        //var_dump($stderr);
    }

    unlink($outfile);
    unlink($errfile);
    return $exit;
}
