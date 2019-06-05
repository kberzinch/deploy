<?php declare(strict_types = 1);

// phpcs:disable Generic.Strings.UnnecessaryStringConcat.Found

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

global $oauth_client_id;
global $oauth_client_secret;
global $app_id;

if (!isset($handshakes[$_GET['state']])) {
    http_response_code(400);
    exit(file_get_contents('../oauth_error.html'));
}

$curl = curl_init('https://' . $handshakes[$_GET['state']]['github'] . '/login/oauth/access_token');
if (false === $curl) {
    http_response_code(500);
    exit('Could not initialize cURL');
}
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'User-Agent: GitHub App ID ' . $app_id[$handshakes[$_GET['state']]['github']],
]);
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
    'client_id' => $oauth_client_id[$handshakes[$_GET['state']]['github']],
    'client_secret' => $oauth_client_secret[$handshakes[$_GET['state']]['github']],
    'code' => $_GET['code'],
    'state' => $_GET['state'],
]));
$response = curl_exec($curl);
$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

if (200 !== $code || is_bool($response)) {
    http_response_code(500);
    exit(file_get_contents('../oauth_error.html'));
}

$response = json_decode($response, true);

$success = file_put_contents(
    __DIR__ . '/../config.php',
    "\n" . '$slack_to_oauth[\'' . $handshakes[$_GET['state']]['team'] . '\'][\'' . $handshakes[$_GET['state']]['user']
        . '\'][\'' . $handshakes[$_GET['state']]['github'] . '\'] = \'' . $response['access_token'] . '\';' . "\n",
    FILE_APPEND
);

if (false === $success) {
    http_response_code(500);
    exit(file_get_contents('../oauth_error.html'));
}

exit(file_get_contents('../oauth_success.html'));
