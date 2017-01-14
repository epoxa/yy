#!/bin/bash
sudo apt-get update
sudo apt-get -y install nginx
cat build/travis/etc/nginx/travis.nginx.conf | sed -e "s,\$DOCROOT,`pwd`," | sudo tee /etc/nginx/nginx.conf
cat /etc/nginx/nginx.conf
sudo /etc/init.d/nginx restart
curl -sSL https://raw.githubusercontent.com/codeship/scripts/master/packages/selenium_server.sh | bash -s
