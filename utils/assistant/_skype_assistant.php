<?php

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

//try {
require_once(dirname(__FILE__) . "/Bootstrap_assistant.php");

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
$oSkype->Invoke('SET WINDOWSTATE HIDDEN');
$oSkype->Invoke('SET SILENT_MODE ON');
$oSkype->Invoke('SET AVATAR 1 ' . CURR_PATH . "/lib/logo_600x600.png");

//Add rabbitMQ queue
$oConnection = new AMQPConnection($aConfig['rabbitmq_server_ip'], $aConfig['rabbitmq_server_port'], $aConfig['rabbitmq_server_user'], $aConfig['rabbitmq_server_pass']);
$oChannel = $oConnection->channel();
$oChannel->exchange_declare('skysiss_exchange', 'topic', false, false, false);
Zend_Registry::set('$oChannel', $oChannel);

class phpSkype {

    private static $iLastId;

    public static function notify($sNotify) {
        $oSkype = Zend_Registry::get('$oSkype');
        $oDbAdapter = Zend_Registry::get('$oDbAdapter');
        $aConfig = Zend_Registry::get('$aConfig');
        $oDbAdapter->getConnection();

        if ($aConfig['debug']) {
            var_dump($sNotify);
        }

        /* OUR STATUSES */
        if (in_array($sNotify, array('USERSTATUS OFFLINE', 'USERSTATUS DND', 'USERSTATUS AWAY', 'USERSTATUS NA'))) {
            $oSkype->Invoke('SET USERSTATUS ONLINE');
        }

        /* If user adds himself by adding server skype */
        if (preg_match('/^USER ([a-zA-Z][a-zA-Z0-9.\-_\:]{5,31}) RECEIVEDAUTHREQUEST (.)+/', $sNotify, $aMatches)) {
            $oUser = getUser($aMatches[1]);
            if (!$oUser || 'NA' === $oUser->status) {
                registerUser($aMatches[1]);
            }
        }

        /* If user adds himself by adding server skype */
        if (0 == rand(0, $aConfig['timeout_const'] * 2)) {
            $sSelfAuthorizedUsers = $oSkype->Invoke("SEARCH USERSWAITINGMYAUTHORIZATION");
            $aSelfAuthorizedUsers = explode(', ', $sSelfAuthorizedUsers);

            if (strlen($sSelfAuthorizedUsers) > 6) {
                /* There are users */
                $aSelfAuthorizedUsers[0] = str_replace('USERS ', '', $aSelfAuthorizedUsers[0]);
                foreach ($aSelfAuthorizedUsers as $sSelfAuthorizedUser) {
                    $sSelfAuthorizedUser = str_replace(array(',', ' '), '', $sSelfAuthorizedUser);
                    /* If user was already in DB */
                    $oUser = getUser($sSelfAuthorizedUser);
                    if (!$oUser || 'NA' === $oUser->status) {
                        registerUser($sSelfAuthorizedUser);
                    }
                }
            }
        }

        /* Message serving 2 */
        //if (preg_match('/RECEIVED$|SENT/Uis', $sNotify)) {
//        if (preg_match('/RECEIVED$/Uis', $sNotify)) {
//            $aReceivedMsg = explode(' ', $sNotify);
//            $sAuthRaw = $oSkype->Invoke('GET CHATMESSAGE ' . $aReceivedMsg[1] . ' FROM_HANDLE');
//            $sMessageRaw = $oSkype->Invoke('GET CHATMESSAGE ' . $aReceivedMsg[1] . ' BODY');
//            $aMessage = explode('BODY ', $sMessageRaw);
//
//            $aUsernameSkype = explode('FROM_HANDLE ', $sAuthRaw);
//
//            self::$iLastId = $aReceivedMsg[1];
//            if (self::$iLastId <= $aReceivedMsg[1]) {
//                self::received($aUsernameSkype[1], $aMessage[1], $aReceivedMsg[1]);
//            }
//        }

        /* Message serving 4 */
        if (preg_match('/^CHATMESSAGES/', $sNotify)) {
            $sNotify = str_replace('CHATMESSAGES ', '', $sNotify);

            if ($sNotify != '') {
                $aMessages = explode(', ', $sNotify);
                if (sizeof($aMessages)) {
                    foreach ($aMessages as $iMessageId) {
                        $sAuthRaw = $oSkype->Invoke('GET CHATMESSAGE ' . $iMessageId . ' FROM_HANDLE');
                        $sMessageRaw = $oSkype->Invoke('GET CHATMESSAGE ' . $iMessageId . ' BODY');
                        $aMessage = explode('BODY ', $sMessageRaw);
                        $aUsernameSkype = explode('FROM_HANDLE ', $sAuthRaw);

                        $aChatHash = explode('CHATNAME ', $oSkype->Invoke('GET CHATMESSAGE ' . $iMessageId . ' CHATNAME'));
                        verifyUserSkypeChatId($aUsernameSkype[1], $aChatHash[1]);

                        if (self::$iLastId < $iMessageId) {
                            self::$iLastId = $iMessageId;
                            self::received($aUsernameSkype[1], trim(strtolower($aMessage[1])), $iMessageId);
                        } else {
                            $oSkype->Invoke('SET CHATMESSAGE ' . $iMessageId . ' SEEN');
                        }
                    }
                }
            }
        }

        /* RECEIVE ALL CHATS LIST */
        if (preg_match('/^CHATS/', $sNotify)) {
            $sNotify = str_replace('CHATS ', '', $sNotify);

            if ($sNotify != '') {
                $aChats = explode(', ', $sNotify);
                if (sizeof($aChats)) {
                    foreach ($aChats as $iChatId) {
                        $sStatus = $oSkype->Invoke("GET CHAT " . $iChatId . " STATUS");
                        $sStatus = str_replace('CHAT ' . $iChatId . ' STATUS ', '', $sStatus);

                        if ($sStatus != "UNSUBSCRIBED") {
                            $sMembers = $oSkype->Invoke("GET CHAT " . $iChatId . " ACTIVEMEMBERS");
                            $sMembers = str_replace('CHAT ' . $iChatId . ' ACTIVEMEMBERS ', '', $sMembers);
                            $aMembers = explode(' ', $sMembers);
                            if (sizeof($aMembers) != 2) {
                                $oSkype->Invoke("ALTER CHAT " . $iChatId . " CLEARRECENTMESSAGES");
                                $oSkype->Invoke("ALTER CHAT " . $iChatId . " DISBAND");
                                $oSkype->Invoke("ALTER CHAT " . $iChatId . " LEAVE");
                            }
                        }
                    }
                }
            }
        }

        /* Client unlink */
        if (preg_match('/BUDDYSTATUS 2/', $sNotify)) {
            $aUser = explode(' ', $sNotify);
            deleteMe($aUser[1]);
        }

        /* Client accepted */
        if (preg_match('/BUDDYSTATUS 3/', $sNotify)) {
            $aUser = explode(' ', $sNotify);
            /* Somebody approves authorization */
            if (getUser($aUser[1])) {
                iAmConnected($aUser[1]);
            } else {
                registerUser($aUser[1]);
            }
        }

        $oDbAdapter->closeConnection();
    }

    public static function received($sUid, $sMessage, $iLastMsgId) {
        $oSkype = Zend_Registry::get('$oSkype');
        $oDbAdapter = Zend_Registry::get('$oDbAdapter');

        // Mark received message as read
        $oSkype->Invoke('SET CHATMESSAGE ' . $iLastMsgId . ' SEEN');

        /* Prepare */
        $sChatIdRaw = $oSkype->Invoke('GET CHATMESSAGE ' . $iLastMsgId . ' CHATNAME');
        $aChatId = explode('CHATNAME ', $sChatIdRaw); // $aChatId[1] is OLD chat id

        $sMembers = $oSkype->Invoke("GET CHAT " . $aChatId[1] . " ACTIVEMEMBERS");
        $sMembers = str_replace('CHAT ' . $aChatId[1] . ' ACTIVEMEMBERS ', '', $sMembers);
        $aMembers = explode(' ', $sMembers);
        if (sizeof($aMembers) > 2) {
            $oSkype->Invoke("ALTER CHAT " . $aChatId[1] . " CLEARRECENTMESSAGES");
            $oSkype->Invoke("ALTER CHAT " . $aChatId[1] . " DISBAND");
            $oSkype->Invoke("ALTER CHAT " . $aChatId[1] . " LEAVE");
            return false;
        }

        receiveMessage($sUid, $sMessage, 'SK');
    }

}

$oDbus->registerObject('/com/Skype/Client', 'com.Skype.API.Client', 'phpSkype'); //Регистрируем просмотр уведомлений скайпа

while (true) {
    $s = $oDbus->waitLoop(1);

    /* Get new IM messages */
    if ((time() % $aConfig['timeout_const']) == 0) {
        //$oSkype->Invoke('SET USERSTATUS ONLINE');
        $oSkype->Invoke('SEARCH MISSEDMESSAGES');
        usleep(600000); //microseconds
    }

    restartTimer('SK');
}