<?php
include $_SERVER['DOCUMENT_ROOT'].'/config.php';

// below code from http://php.net/manual/en/function.getallheaders.php		
if (!function_exists('getallheaders')) {		
   function getallheaders()		
   {		
        $headers = '';		
        foreach ($_SERVER as $name => $value) {		
            if (substr($name, 0, 5) == 'HTTP_') {		
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;		
            }		
        }		
        return $headers;		
    }		
}

$headers = getallheaders();
$hubSignature = $headers['X-Hub-Signature'];

list($algo, $hash) = explode('=', $hubSignature, 2);

$payload = file_get_contents('php://input');

$payloadHash = hash_hmac($algo, $payload, $secret);

if ($hash !== $payloadHash) {
    http_response_code(401);
    echo "Bad secret";
    exit;
}

$data = json_decode($payload, true);

echo "Authenticated properly\nDelivery ID: ".$headers['X-Github-Delivery']."\nRepository to deploy: ".$data["repository"]["name"]."\n";

// check to make sure repo is only alpha and periods and dashes
if (!ctype_alnum(str_replace(".", "", str_replace("-", "", $data["repository"]["name"])))) {
    echo "Repo name looks dangerous. Bailing...";
    exit;
}

echo passthru("/bin/bash ".$_SERVER['DOCUMENT_ROOT']."/pull.sh ".$data["repository"]["name"]." 2>&1");

// mail("you@example.com", "New commit pushed to ".$data["repository"]["name"], ob_get_contents(), "From: some-email@example.com");
