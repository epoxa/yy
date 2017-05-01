FROM bitnami/php-fpm:latest

RUN apt-get update && apt-get install -y git

RUN \
  cat /opt/bitnami/php/etc/php-fpm.d/www.conf.default | sed 's/;clear_env = no/clear_env = no/' > temp \
  && mv -f temp /opt/bitnami/php/etc/php-fpm.d/www.conf.default



