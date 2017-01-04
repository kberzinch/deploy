#!/bin/bash

if [ ! -d "/var/www/$1/" ]; then
echo "First-time setup..."
cd /var/www/
git clone https://${3}github.com/${2}.git
fi
cd /var/www/$1/
git pull
echo "Done!"
