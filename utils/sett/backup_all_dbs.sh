#!bin/bash
SLAVEUSER=$'root'
SLAVEPASS=$'%PASSWORD%'

/usr/bin/mysqldump -uroot -p%PASSWORD% --ignore-table=mysql.event --all-databases | gzip -c > /home/skysiss/backups/$(date +%F)_all_databases.sql.gz
