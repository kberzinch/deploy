<?php

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

global $oauth_client_id;
global $oauth_client_secret;

$response = github(
    "https://".$_GET['github']."/login/oauth/access_token",
    [
        'client_id' => $oauth_client_id[$_GET['github']],
        'client_secret' => $oauth_client_secret[$_GET['github']],
        'code' => $_GET['code'],
        'state' => $_GET['state'],
    ],
    'completing OAuth handshake',
    'POST',
    'application/json',
    200,
    $_GET['github']
);

var_dump($response);
