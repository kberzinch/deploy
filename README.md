# deploy
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](https://raw.githubusercontent.com/kberzinch/deploy/master/LICENSE.md) [![StyleCI badge](https://styleci.io/repos/43822640/shield)](https://styleci.io/repos/43822640)

This is a simple utility I cobbled together to trigger deployments from GitHub events. I use it to publish several repositories to my [DigitalOcean](https://m.do.co/c/3c14b82dc1b9) VPS (including itself!)

## Setup
0. Have a PHP-capable web server.
1. Clone the repo you want to deploy to ```/var/github/<repo-name>```. Use some mechanism for saving your credentials. For <abbr title="laziness'">simplicity's</abbr> sake I clone from ```https://username:password@github.com/username/repo-name.git```. **DO NOT** actually use your password! If you choose to go this route, [get a personal access token](https://github.com/settings/tokens) and use that.
2. Create a folder ```/var/www/<repo-name>```. Make sure your web server can write to it.
3. Add ```http(s)://example.com/pull.php``` as a webhook under the repository's settings, with content type ```application/json```, [some secure secret](https://www.random.org/bytes/) (you'll need this later, too), and set  some events to trigger the webhook (probably ```push```). Set it to active if you want it to do things.
4. Copy ```config.sample.php``` to ```config.php```. Add the secret to ```config.php```.
5. You're done! Push some stuff to your repo and check the deliveries section under the webhook's settings. The script should send back the output of ```git pull```.

**Note that this script will not delete files, only add or replace.**