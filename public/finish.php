<?php declare(strict_types = 1);

require __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../config.php')) {
    exit(file_get_contents(__DIR__ . '/../config_exists.html'));
}

if (!isset($_GET['code'])) {
    $code_somewhere = explode('?', $_GET['urlprefix']);
    $_GET['code'] = substr($code_somewhere[1], 5, strlen($code_somewhere[1]));
    $_GET['urlprefix'] = $code_somewhere[0];
}

$curl = curl_init(
    'https://' . ('github.com' === $_GET['github'] ? 'api.github.com' : $_GET['github'] . '/api/v3') . '/app-manifests/'
        . $_GET['code'] . '/conversions'
);
if (false === $curl) {
    http_response_code(500);
    exit('Could not initialize cURL');
}
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/vnd.github.fury-preview+json',
    'User-Agent: @kberzinch PHP Checks Setup Wizard',
]);
$response = curl_exec($curl);
$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

if (201 !== $code || is_bool($response)) {
    http_response_code(500);
    exit('Received failure response from GitHub.');
}

$response = json_decode($response, true);

$success = file_put_contents(__DIR__ . '/../' . $_GET['github'] . '-private-key.pem', $response['pem']);

if (false === $success) {
    http_response_code(500);
    exit('Failed to put private key in file. Is the directory writable?');
}

$success = file_put_contents(
    __DIR__ . '/../config.php',
    '<?php' . "\n\n" . '$webhook_secret = \'' . $response['webhook_secret'] . "';\n"
    . '$url_prefix = \'' . $_GET['urlprefix'] . "';\n"
    . '$private_key[\'' . $_GET['github'] . "'] = '" . __DIR__ . '/../' . $_GET['github'] . '-private-key.pem' . "';\n"
    . '$app_id[\'' . $_GET['github'] . "'] = " . $response['id'] . ";\n"
);

if (false === $success) {
    http_response_code(500);
    exit('Failed to write config.php. Is the directory writable?');
}

exit(file_get_contents('../setup_complete.html'));
