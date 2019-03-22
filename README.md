__DISCLAIMER__: pardon, this is very old project, and I have no willing to correct it and translate as it is no more supported.



1. Open cmd and 'cd' to git project
cd path/to/project/

Download composer
php -r "readfile('https://getcomposer.org/installer');" | php

Run composer
php composer.phar update

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

====================================
IM:

debian 7.4 wheezy
php 5.5.11
mysql-client 5.5
nginx 1.6.0-1
mysql 5.6.17
memcache 1.4.13
rabbitMQ 2.8.4
skype 4.2.0.13
phpMyAdmin 4.1.13
openssl 1.0.1g

[update Debian from 7 to 7.4]
apt-get dist-upgrade
�� �㦭� ᬮ���� ����� ��⥬� ����㠫���樨 �ᯮ������

[setup]
hostname="im.skysiss.com"

>>nano /etc/network/interfaces
# The loopback network interface
auto lo
iface lo inet loopback

# The primary network interface
#allow-hotplug eth0
#NetworkManager#iface eth0 inet dhcp
#auto eth0

auto eth1
iface eth1 inet static
address 192.168.56.2
netmask 255.255.255.0
network 192.168.56.0
gateway 192.168.56.1
broadcast 192.168.56.255

>>nano /etc/resolv.conf
nameserver 8.8.8.8

>>nano /etc/sysctl.conf
net.ipv4.conf.all.accept_redirects=0
net.ipv4.conf.all.secure_redirects=0
net.ipv4.conf.all.send_redirects=0
net.ipv4.tcp_max_orphans=65536
net.ipv4.tcp_fin_timeout=10
net.ipv4.tcp_keepalive_time=1800
net.ipv4.tcp_keepalive_intvl=15
net.ipv4.tcp_keepalive_probes=5
net.ipv4.tcp_max_syn_backlog=4096
net.ipv4.tcp_synack_retries=1
net.ipv4.tcp_mem=50576   64768   98152
net.ipv4.tcp_rmem=4096 87380 16777216
net.ipv4.tcp_wmem=4096 65536 16777216
net.ipv4.tcp_orphan_retries=0
net.ipv4.tcp_syncookies=0
net.ipv4.netfilter.ip_conntrack_max=1048576
net.ipv4.tcp_timestamps=1
net.ipv4.tcp_sack=1
net.ipv4.tcp_congestion_control=htcp
net.ipv4.tcp_no_metrics_save=1
net.ipv4.route.flush=1
net.ipv4.conf.all.rp_filter=1
net.ipv4.conf.lo.rp_filter=1
net.ipv4.conf.eth0.rp_filter=1
net.ipv4.conf.default.rp_filter=1
net.ipv4.conf.all.accept_source_route=0
net.ipv4.conf.lo.accept_source_route=0
net.ipv4.conf.eth0.accept_source_route=0
net.ipv4.conf.default.accept_source_route=0
net.ipv4.ip_local_port_range=1024 65535
net.ipv4.tcp_tw_reuse=1
net.ipv4.tcp_window_scaling=1
net.ipv4.tcp_rfc1337=1
net.ipv4.ip_forward=0
net.ipv4.icmp_echo_ignore_broadcasts=1
net.ipv4.icmp_echo_ignore_all=1
net.ipv4.icmp_ignore_bogus_error_responses=1
net.core.somaxconn=15000
net.core.netdev_max_backlog=1000
net.core.rmem_default=65536
net.core.wmem_default=65536
net.core.rmem_max=16777216
net.core.wmem_max=16777216

shutdown -r now

[install openssl]
apt-get install build-essential
cp /usr/bin/openssl /usr/bin/openssl.orig
cd /tmp
wget http://www.openssl.org/source/openssl-1.0.1g.tar.gz
tar -xvzf openssl-1.0.1g.tar.gz
cd openssl-1.0.1g
./config no-shared no-threads
make depend
make
make install

[install all]
apt-get install htop libmcrypt-dev curl openssl libxml2-dev libcurl4-openssl-dev libjpeg-dev libpng-dev libpcre3-dev libc-client-dev libxslt-dev
apt-get install libdbus-1-dev autoconf
apt-get install libaio1
apt-get install memcached subversion vnc4server postfix rabbitmq-server
--libssl-dev nginx mysql-server mysql-client

*if amd64 install next:
dpkg --add-architecture i386
apt-get update
apt-get -f upgrade

[install nginx]
cd /tmp
#wget http://ftp.us.debian.org/debian/pool/main/o/openssl/libssl0.9.8_0.9.8o-4squeeze14_amd64.deb
#dpkg -i libssl0.9.8_0.9.8o-4squeeze14_amd64.deb

wget -O key http://nginx.org/keys/nginx_signing.key && sudo apt-key add key && sudo rm -f key

>>nano /etc/apt/sources.list
deb http://nginx.org/packages/debian/ jessie nginx
deb-src http://nginx.org/packages/debian/ jessie nginx

apt-get update
apt-get install nginx

[install php fpm]
cd /tmp
wget http://nl1.php.net/distributions/php-5.5.11.tar.gz
tar -zxvf php-5.5.11.tar.gz

cd php-5.5.11
./configure --enable-fpm --with-curl --with-gd --with-jpeg-dir --with-png-dir --with-imap --with-pear --with-kerberos --with-imap-ssl --with-zlib --with-mcrypt --with-mhash --with-mysql --with-mysqli --with-pdo-mysql --with-openssl --with-gettext --with-xsl --with-fpm-user=www-data --with-fpm-group=www-data --enable-zip --enable-bcmath --enable-mbstring --enable-sockets --enable-shmop --enable-dba --enable-sysvmsg --enable-sysvsem --enable-sysvshm  --enable-opcache --enable-pcntl

make && make install

cp sapi/fpm/init.d.php-fpm /etc/init.d/php-fpm
chmod +x /etc/init.d/php-fpm
mkdir /usr/local/etc/php
cp php.ini-development /usr/local/etc/php/php.ini
cp sapi/fpm/php-fpm.conf /usr/local/etc/php-fpm.conf
ln -s /usr/local/etc/php/php.ini /usr/local/lib/

>>nano /etc/init.d/php-fpm
php_fpm_PID=/var/run/php-fpm.pid

update-rc.d -f php-fpm defaults

>>nano /usr/local/etc/php-fpm.conf
user = www
group = www
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 35
listen = /var/run/php5-fpm.sock

adduser www
gpasswd -a www www

pecl install dbus-beta
echo extension=/usr/local/lib/php/extensions/no-debug-non-zts-20121212/dbus.so >> /usr/local/etc/php/php.ini


***OLD
cd /tmp
pecl install memcache
#pecl download memcache
#tar xvzf memcache-2.2.7.tgz
#cd memcache-2.2.7/
#phpize
#./configure
#make & make install
echo extension=/usr/local/lib/php/extensions/no-debug-non-zts-20121212/memcache.so >> /usr/local/etc/php/php.ini
echo zend_extension=/usr/local/lib/php/extensions/no-debug-non-zts-20121212/opcache.so >> /usr/local/etc/php/php.ini
***EOF OLD

opcache ����砥��� � ���䨣� php (�⠢���� � ����⠬� ��� � �� 㦥)
�� �⠢�� memcache* �� pecl
�⠢�� �����
apt-get install php5-memcached � ��
ॡ�⠥� ᠩ� � ��� (!�������� �� �ࢥ� ������ ���� ���㠫쭠� ����� ����)

/etc/init.d/php-fpm restart

[web & api]
cd /home/www
mkdir web
mkdir api

>>nano svn_update_web

>>nano svn_update_api

[nginx]
mkdir /etc/nginx/ssl
cd /etc/nginx/ssl
openssl dhparam -out dhparam.pem 2048

>>nano /etc/nginx/nginx.conf
#http{
#server_names_hash_bucket_size  64;

>>nano /etc/nginx/ssl.conf

>>nano web.conf

>>nano api.conf

ln -s /usr/share/phpmyadmin /usr/share/pms

/etc/init.d/nginx restart

[phpmyadmin]
#apt-get install phpmyadmin
#apt-get remove apache*

cd /tmp
#mv phpmyadmin phpmyadmin.bak
#mkdir /usr/share/phpmyadmin
#cd /usr/share/phpmyadmin
wget http://netcologne.dl.sourceforge.net/project/phpmyadmin/phpMyAdmin/4.1.13/phpMyAdmin-4.1.13-english.tar.gz
tar -zxvf phpMyAdmin-4.1.13-english.tar.gz
mv /tmp/phpMyAdmin-4.1.13-english /usr/share/phpmyadmin
#rm -rf phpmyadmin
#mv /tmp/phpmyadmin/ /usr/share/phpmyadmin
/etc/init.d/nginx restart

[install mysql]
cd /tmp
groupadd mysql
useradd -r -g mysql mysql
wget http://cdn.mysql.com/Downloads/MySQL-5.6/mysql-5.6.17-debian6.0-x86_64.deb
dpkg -i mysql-5.6.17-debian6.0-x86_64.deb
cd /usr/local
ln -s /opt/mysql/server-5.6 mysql
cd mysql
rm -rf my.cnf
scripts/mysql_install_db --user=mysql
chown -R root .
chown -R mysql data
cp support-files/mysql.server /etc/init.d/mysql

>>nano /etc/my.cnf
innodb_buffer_pool_size = 256M
query_cache_size = 32M
query_cache_limit = 1M

>>nano /etc/init.d/mysql
basedir=/usr/local/mysql
datadir=/usr/local/mysql/data

cp support-files/my-default.cnf /etc/my.cnf
>>nano /etc/my.cnf
[mysqld]
#skip-networking
bind-address = 127.0.0.1
max_allowed_packet=64M
socket=/tmp/mysql.sock
[client]
socket=/tmp/mysql.sock

alias mysql=/opt/mysql/server-5.6/bin/mysql
alias mysqladmin=/opt/mysql/server-5.6/bin/mysqladmin

/etc/init.d/mysql restart

/opt/mysql/server-5.6/bin/mysql_secure_installation
update-rc.d mysql defaults

[mysql import timezones from os freebsd]
/opt/mysql/server-5.6/bin/mysql_tzinfo_to_sql /usr/share/zoneinfo | /opt/mysql/server-5.6/bin/mysql -u root -p mysql

[mysql setting]
delete all unnecessary users and databases

mysql -u root -p mysql
CREATE USER 'skysiss'@'%' IDENTIFIED BY '%PASSWORD%';GRANT USAGE ON *.* TO 'skysiss'@'%' IDENTIFIED BY '%PASSWORD%' WITH MAX_QUERIES_PER_HOUR 0 MAX_CONNECTIONS_PER_HOUR 0 MAX_UPDATES_PER_HOUR 0 MAX_USER_CONNECTIONS 0;
GRANT SELECT, INSERT, UPDATE, DELETE ON `skysiss`.* TO 'skysiss'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON `skysiss\_sessions`.* TO 'skysiss'@'%';

[UPDATE PAKAGES]
apt-get update
apt-get upgrade
apt-get purge exim4
apt-get purge apache*

[disable root]
nano /etc/ssh/sshd_config
PermitRootLogin no

/etc/init.d/ssh restart

[VNC server]
mkdir -p /etc/vncserver
>>nano /etc/vncserver/vncservers.conf
VNCSERVERS="1:skysiss 2:calc 3:clock 4:currency 5:magic8 6:weather 7:xmess"
VNCSERVERARGS[1]="-geometry 1024x768"
VNCSERVERARGS[2]="-geometry 1024x768"
VNCSERVERARGS[3]="-geometry 1024x768"
VNCSERVERARGS[4]="-geometry 1024x768"
VNCSERVERARGS[5]="-geometry 1024x768"
VNCSERVERARGS[6]="-geometry 1024x768"
VNCSERVERARGS[7]="-geometry 1024x768"

>>nano /etc/init.d/vncserver
#!/bin/bash
### BEGIN INIT INFO
# Provides:          vncserver
# Required-Start:    $syslog
# Required-Stop:     $syslog
# Default-Start:     2 3 4 5
# Default-Stop:      0 1 6
# Short-Description: vnc server
# Description:
#
### END INIT INFO

unset VNCSERVERARGS
VNCSERVERS=""
[ -f /etc/vncserver/vncservers.conf ] && . /etc/vncserver/vncservers.conf
prog=$"VNC server"

start() {
 . /lib/lsb/init-functions
 REQ_USER=$2
 echo -n $"Starting $prog: "
 ulimit -S -c 0 >/dev/null 2>&1
 RETVAL=0
 for display in ${VNCSERVERS}
 do
 export USER="${display##*:}"
 if test -z "${REQ_USER}" -o "${REQ_USER}" == ${USER} ; then
 echo -n "${display} "
 unset BASH_ENV ENV
 DISP="${display%%:*}"
 export VNCUSERARGS="${VNCSERVERARGS[${DISP}]}"
 su ${USER} -c "cd ~${USER} && [ -f .vnc/passwd ] && vncserver :${DISP} ${VNCUSERARGS}"
 fi
 done
}

stop() {
 . /lib/lsb/init-functions
 REQ_USER=$2
 echo -n $"Shutting down VNCServer: "
 for display in ${VNCSERVERS}
 do
 export USER="${display##*:}"
 if test -z "${REQ_USER}" -o "${REQ_USER}" == ${USER} ; then
 echo -n "${display} "
 unset BASH_ENV ENV
 export USER="${display##*:}"
 su ${USER} -c "vncserver -kill :${display%%:*}" >/dev/null 2>&1
 fi
 done
 echo -e "\n"
 echo "VNCServer Stopped"
}

case "$1" in
start)
start $@
;;
stop)
stop $@
;;
restart|reload)
stop $@
sleep 3
start $@
;;
condrestart)
if [ -f /var/lock/subsys/vncserver ]; then
stop $@
sleep 3
start $@
fi
;;
status)
status Xvnc
;;
*)
echo $"Usage: $0 {start|stop|restart|condrestart|status}"
exit 1
esac


chmod +x /etc/init.d/vncserver
update-rc.d vncserver defaults 99

under each user do:

vncpasswd;vncserver :1;vncserver -kill :1;
nano .vnc/xstartup

#!/bin/sh

# Uncomment the following two lines for normal desktop:
unset SESSION_MANAGER
# exec /etc/X11/xinit/xinitrc
gnome-session &

[ -x /etc/vnc/xstartup ] && exec /etc/vnc/xstartup
[ -r $HOME/.Xresources ] && xrdb $HOME/.Xresources
xsetroot -solid grey
vncconfig -iconic &
x-terminal-emulator -geometry 80x24+10+10 -ls -title "$VNCDESKTOP Desktop" &
# x-window-manager &

vncserver :1

now you can connect as im.skysiss.com:1

service vncserver start

[Skype]
cd /tmp
wget http://download.skype.com/linux/skype-debian_4.3.0.37-1_i386.deb
dpkg -i skype-debian_4.3.0.37-1_i386.deb
apt-get -f install

[checkout project]
>>nano svn_update_assist

cd library/
wget https://packages.zendframework.com/releases/ZendFramework-1.11.11/ZendFramework-1.11.11-minimal.tar.gz
tar -zxvf ZendFramework-1.11.11-minimal.tar.gz
rm -rf Zend
mv ZendFramework-1.11.11-minimal/library/Zend/ ./
rm -rf ZendFramework-1.11.11-minimal.tar.gz
rm -rf ZendFramework-1.11.11-minimal

>>nano utils/assistant/config.ini

>>nano /etc/hosts
127.0.0.1 api.skysiss.com
192.168.56.2 skysiss.com
192.168.56.2 im.skysiss.com

chmod 777 /home/www/web/public/mmc6/Temp/
chmod 777 /home/www/web/cache
chmod 777 /home/www/web/log
chmod 777 /home/www/api/log
chmod -R 777 /home/www/web/public/cache/
chmod -R 777 /home/www/web/cache/

cd /usr/local/etc/php
>>nano php.ini
expose_php = Off
rename PHPSESSID to SKYSISS_SESS_ID

>>nano /etc/memcached.conf
-m 256
-l 127.0.0.1

/etc/init.d/memcached restart

check it
netstat -tap | grep memcached

[rabbitMQ]
rabbitmqctl change_password guest %PASSWORD%

enable monitoring
rabbitmq-plugins enable rabbitmq_management

rabbitmq-server
/etc/init.d/rabbitmq-server restart

[JABBER]
apt-get install erlang ejabberd

������ ssl ���䨪�� ��� ����
cd /etc/ejabberd
openssl req -new -x509 -nodes -newkey rsa:1024 -days 3650 \
-keyout privkey.pem -out server.pem -subj \
"/C=XX/ST=XX/L=XX/O=XX/OU=XX/CN=im.skysiss.com/emailAddress="support@im.skysiss.com

cat privkey.pem >> server.pem
rm privkey.pem
mv server.pem ssl.pem

।��⨬
nano /etc/ejabberd/ejabberd.cfg
�⠢��
{loglevel, 4}.
�
%% Admin user
{acl, admin, {user, "_skysiss", "localhost"}}.
{acl, admin, {user, "_skysiss", "im.skysiss.com"}}.
%% Hostname
{hosts, ["im.skysiss.com", "localhost"]}.

�饬 {5222, ejabberd_c2s,
।����㥬 �⮡� �뫮 ⠪
{certfile, "/etc/ejabberd/ssl.pem"},
{max_fsm_queue, 500},
{access, c2s}, zlib, starttls,
{shaper, c2s_shaper},
{max_stanza_size, 65536}

%%%�饬
{5223, ejabberd_c2s, [
।����㥬 �⮡� �뫮 ⠪
{access, c2s},
{shaper, c2s_shaper},
{certfile, "/etc/ejabberd/ssl.pem"}, tls,
{max_stanza_size, 65536}
]},

{5269, ejabberd_s2s_in, [
{shaper, s2s_shaper},
{max_stanza_size, 131072}
%%%

������塞 � �⮧����
echo "ejabberd_enable="YES"" >> /etc/rc.conf

>>nano /etc/ejabberd/inetrc
���
��ਠ�� 1
{file, hosts, "/etc/hosts"}.
{file, resolv, "/etc/resolv.conf"}.
% ᭠砫� �饬 ����� � hosts, � ��⥬ ���頥��� � DNS
{lookup, [file, dns]}.
��ਠ�� ࠡ�稩
{lookup,["file","native"]}.
{host,{127,0,0,1}, ["localhost","hostalias"]}.
{file, resolv, "/etc/resolv.conf"}.

add interaction with other xmpp servers
{s2s_use_starttls, true}.
{s2s_certfile, "/ejabberd-2.0.1/conf/server.pem"}.
{s2s_default_policy, allow}.

add a SRV record to your DNS for your chat server's FQDN (Fully Qualified Domain Name) (https://www.jms1.net/jabberd2/srv.shtml)

����㥬
/etc/init.d/ejabberd restart

�஢��塞
ejabberdctl status

ejabberdctl register _skysiss im.skysiss.com %PASSWORD%
ejabberdctl register _skysiss.xmess im.skysiss.com %PASSWORD%
ejabberdctl register _skysiss.office im.skysiss.com %PASSWORD%
ejabberdctl register _skysiss.lifestyle im.skysiss.com %PASSWORD%
ejabberdctl register _skysiss.dev im.skysiss.com %PASSWORD%

ejabberdctl register yury_nechayeu im.skysiss.com %PASSWORD%

ejabberdctl unregister tester hblchat.morphia.com


[SECURITY]
# ����뢠�� ����砫쭮 ��� (�.�. ����砫쭮 �� �� �� ࠧ�襭� - ����饭�):
iptables -P INPUT DROP
iptables -P OUTPUT DROP
iptables -P FORWARD DROP
# ����ﭨ� ESTABLISHED ������ � ⮬, �� �� �� ���� ����� � ᮥ�������.
# �ய�᪠�� �� 㦥 ���樨஢���� ᮥ�������, � ⠪�� ���୨� �� ���
iptables -A INPUT -p all -m state --state ESTABLISHED,RELATED -j ACCEPT
# �ய�᪠�� ����, � ⠪ �� 㦥 ���樨஢���� � �� ���୨� ᮥ�������
iptables -A OUTPUT -p all -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT
# ������� �ࢠन�� ��� �����, � ⠪ �� 㦥 ���樨஢����� � �� ���୨� ᮥ�������
iptables -A FORWARD -p all -m state --state NEW,ESTABLISHED,RELATED -j ACCEPT

#memcache only available
iptables -A INPUT -s 127.0.0.1 -p tcp --dport 11211 -j ACCEPT
iptables -A INPUT -s 192.168.56.3 -p tcp --dport 11211 -j ACCEPT
====================================

check php.ini for opcache enabled (do not enable for CLI)
>>nano /usr/local/etc/php/php.ini
/etc/init.d/php-fpm restart

[mysql backup]
mkdir /tmp/automysqlbackup
cd /tmp/automysqlbackup
wget http://kent.dl.sourceforge.net/project/automysqlbackup/AutoMySQLBackup/AutoMySQLBackup%20VER%203.0/automysqlbackup-v3.0_rc6.tar.gz
tar -zxvf automysqlbackup-v3.0_rc6.tar.gz
./install.sh

>>nano /etc/automysqlbackup/myserver.conf
CONFIG_mysql_dump_username='root'
CONFIG_mysql_dump_password='%PASSWORD%'
CONFIG_backup_dir='/home/skysiss/mysqlbackups/'
CONFIG_rotation_daily=6
CONFIG_mailcontent='files'
CONFIG_mailcontent='stdout'
CONFIG_mail_address='yury_nechayeu@epam.com'

automysqlbackup /etc/automysqlbackup/myserver.conf

crontab -e
#every day at 5:30 am
30 5 * * * /usr/local/bin/automysqlbackup /etc/automysqlbackup/myserver.conf

[skype settings]
disable notifications
disable AWAY mode
disable history

[postfix]
>>nano /etc/postfix/main.cf
myhostname = skysiss.com

/etc/init.d/postfix reload

>>nano /etc/postfix/virtual
support@skysiss.com cryomi

postmap /etc/postfix/virtual
/etc/init.d/postfix reload

[time]
apt-get install ntpdate
ntpdate pool.ntp.org

�� ������
apt-get autoremove
==================================
��� ஢ � ��⮫���� (ᤥ���� �।���⥫쭮 snapshot)
http://forums.debian.net/viewtopic.php?f=5&t=84164
>>nano /etc/inittab
13:2345:respawn:/bin/login -f skysiss tty13 </dev/tty13 >/dev/tty13 2>&1
14:2345:respawn:/bin/login -f lifestyle tty14 </dev/tty14 >/dev/tty14 2>&1
15:2345:respawn:/bin/login -f developer tty15 </dev/tty15 >/dev/tty15 2>&1
16:2345:respawn:/bin/login -f office tty16 </dev/tty16 >/dev/tty16 2>&1

>>nano /etc/profile
sleep 15
[ `tty` == '/dev/tty13' ] && startx -- :1
sleep 15
[ `tty` == '/dev/tty14' ] && startx -- :2
sleep 15
[ `tty` == '/dev/tty15' ] && startx -- :3
sleep 15
[ `tty` == '/dev/tty16' ] && startx -- :4

����� ��᫥�� � background
1. �ࠧ� ५������ �:
pkill -KILL -u developer
pkill -KILL -u office
pkill -KILL -u lifestyle
pkill -KILL -u skysiss

2.
who
killall -u developer
killall -u office
killall -u lifestyle
killall -u skysiss

/bin/login -f skysiss tty13 </dev/tty13 >/dev/tty13 2>&1 &
/bin/login -f lifestyle tty14 </dev/tty14 >/dev/tty14 2>&1 &
/bin/login -f developer tty15 </dev/tty15 >/dev/tty15 2>&1 &
/bin/login -f office tty16 </dev/tty16 >/dev/tty16 2>&1 &

��ᬮ���� background
jobs -l

1==2

**OLD �ਯ�� ��� ������� php �ਯ⮢ ����� � D:\!For_mec\-Biz\#skysiss\#autostart
>>nano /etc/gdm3/PostSession/Default
killall -9 -u "$USER" php

�ਯ�� ��� ������� php �ਯ⮢ ����� � D:\!For_mec\-Biz\#skysiss\#autostart
==================================
᫠�� �뫮 ��᫥ ॡ��

cd /etc/init.d/
apt-get install chkconfig
???apt-get install daemon

>>nano notify_restart
#!/bin/bash
#
# emailstartstop    Send an email on server startup and shutdown.
#
# chkconfig:    2345 99 01
# description:  Send an email on server startup and shutdown.

EMAIL="yury_nechayeu@epam.com"
STARTSUBJ=`hostname`" started on "`date`
STARTBODY="Just letting you know that server "`hostname`" has started on "`date`
STOPSUBJ=`hostname`" shutdown on "`date`
STOPBODY="Just letting you know that server "`hostname`" has shutdown on "`date`
lockfile=/var/lock/subsys/emailstartstop

# Send email on startup
start() {
    echo -n $"Sending email on startup: "

    echo "${STARTBODY}" | mail -s "${STARTSUBJ}" ${EMAIL}
    RETVAL=$?
    echo
    [ $RETVAL = 0 ] && touch $lockfile
    return 0
}

# Send email on shutdown
stop() {
    echo -n "Sending email on shutdown: "

    echo "${STOPBODY}" | mail -s "${STOPSUBJ}" ${EMAIL}
    RETVAL=$?
    echo
    [ $RETVAL = 0 ] && rm -f $lockfile
    return 0
}

# See how we were called.
case "$1" in
    start)
        start
        ;;
    stop)
        stop
        ;;
    *)
        echo $"Usage: $prog {start|stop}"
        exit 2
esac
exit ${RETVAL}


chmod u+x notify_restart
chkconfig --add notify_restart
>>reboot
==================================
��祪��� mail root
mail
d *
==================================
�� ����
apt-get autoremove
reboot

�� ࠢ�� ���� ����� �⠢���
apt-get install gnome
==================================
delete all logs

rm -rf /var/www/api/log/*
rm -rf /var/www/web/log/*
rm -rf /home/skysiss/log/*

==================================
LETSENCRYPT

apt-get install vim
apt-get update && apt-get dist-upgrade

cd /opt && git clone https://github.com/letsencrypt/letsencrypt
/opt/letsencrypt/letsencrypt-auto --agree-tos --renew-by-default --standalone --standalone-supported-challenges http-01 --http-01-port 9999 --server https://acme-v01.api.letsencrypt.org/directory certonly -d skysiss.com -d im.skysiss.com -d api.skysiss.com
mv /etc/letsencrypt/live/skysiss.com /etc/letsencrypt/live/skysiss.com-0001


cd /etc/ejabberd && wget https://letsencrypt.org/certs/isrgrootx1.pem.txt
mv /etc/ejabberd/isrgrootx1.pem.txt /etc/ejabberd/ca.crt
cat /etc/letsencrypt/live/skysiss.com-0001/privkey.pem /etc/letsencrypt/live/skysiss.com-0001/fullchain.pem /etc/ejabberd/ca.crt >> /etc/ejabberd/ejabberd.pem

