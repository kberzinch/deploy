#!/bin/bash

if [ ! -d "/var/www/$1/" ]; then
cd /var/www/
git clone ${2} ${1}
git -c advice.detachedHead=false checkout ${3}
exit
fi
cd /var/www/$1/
git remote set-url origin ${2}
git fetch
git -c advice.detachedHead=false checkout ${3}
