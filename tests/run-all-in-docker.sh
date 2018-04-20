#!/usr/bin/env bash

cd $(cd $(dirname $0) && pwd)/..

sudo mkdir -p default/runtime/log && sudo chmod a+rwX -R default/runtime

docker-compose -f docker/docker-compose-tests.yml up -d
sleep 20
docker-compose -f docker/docker-compose-tests.yml exec -T php sh -c "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer"

# Make swap for limited memory os environment
#docker-compose -f docker/docker-compose-tests.yml exec -T php /bin/dd if=/dev/zero of=/var/swap.1 bs=1M count=1024
#docker-compose -f docker/docker-compose-tests.yml exec -T php chmod 600 /var/swap.1
#docker-compose -f docker/docker-compose-tests.yml exec -T php /sbin/mkswap /var/swap.1
#docker-compose -f docker/docker-compose-tests.yml exec -T php /sbin/swapon /var/swap.1

docker-compose -f docker/docker-compose-tests.yml exec -T php apt update
docker-compose -f docker/docker-compose-tests.yml exec -T php apt install unzip

docker-compose -f docker/docker-compose-tests.yml exec -T php docker-php-ext-install pcntl

docker-compose -f docker/docker-compose-tests.yml exec -T php composer install

docker-compose -f docker/docker-compose-tests.yml exec -T php vendor/bin/phpunit
