<?php

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

/* User */

function registerUser($sUid) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $aData = array(
        'api_uri' => 'account/create_account',
        'uid' => $sUid, //uid name
        'uid_type' => getUidType($sUid),
        'params' => array(
            'requestor' => 'im',
        )
    );

    $oMsg = new AMQPMessage(json_encode($aData));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);

    return true;
}

function getUidType($sUid) {
    if (preg_match('/^\@[a-zA-Z0-9.\-_]{5,31}$/', $sUid)) {//telegram
        return 'TG';
    } elseif (preg_match('/^([0-9a-zA-Z].*?@([0-9a-zA-Z].*\.\w{2,4}))$/', $sUid)) {//mail
        return 'JB';
    } elseif (preg_match('/^[a-zA-Z][a-zA-Z0-9.\-_\:]{5,31}$/', $sUid)) {//skype name
        return 'SK';
    }

    return false;
}

function getFullUidType($sUidType) {
    switch ($sUidType) {
        case 'TG':
            return 'Telegram';
            break;
        case 'SK':
            return 'Skype';
            break;
        case 'JB':
            return 'Jabber';
            break;
    }
}

function deleteMe($sUid) {
    $oChannel = Zend_Registry::get('$oChannel');
    $oUser = getUser($sUid);
    $aConfig = Zend_Registry::get('$aConfig');

    if ($oUser) {
        $aData = array(
            'api_uri' => 'account/delete',
            'uid' => $sUid,
            'params' => array(
                'ui' => $oUser->rec_id,
            )
        );
        $aData['uid_type'] = getUidType($sUid);

        $oMsg = new AMQPMessage(json_encode($aData));
        $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
    }

    return true;
}

function iAmConnected($sUid) {
    $oUser = getUser($sUid);

    if ($oUser) {
        $oChannel = Zend_Registry::get('$oChannel');
        $aConfig = Zend_Registry::get('$aConfig');

        $aData = array(
            'api_uri' => 'service/connected',
            'uid' => $sUid,
            'params' => array(),
        );

        $aData['uid_type'] = getUidType($sUid);
        $oMsg = new AMQPMessage(json_encode($aData));
        $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
    } else {
        registerUser($sUid);
    }

    return true;
}

function getUser($sUid, $bRecache = false) {
    if (!$bRecache) {
        if ($iUserId = memcacheGet($sUid)) {
            if ($oUser = memcacheGet($iUserId)) {
                return $oUser;
            }
        }
    }

    $oDbAdapter = Zend_Registry::get('$oDbAdapter');
    $oUser = $oDbAdapter->fetchRow($oDbAdapter->select()
                    ->from('user')
                    ->where('uid = ?', $sUid));

    if (!$oUser) {
        return false;
    }

    memcacheSet($oUser->rec_id, $oUser);
    memcacheSet($sUid, $oUser->rec_id);

    return $oUser;
}

function getUserSkypeChatId($sUid) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');
    $oUser = getUser($sUid);

    if ($sSkypeChatId = memcacheGet($oUser->rec_id . '_chat_id')) {
        return $sSkypeChatId;
    }

    if ($oSkypeChat = $oDbAdapter->fetchRow($oDbAdapter->select()
                    ->from('user')
                    ->where('rec_id = ?', $oUser->rec_id)
                    ->where("time_format(timediff(NOW(),date_updated),'%H') <= 48"))) {

        memcacheSet($oUser->rec_id . '_chat_id', $oSkypeChat->chat_id);
        return $oSkypeChat->chat_id;
    } else {
        $oSkype = Zend_Registry::get('$oSkype');
        $sChatCreated = $oSkype->Invoke("CHAT CREATE $sUid");
        $aChatCreated = explode(' ', $sChatCreated);

        $aData = array();
        $aData['chat_id'] = $aChatCreated[1];
        updateUser($oUser->rec_id, $aData);

        memcacheSet($oUser->rec_id . '_chat_id', $aChatCreated[1]);
        return $aChatCreated[1];
    }
}

function getUserTelegramChatId($sUid, $sTelegramChatId = NULL, $sOldUid = NULL) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');
    if ($sOldUid && $sOldUid !== '@' && $oUser = getUser($sOldUid)) {
        updateUser($oUser->rec_id, array('uid' => $sUid));
    }
    $oUser = getUser($sUid);

    if ($sTelegramChatId) {
        if ($sTelegramChatId == memcacheGet($sUid . '_chat_id')) {
            if ($oUser && !$oUser->chat_id) {
                $aData = array();
                $aData['chat_id'] = $sTelegramChatId;
                updateUser($oUser->rec_id, $aData);
            }
        } else {
            memcacheSet($sUid . '_chat_id', $sTelegramChatId);
        }

        return $sTelegramChatId;
    } else {
        if ($sTelegramChatId = memcacheGet($sUid . '_chat_id')) {
            return $sTelegramChatId;
        }

        if ($oUser && $oUser->chat_id) {
            memcacheSet($sUid . '_chat_id', $oUser->chat_id);
            return $oUser->chat_id;
        }
    }
}

function verifyUserSkypeChatId($sUid, $sSkypeChatId) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');

    $oUser = getUser($sUid);
    $bRefreshSkypeChatId = false;

    if ($oUser) {
        if ($sSkypeChatIdMemcache = memcacheGet($oUser->rec_id . '_chat_id')) {
            if ($sSkypeChatId !== $sSkypeChatIdMemcache) {
                $aData = array();
                $aData['chat_id'] = '';
                updateUser($oUser->rec_id, $aData);

                memcacheDelete($oUser->rec_id . '_chat_id');
                $bRefreshSkypeChatId = true;
            }
        } else {
            if ($sSkypeChatId !== $oUser->chat_id) {
                $aData = array();
                $aData['chat_id'] = '';
                updateUser($oUser->rec_id, $aData);
                $bRefreshSkypeChatId = true;
            }
        }

        if ($bRefreshSkypeChatId) {
            $aData = array();
            $aData['chat_id'] = $sSkypeChatId;
            updateUser($oUser->rec_id, $aData);
            memcacheSet($oUser->rec_id . '_chat_id', $sSkypeChatId);
        }
    }

    return true;
}

function getUserByParam($sParamName, $sParamValue) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');

    $oUser = $oDbAdapter->fetchRow($oDbAdapter->select()
                    ->from('user')
                    ->where("$sParamName = ?", $sParamValue));
    return $oUser;
}

function recacheUser($sUid) {
    return getUser($sUid, true);
}

function generateLink($sUid, $sLinkType) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');
    $aData = array();

    $aData['api_uri'] = 'account/temporary_link';
    $aData['uid_type'] = getUidType($sUid);
    $aData['uid'] = $sUid;
    $aData['params'] = array(
        'link_type' => $sLinkType,
    );

    $oMsg = new AMQPMessage(json_encode($aData));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);

    return true;
}

function addTemporaryLink($aData) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');

    $oDbAdapter->insert('temporary_link', $aData);
    return $oDbAdapter->lastInsertId('temporary_link');
}

function updateUser($iUserId, $aData) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');
    return $oDbAdapter->update('user', $aData, array('rec_id = ?' => $iUserId));
}

function getXmessAssitant($sUid) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');
    $oUser = recacheUser($sUid);

    if ($oUser && $oUser->chat_id) {
        return $oUser->chat_id;
    } else {
//        if ($oRec = $oDbAdapter->fetchRow($oDbAdapter->select()
//                        ->from('xmess_assistant')
//                        ->where("skype = ?", $sUid))) {
//
//            return $oRec->chat_id;
//        } else {
        $oSkype = Zend_Registry::get('$oSkype');

        $sChatCreated = $oSkype->Invoke("CHAT CREATE $sUid");
        $aChatCreated = explode(' ', $sChatCreated);

//            $oDbAdapter->insert('xmess_assistant', array(
//                'skype' => $sUid,
//                'chat_id' => $aChatCreated[1],
//            ));

        return $aChatCreated[1];
//        }
    }
}

/* Cache */
/* Memcache */

function memcacheSet($mKey, $mData, $iTime = 3600) {
    /* $iTime in seconds */
    $oMemcache = Zend_Registry::get('$oMemcache');
//$oMemcache->set($mKey, serialize($mData), false, $iTime) or die("Failed to save data at the memcache");
    $oMemcache->set($mKey, $mData, $iTime) or die("Failed to save data at the memcache");
}

function memcacheGet($mKey) {
    $oMemcache = Zend_Registry::get('$oMemcache');
//return unserialize($oMemcache->get($mKey));
    return $oMemcache->get($mKey);
}

function memcacheDelete($mKey) {
    $oMemcache = Zend_Registry::get('$oMemcache');
    return $oMemcache->delete($mKey);
}

function memcacheReplace($mKey, $mData, $iTime = 3600) {
    /* $iTime in seconds */
    $oMemcache = Zend_Registry::get('$oMemcache');
//$oMemcache->replace($mKey, serialize($mData), false, $iTime) or die("Failed to save data at the memcache");
    $oMemcache->replace($mKey, $mData, $iTime) or die("Failed to save data at the memcache");
}

function memcacheIncrement($mKey, $iValue = 1) {
    $oMemcache = Zend_Registry::get('$oMemcache');
    return $oMemcache->increment($mKey, $iValue);
}

function memcacheDecrement($mKey, $iValue = 1) {
    $oMemcache = Zend_Registry::get('$oMemcache');
    return $oMemcache->decrement($mKey, $iValue);
}

/* Lang */

function _trlt($sUid, $sMessage) {
    $oUser = getUser($sUid);
    $oTranslate = Zend_Registry::get('$oTranslate');

    if (!$oUser || !$sUserLocale = $oUser->lang_short_name) {
        $sUserLocale = 'en';
    }

    $oTranslate->setLocale($sUserLocale);
    return $oTranslate->_($sMessage);
}

function changeLangMQ($sUid, $sParam) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'changeLang',
                'uid' => $sUid, //uid name
                'param' => $sParam,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function changeLang($sUid, $sLocale) {
    if (in_array($sLocale, array('en', 'es', 'de', 'ru'))) {
        $oUser = getUser($sUid);
        updateUser($oUser->rec_id, array('lang_short_name' => strtolower($sLocale)));
        sendMessage($sUid, _trlt($sUid, '_Changed'));
        recacheUser($sUid);
    } else {
        sendMessage($sUid, _trlt($sUid, '_Not valid locale'));
    }
}

/* Common */

function receiveMessage($sUid, $sMessage, $sUidType) {
    $aConfig = Zend_Registry::get('$aConfig');
    $oUser = getUser($sUid);
    addLogMQ($sUid, $sMessage);
    $sMessage = trim($sMessage);
    if ($aConfig['debug']) {
        var_dump($sMessage);
    }

    $aMessage = explode(' ', $sMessage);
    if (!$oUser || 'NA' === $oUser->status) {
        if ($sUid) {
            if (preg_match('/registerme/', $aMessage[0])) {
                registerUser($sUid);
            } else {
                sendMessage($sUid, _trlt($sUid, '_Type #registerme'));
            }
        } else {
            /* Script cannot obtain user uid */
        }
    } elseif ('AC' === $oUser->status) {
        /* Commands exec */
        if (preg_match('/help/', $aMessage[0])) {
            helpMeMQ($sUid, isset($aMessage[1]) ? $aMessage[1] : '');
        } else {
            /* Erase other apps memcache flash memory */
            $sCommand = substr($aMessage[0], 1); //#command -> command
            switch ($sCommand) {
                //base bot
                case 'lang':
                    memcacheDelete('dev' . $oUser->rec_id);
                    memcacheDelete('lifestyle' . $oUser->rec_id);
                    memcacheDelete('office' . $oUser->rec_id);
                    break;

                //dev bot
                case 'hash':
                case 'ssh':
                    memcacheDelete('lifestyle' . $oUser->rec_id);
                    memcacheDelete('office' . $oUser->rec_id);
                    break;

                //lifestyle bot
                case 'weather':
                case 'horo':
                case '8ball':
                case 'tower':
                case 'xmess':
                    memcacheDelete('dev' . $oUser->rec_id);
                    memcacheDelete('office' . $oUser->rec_id);
                    break;

                //office
                case 'calc':
                case 'currency':
                case 'clock':
                    memcacheDelete('dev' . $oUser->rec_id);
                    memcacheDelete('lifestyle' . $oUser->rec_id);
                    break;
            }

            /* Serve message */
            $sCommand = substr($aMessage[0], 1); //#command -> command
            switch ($sCommand) {
                //base bot
                case 'deleteme':
                    deleteMe($sUid);
                    break;
                case 'loginlink':
                    generateLink($sUid, 'account/login/imlogin');
                    break;
                case 'lang':
                    if (isset($aMessage[1])) {
                        changeLangMQ($sUid, $aMessage[1]);
                    } else {
                        sendMessage($sUid, _trlt($sUid, '_Enter your locale, please:'));
                    }
                    break;
                case 'en':
                case 'ru':
                case 'es':
                case 'de':
                case 'ch':
                    changeLangMQ($sUid, $sCommand);
                    break;

                //dev bot
                case 'hash':
                case 'ssh':
                    if (isset($aMessage[1])) {
                        $sFunctionName = 'get' . ucfirst($sCommand) . 'MQ';
                        $sFunctionName($sUid, $aMessage[1]);
                    } else {
                        memcacheSet('dev' . $oUser->rec_id, $sCommand, 86400); //24h
                        helpMeMQ($sUid, $sCommand);
                    }
                    break;
                case 'rnd':
                    $aDayArr = array(
                        0 => 'Monday',
                        1 => 'Tuesday',
                        2 => 'Wednesday',
                        3 => 'Thursday',
                        4 => 'Friday',
                    );
                    sendMessage($sUid, $aDayArr[rand(0, 4)]);
                    break;
                case 'stat':
                    helpMeMQ($sUid, 'stat');
                    statParseMQ($sUid);
                    break;
                case 'habr':
                    habrParseMQ($sUid);
                    break;
                case 'push':
                case '2auth':
                    helpMeMQ($sUid, $sCommand);
                    break;

                //lifestyle bot
                case 'weather':
                case '8ball':
                    if (isset($aMessage[1])) {
                        $sFunctionName = 'get' . ucfirst($sCommand) . 'MQ';
                        $sFunctionName($sUid, $aMessage[1]);
                    } else {
                        memcacheSet('lifestyle' . $oUser->rec_id, $sCommand, 86400); //24h
                        helpMeMQ($sUid, $sCommand);
                    }
                    break;
                case 'horo':
                    memcacheSet('lifestyle' . $oUser->rec_id, $sCommand, 86400); //24h
                    if (isset($aMessage[1])) {
                        $sFunctionName = 'get' . ucfirst($sCommand) . 'MQ';
                        $sFunctionName($sUid, $aMessage[1]);
                    } else {
                        helpMeMQ($sUid, $sCommand);
                    }
                    break;
                case 'tower':
                    memcacheSet('lifestyle' . $oUser->rec_id, $sCommand, 86400); //24h
                    helpMeMQ($sUid, $sCommand);
                    break;
                case 'xmess':
                    if (isset($aMessage[1]) && isset($aMessage[2])) {
                        if (getUidType($aMessage[1])) {
                            if ('@' === $aMessage[1][0] && !$oTGUser = getUser($aMessage[1])) {
                                sendMessage($sUid, _trlt($sUid, '_your message is not sent. Telegram opponent has not added the bot'));
                            } else {
                                $sUserMessage = '';
                                for ($index = 2; $index < count($aMessage); $index++) {
                                    $sUserMessage .= $aMessage[$index] . ' ';
                                }
                                sendMessage($aMessage[1], getFullUidType($sUidType) . ': ' . $oUser->uid . ' ' .
                                        _trlt($sUid, '_sends you a message:') . ' "' . trim($sUserMessage) .
                                        '"' . PHP_EOL . _trlt($sUid, '_if you do not know him, just ignore message. More info at') . ' ' .
                                        $aConfig['web_url']);
                                sendMessage($sUid, _trlt($sUid, '_your message is sent'));
                            }
                        } else {
                            memcacheSet('lifestyle' . $oUser->rec_id, $sCommand, 86400); //24h
                            helpMeMQ($sUid, 'xmess');
                        }
                    } else {
                        memcacheSet('lifestyle' . $oUser->rec_id, $sCommand, 86400); //24h
                        helpMeMQ($sUid, 'xmess');
                    }
                    break;

                //office
                case 'calc':
                    memcacheSet('office' . $oUser->rec_id, $sCommand, 86400); //24h
                    helpMeMQ($sUid, $sCommand);
                    break;
                case 'currency':
                    memcacheSet('office' . $oUser->rec_id, $sCommand, 86400); //24h
                    if (isset($aMessage[1])) {
                        $sFunctionName = 'get' . ucfirst($sCommand) . 'MQ';
                        $sFunctionName($sUid, $aMessage[1]);
                    } else {
                        helpMeMQ($sUid, $sCommand);
                    }
                    break;
                case 'clock':
                    if (isset($aMessage[1])) {
                        $sFunctionName = 'get' . ucfirst($sCommand) . 'MQ';
                        $sFunctionName($sUid, $aMessage[1]);
                    } else {
                        memcacheSet('office' . $oUser->rec_id, $sCommand, 86400); //24h
                        helpMeMQ($sUid, 'clock');
                    }
                    break;
                case 'news':
                    newsParseMQ($sUid);
                    break;
                default :
                    //dev bot
                    $sDevUserPos = memcacheGet('dev' . $oUser->rec_id);
                    switch ($sDevUserPos) {
                        case 'hash':
                        case 'ssh':
                            memcacheSet('dev' . $oUser->rec_id, $sDevUserPos, 86400); //24h
                            $sFunctionName = 'get' . ucfirst($sDevUserPos) . 'MQ';
                            $sFunctionName($sUid, $sMessage);
                            break;
                    }

                    //lifestyle bot
                    $sLifestyleUserPos = memcacheGet('lifestyle' . $oUser->rec_id);
                    switch ($sLifestyleUserPos) {
                        case 'weather':
                        case 'horo':
                        case '8ball':
                        case 'tower':
                            memcacheSet('lifestyle' . $oUser->rec_id, $sLifestyleUserPos, 86400); //24h
                            $sFunctionName = 'get' . ucfirst($sLifestyleUserPos) . 'MQ';
                            $sFunctionName($sUid, $sMessage);
                            break;
                        case 'xmess':
                            memcacheSet('lifestyle' . $oUser->rec_id, $sLifestyleUserPos, 86400); //24h
                            $aMessage = explode(' ', $sMessage);
                            if (getUidType($aMessage[0])) {
                                if ('@' === $aMessage[0][0] && !$oTGUser = getUser($aMessage[0])) {
                                    sendMessage($sUid, _trlt($sUid, '_your message is not sent. Telegram opponent has not added the bot'));
                                } else {
                                    $sUserMessage = '';
                                    for ($index = 1; $index < count($aMessage); $index++) {
                                        $sUserMessage .= $aMessage[$index] . ' ';
                                    }
                                    sendMessage($aMessage[0], getFullUidType($sUidType) . ': ' . $oUser->uid . ' ' .
                                            _trlt($sUid, '_sends you a message:') . ' "' . trim($sUserMessage) .
                                            '"' . PHP_EOL . _trlt($sUid, '_if you do not know him, just ignore message. More info at') . ' ' .
                                            $aConfig['web_url']);
                                    sendMessage($sUid, _trlt($sUid, '_your message is sent'));
                                }
                            } else {
                                helpMeMQ($sUid, 'xmess');
                            }
                            break;
                    }

                    //office
                    $sOfficeUserPos = memcacheGet('office' . $oUser->rec_id);
                    switch ($sOfficeUserPos) {
                        case 'calc':
                        case 'currency':
                        case 'clock':
                            memcacheSet('office' . $oUser->rec_id, $sOfficeUserPos, 86400); //24h
                            $sFunctionName = 'get' . ucfirst($sOfficeUserPos) . 'MQ';
                            $sFunctionName($sUid, $sMessage);
                            break;
                    }

                    //base bot
                    if (!$sDevUserPos && !$sLifestyleUserPos && !$sOfficeUserPos) {
                        helpMeMQ($sUid, '');
                    }

                    break;
            }
        }
        //}
    } elseif ('IA' === $oUser->status) {
        /* Commands exec */
        if ($sUid) {
            if (preg_match('/activateme/', $aMessage[0])) {
                generateLink($sUid, 'account/login/activate');
            } else {
                sendMessage($sUid, _trlt($sUid, '_Type #activateme'));
            }
        } else {
            /* Script cannot obtain user uid */
        }


//        if (preg_match('/#help/', $sMessage)) {
//            $aMessage = explode(' ', $sMessage);
//            helpMeMQ($sUid, isset($aMessage[1]) ? $aMessage[1] : '');
//        } else {
//            switch ($sMessage) {
//                case '#activateme': generateLink($sUid, 'account/login/activate');
//                    break;
//                default :
//                    helpMeMQ($sUid, '');
//                    break;
//            }
//        }
    }
}

function sendMessage($sUid, $sMessage) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    if (in_array(getUidType($sUid), array('TG', 'JB'))) {
        $sMessage = preg_replace('/#/', '/', $sMessage);
    }

    addLogOutMQ($sUid, $sMessage);

    $aData = array(
        'uid' => $sUid,
        'message' => $sMessage,
    );
    $aData['uid_type'] = getUidType($sUid);
    $oMsg = new AMQPMessage(json_encode($aData));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', SPAMER_WORKER_ROUTING);

    return true;
}

function generateHash($id) {
    $aConfig = Zend_Registry::get('$aConfig');
    $aData = unpack("h*hex", mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $aConfig['hash_password'], sprintf('%010d', $id), MCRYPT_MODE_ECB));

    return strtoupper(array_pop($aData));
}

function helpMeMQ($sUid, $sParam) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'helpMe',
                'uid' => $sUid, //uid name
                'param' => $sParam,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function helpMe($sUid, $sParam) {
    $oUser = getUser($sUid);
    $sAnswer = '';

    /* Common data */
    if ($sAnswer = memcacheGet($oUser->lang_short_name . $oUser->uid_type . $sParam)) {

    } else {
        switch ($sParam) {
            case 'calc':
                $sAnswer = _trlt($sUid, '_Supported expressions:');
                $sAnswer .= PHP_EOL . '+';
                $sAnswer .= PHP_EOL . '-';
                $sAnswer .= PHP_EOL . '*';
                $sAnswer .= PHP_EOL . '/';
                $sAnswer .= PHP_EOL . '()';
                $sAnswer .= PHP_EOL . _trlt($sUid, '_m - memory value (last calculated)');
                $sAnswer .= PHP_EOL;
                $sAnswer .= PHP_EOL . _trlt($sUid, '_Examples:');
                $sAnswer .= PHP_EOL . '(1+21)*2+3/2 ' . _trlt($sUid, '_will produce') . ' 45.5';
                $sAnswer .= PHP_EOL . '132.23+48647687.01 ' . _trlt($sUid, '_will produce') . ' 48647819.24';
                $sAnswer .= PHP_EOL . 'm+2 ' . _trlt($sUid, '_will produce') . ' 47.5';
                break;
            case 'currency':
                $sAnswer = PHP_EOL;
                $sAnswer .= '=== [' . _trlt($sUid, '_Conversion') . '] ===' . PHP_EOL;
                $sAnswer .= _trlt($sUid, '1. _Get conversions rates') . PHP_EOL;
                $sAnswer .= PHP_EOL;
                $sAnswer .= '=== [' . _trlt($sUid, '_Rates') . '] ===' . PHP_EOL;
                $sAnswer .= _trlt($sUid, '2. _International source') . PHP_EOL;
                $sAnswer .= _trlt($sUid, '3. _Yahoo') . PHP_EOL; // http://finance.yahoo.com/webservice/v1/symbols/allcurrencies/quote
                $sAnswer .= _trlt($sUid, '4. _European Central Bank') . PHP_EOL; // http://www.ecb.int/stats/eurofxref/eurofxref-daily.xml
                $sAnswer .= _trlt($sUid, '5. _OCBC Bank') . PHP_EOL; // http://www.ocbc.com/business-banking/Foreign-exchange-rates.html
                $sAnswer .= _trlt($sUid, '6. _Central Bank of Russia') . PHP_EOL; // http://www.cbr.ru/DailyInfoWebServ/DailyInfo.asmx
                $sAnswer .= _trlt($sUid, '7. _The National Bank of Ukraine') . PHP_EOL;
// http://tables.finance.ua/ru/currency/official
// http://finance.ua/currency/data?for=currency-cash
                $sAnswer .= _trlt($sUid, '8. _The National Bank of Belarus') . PHP_EOL; // http://finance.tut.by/kurs_main.php
                $sAnswer .= _trlt($sUid, '9. _Bank of China') . PHP_EOL;
                $sAnswer .= _trlt($sUid, '10. _Bank of Canada') . PHP_EOL; // http://www.bankofcanada.ca/stats/assets/xml/noon-five-day.xml
                $sAnswer .= _trlt($sUid, '11. _Bitcoin Charts') . PHP_EOL; // http://api.bitcoincharts.com/v1/weighted_prices.json
                $sAnswer .= PHP_EOL . _trlt($sUid, '_Enter any number') . PHP_EOL;
                break;
            case 'clock':
                $sAnswer = PHP_EOL . _trlt($sUid, '_Enter City you want (ex.: Minsk)');
                break;
            case '8ball':
                $sAnswer = PHP_EOL . _trlt($sUid, '_Write any question, i will guess...');
                break;
            case 'weather':
                $sAnswer = PHP_EOL . _trlt($sUid, '_Or enter City you want (English* only, ex.: Moscow)');
                break;
            case 'horo':
                $sAnswer = PHP_EOL;
                $sAnswer .= '1. ' . _trlt($sUid, '_aries') . PHP_EOL;
                $sAnswer .= '2. ' . _trlt($sUid, '_leo') . PHP_EOL;
                $sAnswer .= '3. ' . _trlt($sUid, '_sagittarius') . PHP_EOL;
                $sAnswer .= '4. ' . _trlt($sUid, '_taurus') . PHP_EOL;
                $sAnswer .= '5. ' . _trlt($sUid, '_virgo') . PHP_EOL;
                $sAnswer .= '6. ' . _trlt($sUid, '_capricorn') . PHP_EOL;
                $sAnswer .= '7. ' . _trlt($sUid, '_gemini') . PHP_EOL;
                $sAnswer .= '8. ' . _trlt($sUid, '_libra') . PHP_EOL;
                $sAnswer .= '9. ' . _trlt($sUid, '_aquarius') . PHP_EOL;
                $sAnswer .= '10. ' . _trlt($sUid, '_cancer') . PHP_EOL;
                $sAnswer .= '11. ' . _trlt($sUid, '_scorpio') . PHP_EOL;
                $sAnswer .= '12. ' . _trlt($sUid, '_pisces') . PHP_EOL;
                $sAnswer .= PHP_EOL . _trlt($sUid, '_Enter any number');
                break;
            case 'hash':
                $sAnswer = PHP_EOL;
                foreach (hash_algos() as $sHashAlgo) {
                    $sAnswer .= $sHashAlgo . PHP_EOL;
                }
                $sAnswer .= PHP_EOL . _trlt($sUid, '_Enter any string');
                break;
            case 'stat':
                $sAnswer = PHP_EOL;
                $sAnswer .= _trlt($sUid, '_How to (Linux only, also you can modify skysiss_stat.sh, what stats to gather):');
                break;
            case 'push':
                $sAnswer = PHP_EOL;
                $sAnswer .= "GET/POST API url: ";
                break;
            case '2auth':
                $sAnswer = PHP_EOL;
                $sAnswer .= "GET/POST 2-factor auth API url: ";
                break;
            case 'tower':
                $sAnswer = PHP_EOL;
                $sAnswer .= PHP_EOL . _trlt($sUid, '_Game "Hanoi Tower". Enter you complexity 1-10');
                $sAnswer .= PHP_EOL . PHP_EOL . _trlt($sUid, '_Move circles from 1 to anothre column, for ex.: 1-2');
                break;
            case 'xmess':
                $sAnswer = PHP_EOL . _trlt($sUid, '_Send your message to any skype, jabber or whatsapp, example:');
                $sAnswer .= PHP_EOL . _trlt($sUid, '_skypename(or jabber email, phone number without +) your_message');
                $sAnswer .= PHP_EOL . PHP_EOL . _trlt($sUid, '_billy.jhonson Hello! Howdy?');
                $sAnswer .= PHP_EOL . _trlt($sUid, '_billy_jhonson@gmail.com Hello! Howdy?');
                $sAnswer .= PHP_EOL . _trlt($sUid, '_telegramuser Hello! Howdy?');
                break;
            case 'ssh':
                $sAnswer = PHP_EOL . _trlt($sUid, '_Connect to server by typing "username|||password|||hostname_or_ip"');
                break;
            default:
                /* Generate text menu */
                $aCommandList = array();
                $aCommandList[]['txt'] = '_#help - view this list';
//$aCommandList[]['txt'] = '_#loginlink - give you login link';
                $aCommandList[]['txt'] = '_#deleteme - delete your account';
                $aCommandList[]['txt'] = '_#lang - change interface language (type: #help #lang for help)';
                $aCommandList[]['txt'] = PHP_EOL;

                $aCommandList[]['txt'] = '_#calc - calculator';
                $aCommandList[]['txt'] = '_#currency - currency exchange rates';
                $aCommandList[]['txt'] = '_#clock - world clock';
                $aCommandList[]['txt'] = '_#news - news feed';
                $aCommandList[]['txt'] = PHP_EOL;

                $aCommandList[]['txt'] = '_#weather - weather';
                $aCommandList[]['txt'] = '_#horo - horoscope';
                $aCommandList[]['txt'] = '_#8ball - magic 8 ball';
                $aCommandList[]['txt'] = '_#tower - game "Hanoi Tower"';
                $aCommandList[]['txt'] = '_#xmess - send your message to any skype, jabber or whatsapp';
                $aCommandList[]['txt'] = PHP_EOL;

                $aCommandList[]['txt'] = '_#habr - best for night articles';
                $aCommandList[]['txt'] = '_#hash - hash maker';
                $aCommandList[]['txt'] = '_#stat - get your linux server statistics';
                $aCommandList[]['txt'] = '_#ssh - execute ssh commands at your server (service do net remeber your creds)';
                $aCommandList[]['txt'] = '_#push - send any push notification with GET/POST api request';
                $aCommandList[]['txt'] = '_#2auth - 2 factor Auth. Send code to user IM with GET/POST api request';

                $sAnswer = PHP_EOL . '=== [' . _trlt($sUid, '_System Commands') . '] ===' . PHP_EOL;
                foreach ($aCommandList as $iKey => $aValue) {
                    $sAnswer .= _trlt($sUid, $aValue['txt']) . PHP_EOL;
                }
                break;
        }
    }

    if (mb_strlen($sAnswer)) {
        sendMessage($sUid, $sAnswer);
        memcacheSet($oUser->lang_short_name . $oUser->uid_type . $sParam, $sAnswer, 14400);
    }

    /* User-spicific data */
    $sAnswer = '';
    if ($sAnswer = memcacheGet('dev' . $oUser->rec_id . $oUser->lang_short_name . $sParam)) {

    } else {
        switch ($sParam) {
            case 'ssh':
//                $oSshs = getServerSsh($oUser->rec_id);
//                if (count($oSshs)) {
//                    $sAnswer = PHP_EOL . PHP_EOL . _trlt($sUid, '_Available servers:');
//
//                    foreach ($oSshs as $iKey => $oSsh) {
//                        $sAnswer .= $iKey . '. ' . $oSsh->hostname_or_ip . PHP_EOL;
//                    }
//                }
                break;
            case 'stat':
                $sAnswer .= PHP_EOL . '1. wget https://skysiss.com/skysiss_stat.sh';
                $sAnswer .= PHP_EOL . '2. echo \'output=`curl "http://api.skysiss.com/1.0/rest/public/devstat?api_key=public&hostname=$dnsdomainame&status=$status&uh=' . $oUser->hash . '&response=xml&auth_token=' . md5($oUser->rec_id . $oUser->uid) . '"`\' >> skysiss_stat.sh';
                $sAnswer .= PHP_EOL . '3. crontab -e';
                $sAnswer .= PHP_EOL . '* * * * * sleep 30; /bin/sh PATH_TO_skysiss_stat.sh >/dev/null 2>&1';
                $sAnswer .= PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . _trlt($sUid, '_Available servers:');
                break;
            case 'push':
                $sAnswer .= PHP_EOL . 'http://api.skysiss.com/1.0/rest/public/devpush?api_key=public&uid=[SKYPE_OR_JABBER]&message=[MESSAGE]&uh=' . $oUser->hash . '&response=[JSON_OR_XML]&auth_token=' . md5($oUser->rec_id . $oUser->uid);
                $sAnswer .= PHP_EOL . PHP_EOL . 'Selfie: http://api.skysiss.com/1.0/rest/public/devpush?api_key=public&uid=' . $sUid . '&message=Works!&uh=' . $oUser->hash . '&response=json&auth_token=' . md5($oUser->rec_id . $oUser->uid);
                break;
            case '2auth':
                $sAnswer .= PHP_EOL . 'http://api.skysiss.com/1.0/rest/public/dev2fauth?api_key=public&uid=[SKYPE_OR_JABBER]&message=[ANY_YOUR_MESSAGE_WITH_CUSTOM_@code@_PLACEMENT]&uh=' . $oUser->hash . '&response=[JSON_OR_XML]&auth_token=' . md5($oUser->rec_id . $oUser->uid);

                $sAnswer .= PHP_EOL . PHP_EOL . 'Selfie: http://api.skysiss.com/1.0/rest/public/dev2fauth?api_key=public&uid=' . $sUid . '&message=' . urlencode('Dear %User%, you have requested  for 2-factor auth code @code@ at company X.') . '&uh=' . $oUser->hash . '&response=json&auth_token=' . md5($oUser->rec_id . $oUser->uid);
                break;
            case 'help':
                /* Generate text menu */
                $iKey = 5;
                $aCommandList = array();

                if ('AC' !== $oUser->status) {
                    $aCommandList[$iKey++]['txt'] = _trlt($sUid, '_#activateme - activate account  with a link');
                }

                $sAnswer .= PHP_EOL . '=== [' . _trlt($sUid, '_Help Links') . '] ===' . PHP_EOL;
                foreach ($aCommandList as $iKey => $aValue) {
                    $sAnswer .= _trlt($sUid, $aValue['txt']) . PHP_EOL;
                }

                $aCommandList = array();
                $aCommandList[$iKey++]['txt'] = _trlt($sUid, '_Last ip: ') . long2ip($oUser->ip);

                $sAnswer .= PHP_EOL . '=== [' . _trlt($sUid, '_My') . '] ===' . PHP_EOL;
                foreach ($aCommandList as $iKey => $aValue) {
                    $sAnswer .= $aValue['txt'] . PHP_EOL;
                }
                break;
        }
    }

    if (mb_strlen($sAnswer)) {
        memcacheSet($oUser->rec_id . $oUser->lang_short_name . $sParam, $sAnswer, 120);
        sendMessage($sUid, $sAnswer);
    }

    return true;
}

function randomPassword() {
    $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
    $pass = array(); //remember to declare $pass as an array
    $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
    for ($i = 0; $i < 8; $i++) {
        $n = rand(0, $alphaLength);
        $pass[] = $alphabet[$n];
    }
    return implode($pass); //turn the array into a string
}

function randomSpaces() {
    $i = 0;
    $sRet = '';
    while ($i < rand(1, 5)) {
        $sRet .= ' ';
        $i++;
    }
    return $sRet;
}

function restartTimer($sUidType) {
    $aConfig = Zend_Registry::get('$aConfig');
    $bSMLastUpdate = time();

    if (($bSMLastUpdate - Zend_Registry::get('time')) > 14400 * $aConfig['timeout_const']) {//2*14400=4h
        echo "$sUidType timeout restart";
        exit(0); //restart itself
    }
}

/* Services */

function getServerStats($iUserId) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');

    return $oDbAdapter->fetchAll("SELECT *, NOW() as now FROM `dev_stat` WHERE user_id = $iUserId");
}

function getServerSsh($iUserId) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');

    return $oDbAdapter->fetchAll("SELECT * FROM `dev_ssh` WHERE user_id = $iUserId");
}

/* Log */

function addLogMQ($sUid, $sMessage) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'addLog',
                'uid' => $sUid, //uid name
                'param' => $sMessage,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function addLog($sUid, $sMessage) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');
    $oUser = getUser($sUid);
    $aConfig = Zend_Registry::get('$aConfig');

    if ($oUser) {
        $aData['user_id'] = $oUser->rec_id;
    } else {
        $aData['user_id'] = 1;
        $aData['uid'] = $sUid;
    }

    $aData['command'] = $sMessage;
    $aData['type'] = getUidType($sUid);
    $oDbAdapter->insert('command_log', $aData);
    return true;
}

function addLogOutMQ($sUid, $sMessage) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'addLogOut',
                'uid' => $sUid, //uid name
                'param' => $sMessage,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function addLogOut($sUid, $sMessage) {
    $oDbAdapter = Zend_Registry::get('$oDbAdapter');
    $oUser = getUser($sUid);
    $aConfig = Zend_Registry::get('$aConfig');

    if ($oUser) {
        $aData['user_id'] = $oUser->rec_id;
    } else {
        $aData['user_id'] = 1;
        $aData['uid'] = $sUid;
    }

    $aData['command'] = $sMessage;
    $aData['type'] = getUidType($sUid);
    $oDbAdapter->insert('command_log_out', $aData);
    return true;
}

function timeSince($since) {
    $chunks = array(
        array(60 * 60 * 24 * 365, 'year'),
        array(60 * 60 * 24 * 30, 'month'),
        array(60 * 60 * 24 * 7, 'week'),
        array(60 * 60 * 24, 'day'),
        array(60 * 60, 'hour'),
        array(60, 'minute'),
        array(1, 'second')
    );

    for ($i = 0, $j = count($chunks); $i < $j; $i++) {
        $seconds = $chunks[$i][0];
        $name = $chunks[$i][1];
        if (($count = floor($since / $seconds)) != 0) {
            break;
        }
    }

    $print = ($count == 1) ? '1 ' . $name : "$count {$name}s ago";
    return $print;
}

