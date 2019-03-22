#! /bin/sh
pkill -f -9 "user_operations_worker.php"
pkill -f -9 "spamer_worker.php"
pkill -f -9 "api_worker.php"
pkill -f -9 "_skype_assistant.php"
pkill -f -9 "_jabber_assistant.php"
pkill -f -9 "_telegram_assistant.php"
killall -e skype
killall -e Xvfb
echo "Bot stopped"
exit 0 