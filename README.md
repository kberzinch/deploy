# deploy
[![GitHub license](https://img.shields.io/github/license/kberzinch/deploy.svg?style=flat-square)](https://raw.githubusercontent.com/kberzinch/deploy/master/LICENSE.md) [![StyleCI badge](https://styleci.io/repos/43822640/shield)](https://styleci.io/repos/43822640)

This is a simple utility I cobbled together to trigger deployments from GitHub events. I use it to publish several repositories to my [DigitalOcean](https://m.do.co/c/3c14b82dc1b9) VPS (including itself!) It will optionally send a notification email when a deployment completes using PHP's built-in ```mail()``` function and/or run an additional PHP script after finishing.

## Initial Setup
0. Have a PHP-capable web server that serves ```pull.php``` at http://example.com/pull.php.
1. Copy ```config.sample.php``` to ```config.php```. Open ```config.php``` and edit it per the instructions there.
2. Create directories ```/var/github/``` and ```/var/www```. This is where the server's local git repositories and the deployed repositories will be, respectively.

## Per-Repository Setup
3. Add ```http://example.com/pull.php``` as a webhook under the repository's settings, with content type ```application/json```, [the secret you put in ```config.php```](https://www.random.org/bytes/), and set some events to trigger the webhook (probably ```push```).
5. Check the deliveries section under the webhook's settings. Webhook creation should trigger a ```ping``` event, so you should see some status messages followed by the output of ```git pull```.

## Notes
* Your web server needs to be able to write to ```/var/github/``` as well as ```/var/www```. I typically change ownership to the web server's user.
* This script will not delete files from the published copy - only add or replace. This is good enough for me, but it might not be for you.
