#!/bin/bash
sudo apt-get update
sudo apt-get -y install nginx
cat build/travis/etc/nginx/travis.nginx.conf | sed -e "s,\$DOCROOT,`pwd`," | sudo tee /etc/nginx/nginx.conf
sudo /etc/init.d/nginx restart
wget http://selenium-release.storage.googleapis.com/2.47/selenium-server-standalone-2.47.1.jar --quiet

