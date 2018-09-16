# deploy
[![GitHub license](https://img.shields.io/github/license/kberzinch/deploy.svg?style=flat-square)](https://raw.githubusercontent.com/kberzinch/deploy/master/LICENSE.md) [![StyleCI badge](https://styleci.io/repos/43822640/shield)](https://styleci.io/repos/43822640)

This is a toolkit for triggering deployments to on-premises servers from GitHub events or Slack slash commands. It supports pulling repositories on GitHub.com as well as GitHub Enterprise instances from the same installation, and accepting Slack slash commands from multiple workspaces and channels.

## Initial server setup
1. Clone this repository to your server and set up a PHP web server.
2. Run `composer install` to install dependencies.
3. Validate by visiting `/github` - you should get a signature verification failure.
4. Copy `config.sample.php` to `config.php`.
5. Generate the value for `$webhook_secret`.
6. If you'd like to get emails, set `$email_from` and `$email_to`. By default, you will receive emails any time a deployment runs, although you can opt to only get failure emails by setting `$always_email` to `false`.
7. Leave the rest, we'll come back in a bit.

## GitHub App setup
Most likely, you only want your GitHub App to be installed on the account or organization that owns it. Be sure to create the app from this context to ensure that other users can't install it.

1. Go to your account/organization settings on GitHub.com/your GitHub Enterprise instance, then navigate to Developer settings > GitHub Apps > New GitHub App. Most of the form can be filled out as you see fit, but these are the required configuration options.
  * Set the **Webhook URL** to https://example.com/github.
  * Set the **Webhook secret** to the same value you configured earlier.
  * Required permissions for full functionality (feel free to adjust)
    * **Repository contents:** Read
    * **Deployments:** Read & write
    * **Commit statuses:** Read
  * Required event subscriptions for full functionality (feel free to adjust)
    * **Push**
    * **Deployment**
    * **Status**
  * I recommend only allowing the GitHub App to be installed on the owning account.
2. This is a good point to add a logo to your App if you'd like. It will be shown in Slack if you have deployment events sent there using the [GitHub app for Slack](https://slack.github.com/). (If you're on GitHub Enterprise, you can use the legacy GitHub app, but it won't show the App logo.)
3. Generate a private key and store it somewhere on your server (not accessible from the Internet!) Set its location in `config.php` in the `$private_key` array, keyed by the GitHub where the App is registered.
4. Get the ID under the "About" section and set it in `config.php` in the `$app_id` array, keyed by the GitHub where the App is registered.
5. Go to the **Advanced** tab and check your first webhook (at the bottom of the list). If everything is set up correctly, the response should be 200 with a message body that says "Hello GitHub!"

### Installing the GitHub App
1. Go to the **Install** tab and click "Install" next to your account.
2. Choose whether to set up the app for all repositories on the account, or a subset. You can change this later, and the App won't do anything for a repository unless it's explicitly configured.

## Configuring a repository
You can add lines as shown below to `config.php` to configure the app to handle GitHub events for a repository.

```php
$repositories['deploy']['push']['master'] = 'production';
```

The first key is the **repository**, the second key is the **event** (either `push` or `status`, if you have CI or similar that reports commit statuses), and the third key is the **branch**. The value is the **environment** to deploy.

## Slack slash command setup
Slack does not provide the same event and authorization detail as GitHub events, so this configuration is somewhat convoluted.

### Add the integration
1. Visit https://my.slack.com/apps/A0F82E8CA-slash-commands and add a configuration.
2. Choose a command - I suggest `/deploy`.
3. Set the **URL** to https://example.com/slack.
4. Leave the **Method** as `POST`.
5. Make a note of the **Token** - you'll need it later.
6. The rest of the configuration is up to you. Note that this integration generally won't post messages, although errors will be shown to the user with this identity. With that in mind, consider adding a logo and name to match your GitHub App.
7. Try your new slash command somewhere, and verify you get a response with team and user IDs.

### `config.php` changes
* Set `$slack_token` to the token from the integration configuration. Change the key to your team ID.
* Set `$slack_owner_id` to your user ID. Change the key to your team ID.
* Add authorized users to the `$slack_authorized_users` array.
* Set `$slack_gh_org` to the GitHub account or organization that owns your repositories. Note that this must match across GitHub.com and GitHub Enterprise if you are using both.
* The `$which_github` array maps repositories to the GitHub instance where they are hosted. You must add an entry for all repositories that will be deployed from Slack. You must include the account or organization name here.
* The `$environments` array is the list of environments that are valid for all repositories.

Add entries as shown below to authorize deployments for specific repositories from channels.

```php
$slack_channel_repos['TXXXXXXXX']['CXXXXXXXX'] = [''];
```

The first key is the **team ID**, the second key is the **channel**, and the value is an array of **repositories** that can be deployed from this channel. Do not specify the account or organization here.

### Using the slash command
I've made an effort to provide good error handling and reporting. In general, the Slack handler will provide detailed information if the input is invalid or there was an error processing the request.

#### /deploy [repository] [git ref] [environment]
Use this when more than one repository can be deployed from a channel. The repository must only be the name of the repository - do not include the account or organization name here. The git ref may be any valid git identifier, such as a commit checksum, branch name, or tag. The environment must be one listed in the `$environments` array in `config.php`.

#### /deploy [git ref] [environment]
Use this when only one repository can be deployed from a channel. The git ref may be any valid git identifier, such as a commit checksum, branch name, or tag. The environment must be one listed in the `$environments` array in `config.php`.

#### /deploy help
Provides channel-specific information on using the slash command.

#### /deploy config
Provides more detailed channel-specific information about the configuration of the slash command.
