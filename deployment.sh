#!/bin/bash

if [ ! -d "/var/www/$1/" ]; then
cd /var/www/
# ${2} contains sensitive information, so we will mask it.
# Yes this is cumbersome.
{ set +x; } 2>/dev/null
echo "+ git clone $(echo ${2} | sed -e "s/x-access-token:.*@/x-access-token:redacted@/g") ${1}"
git clone ${2} ${1}
set -x
git -c advice.detachedHead=false checkout ${3}
exit
fi
cd /var/www/$1/
{ set +x; } 2>/dev/null
echo "+ git remote set-url origin $(echo ${2} | sed -e "s/x-access-token:.*@/x-access-token:redacted@/g")"
git remote set-url origin ${2}
set -x
git fetch
git -c advice.detachedHead=false checkout ${3}
