<?php

require_once(dirname(dirname(dirname(__FILE__))) . "/Bootstrap_helper.php");
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . "/vendor/autoload.php");

use Telegram\Bot\Api as TgBot;
use Pathetic\TgBot\Types\ReplyKeyboardMarkup as ReplyKeyboardMarkup;

/* TELEGRAM
 *
 *
 *
 *
 */

$oTelegram = new TgBot('134029499:AAEHLXVbStMa1PE7tGDeDtgoFc8dzZqoZJM');

if ($aConfig['debug']) {
    var_dump($argv);
}

switch ($argv[1]) {//action type
    case 'add_user':
        $oTelegram->sendMessage([
            'chat_id' => $sTelegramChatId,
            'text' => urldecode(_trlt($argv[2], '_Authorization. Please authorize me! Try') . ' ' . $aConfig['logo_name']
                    . ' ' . _trlt($argv[2], '_now (Check more info at') . ' ' . $aConfig['web_url'] . ")")
        ]);
        break;
    case 'message':
        $sTelegramChatId = getUserTelegramChatId($argv[2]);

        $reply_markup = $oTelegram->replyKeyboardMarkup([
            'keyboard' => [
                '/help',
                '/lang',
                '/rnd',
                '/currency',
                '/clock',
                '/news',
                '/weather',
                '/horo',
                '/8ball',
                '/habr'
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
            'selective' => true,
        ]);

        if ($sTelegramChatId) {
            $sMessage = $argv[3];
            if ($sMessage) {
                $oTelegram->sendMessage([
                    'chat_id' => $sTelegramChatId,
                    'text' => urldecode($sMessage),
                    'reply_markup' => $oReplyMarkup
                ]);
            }
        }
        break;
}

posix_kill(getmypid(), 9);

