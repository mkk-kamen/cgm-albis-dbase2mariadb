#!/bin/bash
current_time=$(date "+%Y-%m-%d-%H-%M-%S")
php /opt/cgm-albis-dbase2mariadb/scripts/dbf-import.php > /opt/cgm-albis-dbase2mariadb/log/import-$current_time.log 2>&1 &
