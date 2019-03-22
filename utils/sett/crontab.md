0 * * * *    root    sync; echo 3 > /proc/sys/vm/drop_caches >/dev/null 2>&1
0 0 * * *    root    /usr/sbin/ntpdate pool.ntp.org >/dev/null 2>&1
#0 0 * * *   root    /usr/bin/apt-get update >/dev/null 2>&1
#10 0 * * *  root    /usr/bin/apt-get -y upgrade >/dev/null 2>&1
15 * * * *   /bin/sh /home/skysiss/skysiss_stat.sh >/dev/null 2>&1
0 4 * * *    /sbin/shutdown -r now
# Mysql slave backup
1 0 * * *    /bin/sh /home/skysiss/backups/backup_all_dbs.sh
#Cleanup mysql backups older 7 days
0 0 * * *    /usr/bin/find /home/skysiss/backups/ -type f -name "*sql.gz" -mtime +7 -exec rm {} +

