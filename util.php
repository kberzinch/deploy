<?php

/**
 * Verifies and parses the payload
 * @return array the GitHub webhook payload
 */
function payload()
{
    global $webhook_secret;
    list($algo, $hash) = explode('=', $_SERVER["HTTP_X_HUB_SIGNATURE"], 2);
    $payload = file_get_contents('php://input');
    $payloadHash = hash_hmac($algo, $payload, $webhook_secret);
    if ($hash !== $payloadHash) {
        http_response_code(401);
        die("Signature verification failed.");
    }

    return json_decode($payload, true);
}

/**
 * Injects an authentication token for the given URL if one is available in the config file
 * @param  string $url The URL to tokenize
 * @return string      The URL, possibly with an authentication token inserted
 */
function add_access_token($url)
{
    global $token;
    $clone_url = explode("/", $url);
    $clone_url[2] = "x-access-token:".$token."@".$clone_url[2];
    return implode("/", $clone_url);
}

/**
 * Sends $data to $url
 * @param  string $url  The GitHub API URL to hit
 * @param  array  $data The data to send
 */
function github($url, array $data, $action = "", $accept = "application/vnd.github.machine-man-preview+json", $method = "POST", $expected_status = 201)
{
    global $token;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        "Accept: ".$accept,
        "User-Agent: GitHub App ID ".$app_id,
        "Authorization: Bearer ".$token
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== $expected_status) {
        echo "Error ".$action."\n";
        echo "[".curl_getinfo($ch, CURLINFO_HTTP_CODE)."] ";
        var_dump($response);
        http_response_code(500);
        curl_close($ch);
        exit;
    }
    curl_close($ch);
    return json_decode($response, true);
}

/**
 * Sends a status for this deployment to the GitHub API
 * @param string $state       (pending|success|error|inactive|failure)
 * @param string $description A description. This is not displayed anywhere as far as I can tell.
 */
function set_status($state, $description)
{
    global $payload;
    static $didfail = false;
    if ($state === "failure") {
        $didfail = true;
    }
    if ($state === "error") {
        $didfail = true;
    }
    if ($didfail && $state === "success") {
        return; // don't send this
    }
    if ($_SERVER["HTTP_X_GITHUB_EVENT"] !== "deployment") {
        return;
    }
    github(
        $payload["deployment"]["statuses_url"],
        array(
            "state" => $state,
            "log_url" => "https://".$_SERVER["SERVER_NAME"]."/"
                .$payload["repository"]["name"]."/".$payload["deployment"]["environment"]."/".$payload["deployment"]["sha"]."/".$payload["deployment"]["id"].($state === "success" ? "/plain.txt" : ""),
            "description" => $description
        ),
        "setting status",
        "application/vnd.github.ant-man-preview+json"
    );
}

/**
 * Triggers a deployment for the ref that triggered this event
 */
function trigger_deployment($ref, $environment)
{
    global $payload;
    github(
        $payload["repository"]["deployments_url"],
        array(
            "ref" => $ref,
            "environment" => $environment
        ),
        "triggering deployment"
    );
}

/**
 * Fetches an installation token for other components to use
 * @return string a GitHub App access token for interacting with the repository
 */
function token()
{
    global $payload;
    global $private_key;
    global $app_id;
    global $token;

    $key = new SimpleJWT\Keys\RSAKey(file_get_contents($private_key[which_github()]), 'pem');
    $set = new SimpleJWT\Keys\KeySet();
    $set->add($key);

    $headers = array('alg' => 'RS256', 'typ' => 'JWT');
    $claims = array('iss' => $app_id[which_github()], 'exp' => time() + 5);
    $jwt = new SimpleJWT\JWT($headers, $claims);

    $token = $jwt->encode($set);

    $api_base = which_github() === "github.com" ? "api.github.com" : which_github()."/api/v3";

    $access_token = github(
        "https://".$api_base."/installations/".$payload["installation"]["id"]."/access_tokens",
        [],
        "getting access token"
    );

    return $access_token["token"];
}

/**
 * Checks the commit status for the current commit
 * @return string one of pending, success, failure, error
 */
function get_commit_status()
{
    global $payload;
    global $token;
    global $app_id;

    $status = github(
        $payload["commit"]["url"]."/status",
        [],
        "getting commit status",
        "application/vnd.github.machine-man-preview+json",
        "GET",
        200
    );

    return $status["state"];
}

/**
 * Provides the primary GitHub domain for this event. Used for looking up app
 * registration information.
 * @return string primary GitHub domain
 */
function which_github()
{
    global $payload;
    return explode("/", $payload["repository"]["clone_url"])[2];
}
