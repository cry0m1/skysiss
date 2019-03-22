<?php

require_once(dirname(dirname(dirname(__FILE__))) . "/Bootstrap_helper.php");

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
//    'force_tls' => true,
//    'resource' => 'assistant',
    'auth_type' => 'PLAIN',
//    'log_level' => JAXL_INFO,
    'strict' => FALSE,
    'priv_dir' => JAXL_CWD . "/.jaxl",
        ));
Zend_Registry::set('$oJabber', $oJabber);

$oJabber->add_cb('on_auth_success', function() {
    $oJabber = Zend_Registry::get('$oJabber');
    $aConfig = Zend_Registry::get('$aConfig');
    global $argv;

    switch ($argv[1]) {//action type
        case 'add_user':
            if ('web' === $argv[3]) {
//                $oJabber->subscribe($argv[2]);
//                $oJabber->subscribed($argv[2]);
//                $oJabber->send_chat_msg($argv[2], _trlt($argv[2], '_Authorization. Please authorize me! Try') . ' ' . $aConfig['logo_name']
//                        . ' ' . _trlt($argv[2], '_now (Check more info at') . ' ' . $aConfig['web_url'] . ")");
            } elseif ('im' === $argv[3]) {
//                $oJabber->subscribe($argv[2]);
//                $oJabber->subscribed($argv[2]);
            }
            $oJabber->subscribe($argv[2]);
            $oJabber->subscribed($argv[2]);
            $oJabber->send_chat_msg($argv[2], _trlt($argv[2], '_Authorization. Please authorize me! Try') . ' ' . $aConfig['logo_name']
                    . ' ' . _trlt($argv[2], '_now (Check more info at') . ' ' . $aConfig['web_url'] . ")");
            break;
        case 'message':
            $sMessage = $argv[3];
//                for ($i = 4; $i < count($argv); $i++) {
//                    $sMessage .= $argv[$i] . ' ';
//                }

            if ($sMessage) {
                $oJabber->send_chat_msg($argv[2], $argv[2] . ', ' . urldecode($sMessage));
            }
            break;
        case 'delete_user':
            $oJabber->unsubscribe($argv[2]);
            $oJabber->unsubscribed($argv[2]);
            break;
    }

    $oJabber->send_end_stream();
});

$oJabber->start();
//posix_kill(getmypid(), 9);
