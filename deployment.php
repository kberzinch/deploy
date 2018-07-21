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

$this_instance = $payload["repository"]["name"]."/".$payload["deployment"]["environment"];
$directory = '/var/www/'.$this_instance;

$return_value = 0;

$error = false;

$log_location = __DIR__."/".$this_instance."/".$payload["deployment"]["sha"]."/".$payload["deployment"]["id"];

mkdir($log_location, 0700, true);

copy(__DIR__."/log-index.html", $log_location."/index.html");
copy(__DIR__."/worker.js", $log_location."/worker.js");
file_put_contents($log_location."/plain.txt",
    "Delivery ID:    ".$_SERVER["HTTP_X_GITHUB_DELIVERY"]."\n".
    "Deployment ID:  ".$payload["deployment"]["id"]."\n".
    "Environment:    ".$payload["deployment"]["environment"]."\n".
    "Repository:     ".$payload["repository"]["full_name"]."\n".
    "Commit:         ".$payload["deployment"]["sha"]."\n\n"
);

file_put_contents($log_location."/title", $payload["deployment"]["environment"]." | ".$payload["repository"]["full_name"]);

set_status("pending", "Deployment started");

if (file_exists($directory.'/pre-deploy-hook.sh')) {
    echo passthru('/bin/bash '.$directory.'/pre-deploy-hook.sh >> '.$log_location.'/plain.txt 2>&1', $return_value);
    if ($return_value !== 0) {
        set_status("failure", "The pre-deploy-hook encountered an error.");
        $error = true;
        goto finish;
    }
}

$return_value = 0;

echo passthru(
    "/bin/bash ".__DIR__."/deployment.sh "
    .$payload["repository"]["name"]."/".$payload["deployment"]["environment"]." "
    .tokenize($payload["repository"]["clone_url"]
    ." ".$payload["deployment"]["sha"])." >> ".$log_location."/plain.txt 2>&1",
    $return_value
);

if ($return_value !== 0) {
    set_status("failure", "The git operation encountered an error.");
    $error = true;
    goto finish;
}

$return_value = 0;

if (file_exists($directory.'/post-deploy-hook.sh')) {
    echo "\n";
    echo passthru('/bin/bash '.$directory.'/post-deploy-hook.sh >> '.$log_location.'/plain.txt 2>&1', $return_value);
    if ($return_value !== 0) {
        set_status("failure", "The post-deploy-hook encountered an error.");
        $error = true;
        goto finish;
    }
}

finish:
// Transmit and store completion information
if (isset($email_from, $email_to) && ($always_email || $error)) {
    mail(
        $email_to,
        "[".$payload["repository"]["full_name"]."] New deployment triggered",
        "Please review the log at "."https://".$_SERVER["SERVER_NAME"]."/"
            .$payload["repository"]["name"]."/".$payload["deployment"]["environment"]."/".$payload["deployment"]["sha"]."/".$payload["deployment"]["id"],
        "From: ".$email_from
    );
}

set_status("success", "The deployment completed successfully.");
