#!/bin/bash

docker run -d \
--name yy \
-e "WEBROOT=/var/www/html/default/www" \
-e "PUID=`id -u $USER`" \
-v "$(pwd)/../":/var/www/html \
-p 8080:80 -p 8443:443 \
richarvey/nginx-php-fpm:latest
