<?php

///////////////////////////////////////////////////////////
// Copy this file to config.php and edit as appropriate. //
///////////////////////////////////////////////////////////

/**
 * Set $webhook_secret to the same value you entered in the GitHub webhook
 * configuration.
 */
$webhook_secret = 'generate me at randomkeygen.com or wherever';

/**
 * If you'd like to host this tool on a shared domain, you may set a URL prefix here. Add a trailing slash if you set
 * one.
 */
$url_prefix = '';

/**
 * If you'd like email notifications when the script runs, set the two
 * variables below.
 */
//$email_from = 'deploy@example.com';
//$email_to = 'you@example.com';

/**
 * If set to false, emails will only be sent when an error is detected.
 */
$always_email = true;

/**
 * The location of the private key for your GitHub app here.
 */
$private_key["github.com"] = '/opt/deploy/your-github-app.pem';

/**
 * The ID of your GitHub app
 */
$app_id["github.com"] = 15018;

/**
 * Per-repository configuration in the below format
 * First key is repository
 * Second key is event (either push or status)
 * Third key is branch
 * Value is environment
 */
$repositories['deploy']['push']['master'] = 'production';

/**
 * How many completed status checks are required to trigger a deployment?
 *
 * Useful if multiple status checks run on merge.
 */
$required_status_checks['YourGitHubOrg/your-repository'] = 1;

/**
 * Tokens for Slack slash command.
 */
$slack_token['TXXXXXXXX'] = 'get me from Slack integration configuration';

/**
 * Owning user IDs for each team where this slash command is installed
 * This person will be mentioned if there is an error.
 */
$slack_owner_id['TXXXXXXXX'] = 'UXXXXXXXX';

/**
 * Array of users on each team authorized to trigger deployments from Slack
 */
$slack_authorized_users['TXXXXXXXX'] = ['UXXXXXXXX'];

/**
 * GitHub account for deployments - allows referencing only a repo in a channel
 */
$slack_gh_org['TXXXXXXXX'] = 'YourGitHubOrg';

/**
 * Array of Slack teams, channels, and repositories
 */
$slack_channel_repos['TXXXXXXXX']['CXXXXXXXX'] = [''];

/**
 * Which GitHub has your repository?
 */
$which_github['YourGitHubOrg/your-repository'] = 'github.com';

/**
 * List of valid environments for Slack
 */
$environments = ['production'];

/**
 * Information for OAuth handshakes
 */
$oauth_client_id['github.com'] = '';
$oauth_client_secret['github.com'] = '';

/**
 * Will be populated automatically as long as this file is writable. Listed here for reference.
 */
$slack_to_oauth['TXXXXXXXX']['UXXXXXXXX']['github.com'] = [''];
