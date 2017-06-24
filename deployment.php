<?php

require_once 'config.php';
require_once 'util.php';

// Verify payload signature
list($algo, $hash) = explode('=', $_SERVER["HTTP_X_HUB_SIGNATURE"], 2);
$payload = file_get_contents('php://input');
$payloadHash = hash_hmac($algo, $payload, $webhook_secret);
if ($hash !== $payloadHash) {
    http_response_code(401);
    die("Signature verification failed.");
}

$data = json_decode($payload, true);

if ($_SERVER["HTTP_X_GITHUB_EVENT"] !== "deployment") {
    $data["deployment"]["id"] = "0";
    $data["deployment"]["sha"] = "0000000000000000000000000000000000000000";
}

set_status("pending", "Deployment started", $data);

// Begin deployment process
echo "Delivery ID:    ".$_SERVER["HTTP_X_GITHUB_DELIVERY"]."\n";
echo "Deployment ID:  ".$data["deployment"]["id"]."\n";
echo "Repository:     ".$data["repository"]["full_name"]."\n";
echo "Commit:         ".$data["deployment"]["sha"]."\n\n";

$return_value = 0;

echo passthru(
    "/bin/bash ".__DIR__."/deployment.sh "
    .$data["repository"]["name"]." "
    .tokenize($data["repository"]["clone_url"]
    ." ".$data["deployment"]["sha"])." 2>&1",
    $return_value
);

if ($return_value !== 0) {
    set_status("failure", "The git operation encountered an error.", $data);
}

$return_value = 0;

if (file_exists('/var/www/'.$data["repository"]["name"].'/post-deploy-hook.sh')) {
    echo passthru('/bin/bash /var/www/'.$data["repository"]["name"].'/post-deploy-hook.sh 2>&1', $return_value);
    if ($return_value !== 0) {
        set_status("failure", "The post-deploy-hook encountered an error.", $data);
    }
}

// Transmit and store completion information
if (isset($email_from, $email_to)) {
    mail(
        $email_to,
        "[".$data["repository"]["full_name"]."] New deployment triggered",
        ob_get_contents(),
        "From: ".$email_from
    );
}

mkdir(__DIR__."/".$data["repository"]["name"], 0700, true);

file_put_contents(
    __DIR__."/".$data["repository"]["name"]."/".$data["deployment"]["sha"].".html",
    '<pre>'.ob_get_contents().'</pre>'
);

set_status("success", "The deployment completed successfully.", $data);
