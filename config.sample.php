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
