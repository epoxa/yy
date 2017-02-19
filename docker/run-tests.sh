#!/bin/bash

docker run --rm -it \
--name tests \
-e "YY_TEST_BROWSER=firefox" \
-e "YY_TEST_BASE_URL=http://172.17.0.1:80" \
-e "YY_TEST_SELENIUM_HOST=127.0.0.1" \
-e "YY_TEST_SELENIUM_PORT=4444" \
-v $(pwd)/../:/data \
million12/php-testing "
sed -e 's/user www/user $(`echo $USER`)/' /etc/nginx/nginx.conf > nginx.conf.tmp && mv nginx.conf.tmp /etc/nginx/nginx.conf && \
/data/vendor/phpunit/phpunit/phpunit -c /data/phpunit.xml
"
