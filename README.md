# deploy.swampbotics.org

Deploys itself to [deploy.swampbotics.org](http://deploy.swampbotics.org). Works similarly for other repos without any code changes. Used as written to deploy most of [swampbotics.org](http://swampbotics.org) and subdomains.

## Setup
0. Have a PHP-capable web server.
1. Clone the repo you want to deploy with this system to ```/var/github/<repo-name>```. Use some mechanism for saving your credentials, such as cloning from ```https://username:password@github.com/username/repo-name.git```. **DO NOT** actually use your password! If you choose to go this route, [get a personal access token](https://github.com/settings/tokens) and use that.
2. Create a folder ```/var/www/<repo-name>```. Make sure your web server can write to it.
3. Add ```http(s)://example.com/pull.php``` as a webhook under the repository settings, with content type ```application/json```, [some secure secret](https://www.random.org/bytes/) (you'll need this later, too), and set only the ```push``` event to trigger the webhook. Set it to active if you want it to do things.
4. Edit ```config.php```. There are directions in the file.
5. You're done! Push some stuff to your repo and check the deliveries section under the webhook's settings. The script should send back the output of ```git pull```.