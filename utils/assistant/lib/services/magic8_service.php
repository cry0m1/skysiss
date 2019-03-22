<?php

use PhpAmqpLib\Message\AMQPMessage;

function get8ballMQ($sUid, $sParam) {
    $oChannel = Zend_Registry::get('$oChannel');
    $aConfig = Zend_Registry::get('$aConfig');

    $oMsg = new AMQPMessage(json_encode(array(
                'api_uri' => 'magic8',
                'uid' => $sUid, //uid name
                'param' => $sParam,
    )));
    $oChannel->basic_publish($oMsg, 'skysiss_exchange', API_WORKER_ROUTING);
}

function magic8($sUid, $sExpression) {
    $aAnswers = array(
        _trlt($sUid, '_Yes.'),
        _trlt($sUid, '_No.'),
        _trlt($sUid, '_My sources are pointing toward yes.'),
        _trlt($sUid, "_It's possible."),
        _trlt($sUid, '_Very unlikely.'),
        _trlt($sUid, '_Can you repeat the question?'),
        _trlt($sUid, '_Absolutely not.'),
        _trlt($sUid, '_Sure.'),
        _trlt($sUid, '_Ask again later.'),
        _trlt($sUid, '_Iе is certain.'), // не переведено это и ниже
        _trlt($sUid, '_It is decidedly so.'),
        _trlt($sUid, '_Without a doubt.'),
        _trlt($sUid, '_Yes - definitely.'),
        _trlt($sUid, '_You may rely on it.'),
        _trlt($sUid, '_As I see it, yes.'),
        _trlt($sUid, '_Most likely.'),
        _trlt($sUid, '_Outlook good.'),
        _trlt($sUid, '_Signs point to yes.'),
        _trlt($sUid, '_Reply hazy, try again.'),
        _trlt($sUid, '_Better not tell you now.'),
        _trlt($sUid, '_Cannot predict now.'),
        _trlt($sUid, '_Concentrate and ask again.'),
        _trlt($sUid, '_Don’t count on it.'),
        _trlt($sUid, '_My sources say no.'),
        _trlt($sUid, '_Outlook not so good.'),
        _trlt($sUid, '_Very doubtful.'),
    );

    sendMessage($sUid, PHP_EOL . $aAnswers[rand(0, 25)]);
}
