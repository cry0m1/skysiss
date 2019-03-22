<?php

function getEmail($oUser, $sClientType) {
    /* Email pool */
    $aEmails = array(
        'cryomi@gmail.com' => 'password',
        'cryo171819@gmail.com' => 'password',
    );

    if ($aDataFromCache = getCache($oUser, '10mm')) {

        /* Search free email */
        $bHasFreeEmail = false;
        foreach ($aDataFromCache as $sEmail => $aData) {
            if (null == $aData || (time() - $aData['date_created'] > 600)) {
                $aDataFromCache[$sEmail] = null;
                $bHasFreeEmail = true;
                break;
            }
        }

        if ($bHasFreeEmail) {
            $aDataFromCache[$sEmail] = array(
                'date_created' => time(),
                'user_id' => $oUser->rec_id,
                'password' => $aData['password'],
            );
            setCache($oUser, '10mm', $aDataFromCache);
            sendMessage($oUser, '_Your email for 10 minutes is: ' . $sEmail, $sClientType);

            /* Start helper */
            if ('skype' == $sClientType) {
                exec("php -f ../skype_integration/10mm_helper.php $oUser->rec_id > /dev/null 2>/dev/null &");
            } else {
                exec("php -f ../jabber_integration/10mm_helper.php $oUser->rec_id > /dev/null 2>/dev/null &");
            }
        } else {
            sendMessage($oUser, '_No free email are available, try later please', $sClientType);
        }
    } else {
        foreach ($aEmails as $sEmail => $sPassword) {
            $aDataFromCache[$sEmail] = null;
        }

        $aDataFromCache[key($aEmails[0])] = array(
            'date_created' => time(),
            'user_id' => $oUser->rec_id,
            'password' => $aEmails[0],
        );
        setCache($oUser, '10mm', $aDataFromCache);
        sendMessage($oUser, '_Your email for 10 minutes is: ' . $sEmail, $sClientType);

        /* Start helper */
        if ('skype' == $sClientType) {
            exec("php -f ../skype_integration/10mm_helper.php $oUser->rec_id > /dev/null 2>/dev/null &");
        } else {
            exec("php -f ../jabber_integration/10mm_helper.php $oUser->rec_id > /dev/null 2>/dev/null &");
        }
    }
}

function generateRateMessage($aData) {
    $sMess = '';
    foreach ($aData as $sKey => $sValue) {
        $sMess .= $sKey . ' ' . $sValue . PHP_EOL;
    }

    return $sMess;
}

function newEmail($sClientType) {
    /* Init command line params */
    $iUserId = $argv[1];

    if ($aDataFromCache = getCache($oUser, '10mm')) {
        foreach ($aDataFromCache as $sEmail => $aData) {
            if ($iUserId == $aData['user_id']) {
                break;
            }
        }

        /* Close script */
        if (time() - $aData['date_created'] > 600) {
            $oUser = getUserByParam('user_id', $iUserId);
            sendMessage($oUser, '_No email were received, email is expired', $sClientType);
            exit(0);
        }

        $hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
        $username = $sEmail;
        $password = $aData['password'];

        /* try to connect */
        $inbox = imap_open($hostname, $username, $password) or die('Cannot connect to Gmail: ' . imap_last_error());

        /* grab emails */
        $emails = imap_search($inbox, 'NEW');

        /* if emails are returned, cycle through each... */
        if ($emails) {

            /* begin output var */
            $output = '';

            /* put the newest emails on top */
            rsort($emails);

            /* for every email... */
            foreach ($emails as $email_number) {

                /* get information specific to this email */
                $overview = imap_fetch_overview($inbox, $email_number, 0);
                $message = imap_fetchbody($inbox, $email_number, 2);

                /* output the email header information */
                $output.= '<div class="toggler ' . ($overview[0]->seen ? 'read' : 'unread') . '">';
                $output.= '<span class="subject">' . $overview[0]->subject . '</span> ';
                $output.= '<span class="from">' . $overview[0]->from . '</span>';
                $output.= '<span class="date">on ' . $overview[0]->date . '</span>';
                $output.= '</div>';

                /* output the email body */
                $output.= '<div class="body">' . $message . '</div>';
            }

            if ($output) {
                $oUser = getUserByParam('user_id', $iUserId);
                sendMessage($oUser, $output, $sClientType);
            }
        }

        /* close the connection */
        imap_close($inbox);
    }
}

/* Use Zend_mail to read local messages (dir or smtp)
 * In script check memcache for temporary email (memcache: rec_id - email_address *blasblas@skysiss.com)
 * If YES check new mails every 10 sec
 * if NO - do nothing
 * 
 * or connect to imap gmail + http://php.net/manual/en/function.imap-search.php
 


   
 * Pool with all google emails (~100)
 * check free email if no - err mess
 * save to cache rec_id - email_address 
 * write & start script 
 * php script_name.php user_id [2 scripts: for skype & jabber]
 * script connects to server client
 * and checks this email (from cache rec_id - email_address ) every 15 sec
 * if new email send it to client by user rec_id
 * if cache (10 min) has ended, close the script
*/

