# deploy
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](https://raw.githubusercontent.com/kberzinch/deploy/master/LICENSE.md) [![StyleCI badge](https://styleci.io/repos/43822640/shield)](https://styleci.io/repos/43822640)

This is a simple utility I cobbled together to trigger deployments from GitHub events. I use it to publish several repositories to my [DigitalOcean](https://m.do.co/c/3c14b82dc1b9) VPS (including itself!) It will optionally send a notification email when a deployment completes using PHP's ```mail()``` function.

## Initial Setup
This part is not strictly necessary - you can alternatively just download a zipped copy of this repo and extract it somewhere. (You do at least need to be able to serve ```pull.php```.)

0. Have a PHP-capable web server that serves ```pull.php``` at http://example.com/pull.php.
1. Follow the general [Per-Repository Setup](#per-repository-setup) instructions below to set up this repository.
2. Run ```bash pull.sh <repo-name>``` from within the local copy of this repository to do the initial deployment.

## Per-Repository Setup
1. Clone the repo you want to deploy to ```/var/github/<repo-name>```. Use some mechanism for saving your credentials (see [Notes](#notes)).
2. Create a folder ```/var/www/<repo-name>```.
3. Add ```http://example.com/pull.php``` as a webhook under the repository's settings, with content type ```application/json```, [some secure secret](https://www.random.org/bytes/), and set some events to trigger the webhook (probably ```push```).
4. Copy ```config.sample.php``` to ```config.php```. Open ```config.php``` and edit it per the instructions there.
5. Push some stuff to your repo and check the deliveries section under the webhook's settings. You should see some status messages followed by the output of ```git pull```.

## Notes
* Your web server needs to be able to write to both the local Git repo as well as the target directory. I typically change ownership to the web server's user.
* For simplicity's sake I clone from ```https://username:password@github.com/username/repo-name.git```. If you choose to go this route, [get a personal access token](https://github.com/settings/tokens) and use that in place of your password.
* This script will not delete files from the published copy - only add or replace. This is good enough for me, but it might not be for you.
