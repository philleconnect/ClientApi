#!/bin/bash

# Function for a clean shutdown of the container
function shutdown {
    kill -TERM "$NGINX_PROCESS" 2>/dev/null
    exit
}
trap shutdown SIGTERM

# start services:
service php7.3-fpm start

# store envvars
sed -i "s|REPLACE_MYSQL_USER|$MYSQL_USER|g" /var/www/html/config.php
sed -i "s|REPLACE_MYSQL_PASSWORD|$MYSQL_PASSWORD|g" /var/www/html/config.php
sed -i "s|REPLACE_MYSQL_DATABASE|$MYSQL_DATABASE|g" /var/www/html/config.php
sed -i "s|REPLACE_GLOBAL_PASSWORD|$GLOBAL_PASSWORD|g" /var/www/html/config.php

# generate https key and certificate
if [ ! -f /etc/clientapi/privkey.pem ]; then
    openssl genrsa -out /etc/clientapi/privkey.pem 2048
    openssl req -new -x509 -key /etc/clientapi/privkey.pem -out /etc/clientapi/cacert.pem -days 36500 -subj "/C=DE/ST=Germany/L=Germany/O=SchoolConnect/OU=Schulserver/CN=example.com"
fi
cp /etc/clientapi/privkey.pem /etc/nginx/privkey.pem
cp /etc/clientapi/cacert.pem /etc/nginx/cacert.pem

nginx -g 'daemon off;' &
NGINX_PROCESS=$!
wait $NGINX_PROCESS
