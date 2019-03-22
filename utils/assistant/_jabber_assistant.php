<?php

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

require_once(dirname(__FILE__) . "/Bootstrap_assistant.php");

/* JABBER
 *
 *
 *
 *
 */

$oJabber = new JAXL(array(
    'jid' => '_skysiss@im.skysiss.com',
    'pass' => $aConfig['jabber_server_password'],
    'host' => 'im.skysiss.com',
    'port' => 5222,
    //'force_tls' => true,
    'resource' => 'assistant',
    'auth_type' => 'PLAIN',
    'log_level' => ($aConfig['debug'] ? JAXLLogger::INFO : JAXLLogger::ERROR),
    'strict' => FALSE,
//    'priv_dir' => JAXL_CWD . "/.jaxl",
        ));

$oJabber->require_xep(array(
    '0199' // XMPP Ping
));

Zend_Registry::set('$oJabber', $oJabber);

//Add rabbitMQ queue
$oConnection = new AMQPConnection($aConfig['rabbitmq_server_ip'], $aConfig['rabbitmq_server_port'], $aConfig['rabbitmq_server_user'], $aConfig['rabbitmq_server_pass']);
$oChannel = $oConnection->channel();
$oChannel->exchange_declare('skysiss_exchange', 'topic', false, false, false);
Zend_Registry::set('$oChannel', $oChannel);

$oJabber->add_cb('on_auth_success', function() {
    $oJabber = Zend_Registry::get('$oJabber');
    $aConfig = Zend_Registry::get('$aConfig');

    if ($aConfig['debug']) {
        JAXLLogger::info("got on_auth_success cb, jid " . $oJabber->full_jid->to_string());
    }

//    $oJabber->get_roster();
//    $oJabber->get_vcard();

    $oJabber->set_status("Type /help for any help | by https://skysiss.com", "chat", 10);
});

$oJabber->add_cb('on_auth_failure', function($sReason) {
    $oJabber = Zend_Registry::get('$oJabber');
    $aConfig = Zend_Registry::get('$aConfig');

    $oJabber->send_end_stream();
    if ($aConfig['debug']) {
        JAXLLogger::info("got on_auth_failure cb with reason $sReason");
    }
});

$oJabber->add_cb('on_chat_message', function($oStanza) {
    $oJabber = Zend_Registry::get('$oJabber');
    $aConfig = Zend_Registry::get('$aConfig');
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');
    $oDbAdapter->getConnection();

    $sMessage = trim(strtolower($oStanza->body));

    if (!$sMessage) {
        $oStanza->to = $oStanza->from;
        $oStanza->from = $oJabber->full_jid->to_string();
        $oJabber->send($oStanza);
    } else {
        preg_match('/^([0-9a-zA-Z].*?@([0-9a-zA-Z].*\.\w{2,4}))/', $oStanza->from, $aMatches);
        $sUid = $aMatches[1];

        if ($aConfig['debug']) {
            JAXLLogger::info($oStanza->from . ' send: ' . $sMessage);
        }

        receiveMessage($sUid, $sMessage, 'JB');
    }

    $oDbAdapter->closeConnection();
});

$oJabber->add_cb('on_presence_stanza', function($oStanza) {
    restartTimer('JB');

    $oJabber = Zend_Registry::get('$oJabber');
    $aConfig = Zend_Registry::get('$aConfig');
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');
    $oDbAdapter->getConnection();

    /* Somebody connects to jabber server */
    preg_match('/^([0-9a-zA-Z].*?@([0-9a-zA-Z].*\.\w{2,4}))/', $oStanza->from, $aMatches);
    if (isset($aMatches[1])) {
        $sUid = $aMatches[1];

        $type = ($oStanza->type ? $oStanza->type : "available");
        $show = ($oStanza->show ? $oStanza->show : "???");
        if ($aConfig['debug']) {
            JAXLLogger::info($oStanza->from . " is now " . $type . " ($show)");
        }

        if ($type == "available") {

        } elseif (in_array($type, array('unavailable', 'dnd', 'xa', 'away'))) {

        } elseif ($type == 'subscribe') {
            /* Somebody ask authorization */
            $oUser = getUser($sUid);
            if (!$oUser || 'NA' === $oUser->status) {
                registerUser($sUid);
            } else {
                $oJabber->subscribe($sUid);
                $oJabber->subscribed($sUid);
            }
        } elseif ($type == 'subscribed') {
            /* Somebody approves authorization */
            if (getUser($sUid)) {
                iAmConnected($sUid);
            } else {
                registerUser($sUid);
            }
        } elseif ($type == 'unsubscribe' || $type == 'unsubscribed') {
            if (getUser($sUid)) {
                $oJabber->unsubscribe($sUid);
                $oJabber->unsubscribed($sUid);
                deleteMe($sUid);
            }
        }
    }

    $oDbAdapter->closeConnection();
});

$oJabber->add_cb('on_disconnect', function() {
    $aConfig = Zend_Registry::get('$aConfig');

    if ($aConfig['debug']) {
        JAXLLogger::info("got on_disconnect cb");
    }
});


if ($aConfig['debug']) {
    $oJabber->start(array(
            //'--with-debug-shell' => true,
            //'--with-unix-sock' => true
    ));
} else {
    $oJabber->start();
}
