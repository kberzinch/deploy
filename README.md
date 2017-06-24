# deploy
[![GitHub license](https://img.shields.io/github/license/kberzinch/deploy.svg?style=flat-square)](https://raw.githubusercontent.com/kberzinch/deploy/master/LICENSE.md) [![StyleCI badge](https://styleci.io/repos/43822640/shield)](https://styleci.io/repos/43822640)

This is a simple utility I cobbled together to trigger deployments from GitHub events. I use it to publish several repositories to my [DigitalOcean](https://m.do.co/c/3c14b82dc1b9) VPS (including itself!) It will optionally send a notification email when a deployment completes using PHP's built-in ```mail()``` function and/or run a shell script after finishing.

## Initial Setup
0. Have a PHP-capable web server that serves `deploy.php` at http://example.com/deploy.php.
1. Copy `config.sample.php` to `config.php`. Open `config.php` and edit it per the instructions there.
2. Ensure that your web server user can write to `/var/www/`.

## Per-Repository Setup
3. Add `http://example.com/deploy.php` as a webhook under the repository's settings, with content type `application/json`, [the secret you put in `config.php`](https://randomkeygen.com/), and set some events to trigger the webhook. It works best if you just set `deployment` and then use the [GitHub Auto-Deployment](https://developer.github.com/v3/guides/automating-deployments-to-integrators/#sending-deployments-whenever-you-push-to-a-repository) service to trigger `deployment` events.
5. Check the deliveries section under the webhook's settings. Webhook creation should trigger a ```ping``` event, so you should see a preamble followed by the output of ```git clone```.
