#!/bin/bash

if [ ! -d "/var/github/$1/" ]; then
echo "First-time setup..."
cd /var/github/
git clone https://${3}github.com/${2}.git
mkdir /var/www/$1
fi
cd /var/github/$1/
git pull
git archive master | tar -x -C /var/www/$1
echo "Done!"