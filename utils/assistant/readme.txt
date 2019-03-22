====================================
МОНИТОРИНГ

1. Jabber http://im.skysiss.com:5280/admin/
2. RabbitMQ
    freebsd: http://skysiss.com:15672/#/
    debian: http://skysiss.com:55672/#/
3. Memcache http://skysiss.com/memcache/index.php
4. Opcache http://skysiss.com/OpCacheGUI/public/index.php

====================================
Добавление нового сервиса

Шаги:
1. Создать сервис в бд
2. создать нового  юзера
3. Запустить под ним 3 воркера и 3 IM клиента этого сервиса (прочекать наличие конфига и library)

====================================
Команда на добавление _gc.php в cron:

====================================
Структура memcache:

 1. [<sBuddyUsername>] = USER_ID / 3600
 1.1 [USER_ID] = $oUser / 3600
 1.2 [USER_ID_skype_chat_id] = skype_chat_id / 3600
 2. [weather] = [<city_name>] = [city data] / 36000
 3. [xxx_user] = [<FIELD>] = [DATA] / (10800, 72000, 36000)
 3.1. [position] = position in menu
-- 4. [newUsers_<SERVICENAME>] = int / skype - by config, jabber - hard coded
-- 5. [skype_adduser_] = iUserId => sBuddyUsername / 10
-- 6. [jabber_adduser_] = iUserId => sBuddyUsername / 10
 7. [<sBuddyUsername>_last_msg_id] = iLastReceivedMsgId / 3600 // disabled
-- 8. [skype_deleteuser_] = iUserId => sBuddyUsername / 10
-- 9. [jabber_deleteuser_] = iUserId => sBuddyUsername / 10
 10. [<sBuddyUsername>_last_msg_length] = length of last send by script message / 3600
 11. [choose_city_iUserId] => array(key => name) / 1800
 12. [iUserId_menu_recache] => 1/0 / 3600
 13. [service_(SERVICE_ID|SERVICE_SHORT_NAME)|POOL_ID] => record from table 'services' / 72000
 14. $oLocales - list of locales from table 'language' / 3600
 15.1. [LOCALE|SERVICENAME|] = MENU_TXT / 72000 //common menu
 15.2. [LOCALE|SERVICENAME|PARAM] = MENU_TXT / 72000 //common menu
 15.3. [iUserId|LOCALE|SERVICENAME|] = MENU_TXT / 72000 //user specific menu
 15.4. [iUserId|LOCALE|SERVICENAME|PARAM] = MENU_TXT / 72000 //user specific menu
 16. [iUserId|calc] = memory lsat calculation / 72000
 17. [weather|CITY] = [city data] / 3600
 18. [weather_location|CITY] = [location data] / 148000
 19. [weather_location_item|iUserId|iKey] = [location item] / 3600
 20. [currency|TYPE] = [data] / 7200 //more info at currency_service.php
 21. [tower|iUserId] = [data] / 3600 //more info at tower_service.php
 22. [clock|CITY] = [data] / 300
 22. [iUserId|onespam] = [boolean] / 10
 23. [iLast24h] = [int] / 600
 24. [stat|iUserId] = [data] / 10
 25. [news|lang_short_name] / 14400
 26. [habr] / 28800
 27. [iUserId|ip|devpush] / 60
 28. [user_service_PARAM1PARAM2|POOL_ID] => record from table 'user_services' / 3600
 29. [fluent|iUserId] = opponent uid, my locale, opponent locale / 1200
 30. [fluent|UID] = opponent uid / 1200
 31. [fluent_aPossibleTr] = list of supported trans / 148000
 32. [iUserId|whatsapp uid|xmess] = count of excessive usage of 'xmess' app to whatsapp usage / 20x
 33. [reconnectWa|SERVICENAME] = when reconnect WA / 3600-7200
 34. [contactSyncWa|SERVICENAME] = when sync contacts / 3600-7200
 35. [pongWa|SERVICENAME] = send pong to WA / 50

====================================
Структура RabbitMQ:

1. user_operations_worker:
'uid_type'=[skype, jabber, whatsapp]
'action_type'=[add_user, delete_user, add_service, delete_service, invite_user]
'uid'
'service_short_name'
'requestor'=[web, im, invite] --optional
'user_hash' --optional
'password_hash' --optional
'nickname' --optional
'inviter' --optional

2. api_worker:
'api_uri'
'uid_type'=[skype, jabber, whatsapp]
'uid' --optional
'params'=array

2. spamer_worker:
'uid_type'=[skype, jabber, whatsapp]
'uid'
'service_short_name'
'message'

====================================
ЧИСТКА ЛОГОВ

rm -rf /home/skysiss/log/*
rm -rf /home/developer/log/*
rm -rf /home/lifestyle/log/*
rm -rf /home/office/log/*
rm -rf /home/www/web/log/*
rm -rf /home/www/api/log/*