#!/bin/sh
Xvfb :5 &
sleep 1s

DISPLAY=:5 ; export DISPLAY ; 

killall -e skype
skype --enable-dbus --use-system-dbus &
sleep 5s

/usr/bin/php /home/skysiss/utils/assistant/lib/worker/user_operations_worker.php 1 &
/usr/bin/php /home/skysiss/utils/assistant/lib/worker/spamer_worker.php 1 &
#/usr/bin/php /home/skysiss/utils/assistant/lib/worker/spamer_worker.php 2 &
/usr/bin/php /home/skysiss/utils/assistant/lib/worker/api_worker.php 1 &
/usr/bin/php /home/skysiss/utils/assistant/lib/worker/api_worker.php 2 &
/usr/bin/php /home/skysiss/utils/assistant/_jabber_assistant.php &
/usr/bin/php /home/skysiss/utils/assistant/_telegram_assistant.php &
#sleep 2s
/usr/bin/php /home/skysiss/utils/assistant/_skype_assistant.php &
sleep 3s
echo "Bot started"
exit 0 
