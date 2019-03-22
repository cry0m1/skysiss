<?php

require_once(dirname(dirname(dirname(__FILE__))) . "/Bootstrap_helper.php");

/* SKYPE
 *
 *
 *
 *
 */

require_once("skype/Skype.php");
$oDbus = new Dbus(Dbus::BUS_SESSION, true);
$oSkype = $oDbus->createProxy('com.Skype.API', '/com/Skype', 'com.Skype.API'); //Подключаемся к скайпу
$oSkype->Invoke('NAME PHP-ASSISTANT');
$oSkype->Invoke('PROTOCOL 8');
Zend_Registry::set('$oSkype', $oSkype);

class phpSkype {

    public static function notify($sNotify) {
        $oSkype = Zend_Registry::get('$oSkype');
        $oDbAdapter = Zend_Registry::get('$oDbAdapter');
        $aConfig = Zend_Registry::get('$aConfig');
        $argv = Zend_Registry::get('$argv');
        $oMemcache = Zend_Registry::get('$oMemcache');

        if ($aConfig['debug']) {
            var_dump($sNotify);
        }

        switch ($argv[1]) {//action type
            case 'add_user':
                if ('web' === $argv[3]) {
//                    $oSkype->Invoke("SET USER " . $argv[2] . " BUDDYSTATUS 2 " . _trlt($argv[2], '_Authorization. Please authorize me! Try') . ' ' . $aConfig['logo_name']
//                            . ' ' . _trlt($argv[2], '_now (Check more info at') . ' ' . $aConfig['web_url'] . ")");
                } elseif ('im' === $argv[3]) {
                    //$oSkype->Invoke("SET USER " . $argv[2] . " BUDDYSTATUS 2");
                }

                $oSkype->Invoke("SET USER " . $argv[2] . " BUDDYSTATUS 2 " . _trlt($argv[2], '_Authorization. Please authorize me! Try') . ' ' . $aConfig['logo_name']
                        . ' ' . _trlt($argv[2], '_now (Check more info at') . ' ' . $aConfig['web_url'] . ")");
                break;
            case 'message':
                $oUser = getUser($argv[2]);
                $sMessage = $argv[3];

                if ($oUser) {
                    $sChatId = getXmessAssitant($argv[2]);
                    $oSkype->Invoke("CHATMESSAGE $sChatId $argv[2], " . urldecode($sMessage));
                } else {//for xmess
                    if ($sChatId = getXmessAssitant($argv[2])) {
                        $oSkype->Invoke("CHATMESSAGE $sChatId $argv[2], " . urldecode($sMessage));
                        $oSkype->Invoke("SET USER " . $argv[2] . " BUDDYSTATUS 2 " . urldecode($sMessage));
                    }
                }
                break;
            case 'delete_user':
                $oSkype->Invoke("SET USER $argv[2] BUDDYSTATUS 1");
                break;
        }

        posix_kill(getmypid(), 9);
    }

}

$oDbus->registerObject('/com/Skype/Client', 'com.Skype.API.Client', 'phpSkype'); //Регистрируем просмотр уведомлений скайпа
while (true) {
    $s = $oDbus->waitLoop(1);
}