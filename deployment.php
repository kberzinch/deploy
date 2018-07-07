<?php

// This script is allowed to run for up to 10 minutes.
set_time_limit(600);

require_once 'config.php';
require_once 'util.php';

$payload = payload();

if ($_SERVER["HTTP_X_GITHUB_EVENT"] !== "deployment") {
    $payload["deployment"]["id"] = "0";
    $payload["deployment"]["sha"] = "0000000000000000000000000000000000000000";
    $payload["deployment"]["environment"] = "production";
}

if ($payload["deployment"]["environment"] === "github-pages") {
    echo "Not deploying GitHub Pages build.";
    exit;
}

set_status("pending", "Deployment started");

// Begin deployment process
echo "Delivery ID:    ".$_SERVER["HTTP_X_GITHUB_DELIVERY"]."\n";
echo "Deployment ID:  ".$payload["deployment"]["id"]."\n";
echo "Environment:    ".$payload["deployment"]["environment"]."\n";
echo "Repository:     ".$payload["repository"]["full_name"]."\n";
echo "Commit:         ".$payload["deployment"]["sha"]."\n\n";

$this_instance = $payload["repository"]["name"]."/".$payload["deployment"]["environment"];
$directory = '/var/www/'.$this_instance;

$return_value = 0;

if (file_exists($directory.'/pre-deploy-hook.sh')) {
    echo passthru('/bin/bash '.$directory.'/pre-deploy-hook.sh 2>&1', $return_value);
    if ($return_value !== 0) {
        set_status("failure", "The pre-deploy-hook encountered an error.");
		goto finish
    }
}

$return_value = 0;

echo passthru(
    "/bin/bash ".__DIR__."/deployment.sh "
    .$payload["repository"]["name"]."/".$payload["deployment"]["environment"]." "
    .tokenize($payload["repository"]["clone_url"]
    ." ".$payload["deployment"]["sha"])." 2>&1",
    $return_value
);

if ($return_value !== 0) {
    set_status("failure", "The git operation encountered an error.");
	goto finish
}

$return_value = 0;

if (file_exists($directory.'/post-deploy-hook.sh')) {
    echo "\n";
    echo passthru('/bin/bash '.$directory.'/post-deploy-hook.sh 2>&1', $return_value);
    if ($return_value !== 0) {
        set_status("failure", "The post-deploy-hook encountered an error.");
		goto finish
    }
}

finish:
// Transmit and store completion information
if (isset($email_from, $email_to)) {
    mail(
        $email_to,
        "[".$payload["repository"]["full_name"]."] New deployment triggered",
        ob_get_contents(),
        "From: ".$email_from
    );
}

mkdir(__DIR__."/".$this_instance."/".$payload["deployment"]["sha"], 0700, true);

file_put_contents(
    __DIR__."/".$this_instance."/".$payload["deployment"]["sha"]."/".$payload["deployment"]["id"].".html",
    '<pre>'.ob_get_contents().'</pre>'
);

set_status("success", "The deployment completed successfully.");
