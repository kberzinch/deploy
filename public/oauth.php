<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

global $oauth_client_id;
global $oauth_client_secret;

if (!isset($handshakes[$_GET['state']])) {
    http_response_code(400);
    exit(file_get_contents("../oauth_error.html"));
}

    $curl = curl_init("https://".$_GET['github']."/login/oauth/access_token");
if ($curl === false) {
    http_response_code(500);
    exit('Could not initialize cURL');
}
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Accept: application/json",
        "User-Agent: GitHub App ID ".$app_id[$_GET['github']],
    ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
        'client_id' => $oauth_client_id[$_GET['github']],
        'client_secret' => $oauth_client_secret[$_GET['github']],
        'code' => $_GET['code'],
        'state' => $_GET['state'],
    ]));
    $response = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if ($code !== 200) {
        http_response_code(500);
        exit(file_get_contents("../oauth_error.html"));
    }

    json_decode($response);

    $success = file_put_contents(
        __DIR__.'/../config.php',
        "\n".'$slack_to_oauth[\''.$handshakes[$_GET['state']]['team'].'\'][\''.$handshakes[$_GET['state']]['user'].'\'][\''.$handshakes[$_GET['state']]['github'].'\'] = \''.$response['access_token'].'\';'."\n",
        FILE_APPEND
    );

    if ($success === false) {
        http_response_code(500);
        exit(file_get_contents("../oauth_error.html"));
    }

    exit(file_get_contents("../oauth_success.html"));
