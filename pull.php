<?php
include 'config.php';

list($algo, $hash) = explode('=', $_SERVER["HTTP_X_HUB_SIGNATURE"], 2);

$payload = file_get_contents('php://input');

$payloadHash = hash_hmac($algo, $payload, $secret);

if ($hash !== $payloadHash) {
    http_response_code(401);
    echo "Bad secret";
    exit;
}

$data = json_decode($payload, true);

echo "Authenticated properly\nDelivery ID: ".$_SERVER["HTTP_X_GITHUB_DELIVERY"]."\nRepository to deploy: ".$data["repository"]["full_name"]."\n";

echo passthru("/bin/bash ".$_SERVER['DOCUMENT_ROOT']."/pull.sh ".$data["repository"]["name"]." ".$data["repository"]["full_name"]." ".$auth." 2>&1");

if (isset($email_from, $email_to)) {
    mail($email_to, "[".$data["repository"]["full_name"]."] New ".$_SERVER["HTTP_X_GITHUB_EVENT"]." triggered a deployment", ob_get_contents(), "From: ".$email_from);
}

if(file_exists('/var/www/'.$data["repository"]["name"].'/post-deploy-hook.php')){
    include('/var/www/'.$data["repository"]["name"].'/post-deploy-hook.php');
}
