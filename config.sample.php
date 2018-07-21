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
 * If provided below, a personal access token will be used to clone new
 * repositories and publish deployment status information to the GitHub API.
 *
 * Generate a personal access token here: https://github.com/settings/tokens
 * If you want to be fancy, you can also get a real OAuth token. This is left
 * as an exercise for the reader.
 *
 * You can also add GitHub Enterprise credentials using the hostname in a
 * similar format.
 */
//$token['github.com'] = 'f6609dbf9796004709d3c34e7abbad8fb4a737ad';
//$token['api.github.com'] = $token['github.com'];

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
