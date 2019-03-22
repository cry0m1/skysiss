<?php

use PhpAmqpLib\Message\AMQPMessage;

set_include_path(implode(PATH_SEPARATOR, array(
    ROOT_PATH . '/library/phpseclib/',
    get_include_path(),
)));

include(ROOT_PATH . '/library/phpseclib/Net/SSH2.php');

/* SSH service */

function getSshMQ($sUid, $sParam) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'ssh',
                'uid' => $sUid, //uid name
                'param' => $sParam,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function ssh($sUid, $sExpression) {
    $oUser = getUser($sUid);

    // username|||password|||host_or_ip:port

    if ($aSshConnection = memcacheGet('ssh' . $oUser->rec_id)) {
        if (preg_match('/^([a-zA-Z0-9\_]*)\|\|\|(.)*\|\|\|([a-zA-Z0-9\.]*)(\:(\d)+)?$/', $sExpression)) {
            connServer($sUid, $sExpression);
        } else {
            executeSsh($sUid, $aSshConnection, $sExpression);
        }
    } else {
        if (preg_match('/^([a-zA-Z0-9\_]*)\|\|\|(.)*\|\|\|([a-zA-Z0-9\.]*)(\:(\d)+)?$/', $sExpression)) {
            connServer($sUid, $sExpression);
        } else {
            sendMessage($sUid, _trlt($sUid, '_Not supported expression'));
        }
    }
}

function executeSsh($sUid, $aSshConnection, $sExpression) {
    $oSsh = new Net_SSH2($aSshConnection[2]);
    if ($oSsh->login($aSshConnection[0], $aSshConnection[1])) {
        $sMess = trim($oSsh->exec($sExpression));
        if ($sMess) {
            sendMessage($sUid, PHP_EOL . $sMess);
        } else {
            sendMessage($sUid, PHP_EOL . _trlt($sUid, '_Executed'));
        }
    } else {
        sendMessage($sUid, _trlt($sUid, '_Login Failed'));
    }
}

function connServer($sUid, $sExpression) {
    $oUser = getUser($sUid);
    $aSshConnection = explode('|||', $sExpression);

    if (preg_match('/\:/', $aSshConnection[2])) {
        $oSsh = new Net_SSH2(explode(':', $aSshConnection[2])[0], explode(':', $aSshConnection[2])[1]);
    } else {
        $oSsh = new Net_SSH2($aSshConnection[2]);
    }

    if ($oSsh->login($aSshConnection[0], $aSshConnection[1])) {
        memcacheSet('ssh' . $oUser->rec_id, $aSshConnection, 3600); //1h
        sendMessage($sUid, _trlt($sUid, '_Connected, provide ssh commands please:'));
    } else {
        sendMessage($sUid, _trlt($sUid, '_Login Failed'));
    }
}

//<?php
//
//date_default_timezone_set('UTC');
//
//set_include_path(implode(PATH_SEPARATOR, array(
//    'phpseclib/',
//    get_include_path(),
//)));
//
//include('phpseclib/Net/SSH2.php');
//include('phpseclib/File/ANSI.php');
//
//$ssh = new Net_SSH2('skysiss.com');
//if (!$ssh->login('skysiss', '%PASSWORD%')) {
//    exit('Login Failed');
//}
//
//
//echo $ssh->read('skysiss@');
//$ssh->write("ls\n"); // note the "\n"
//echo $ssh->read('skysiss@');
//$ssh->write("cd /\n"); // note the "\n"
//echo $ssh->read('skysiss@');
//$ssh->write("ls\n"); // note the "\n"
//echo $ssh->read('skysiss@');
