<?php

/**
 * Verifies and parses the payload
 * @return array the GitHub webhook payload
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
function payload()
{
    global $webhook_secret;
    list($algo, $hash) = explode('=', $_SERVER["HTTP_X_HUB_SIGNATURE"], 2);
    $payload = file_get_contents('php://input');
    if ($payload === false) {
        http_response_code(500);
        die("Could not read php://input");
    }
    $payloadHash = hash_hmac($algo, $payload, $webhook_secret);
    if ($hash !== $payloadHash) {
        http_response_code(401);
        die("Signature verification failed");
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
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
function github(
    string $url,
    array $data,
    string $action = "",
    string $accept = "application/vnd.github.machine-man-preview+json",
    string $method = "POST",
    int $expected_status = 201
): array {
    global $token;
    global $app_id;
    $curl = curl_init($url);
    if ($curl === false) {
        http_response_code(500);
        exit('Could not initialize cURL');
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        "Accept: ".$accept,
        "User-Agent: GitHub App ID ".$app_id[which_github()],
        "Authorization: Bearer ".$token
    ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($curl);
    if ($response === false || $response === true || curl_getinfo($curl, CURLINFO_HTTP_CODE) !== $expected_status) {
        echo "Error ".$action."\n".$url."\n".json_encode($data)."\n".curl_getinfo($curl, CURLINFO_HTTP_CODE)." "
            .$response;
        curl_close($curl);
        exit;
    }
    curl_close($curl);
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
    global $log_location;
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
        [
            "state" => $state,
            "log_url" => "https://".$_SERVER["SERVER_NAME"]."/"
                .$payload["repository"]["name"]."/".$payload["deployment"]["environment"]."/"
                .$payload["deployment"]["sha"]."/".$payload["deployment"]["id"]
                .($state === "pending" ? "/" : "/plain.txt"),
            "description" => $description
        ],
        "setting status",
        "application/vnd.github.ant-man-preview+json"
    );
    if ($state !== "pending") {
        file_put_contents($log_location."/plain.txt", "\n# ".$description."\n", FILE_APPEND);
    }
}

/**
 * Triggers a deployment for the ref that triggered this event
 */
function trigger_deployment($ref, $environment)
{
    global $payload;
    github(
        $payload["repository"]["deployments_url"],
        [
            "ref" => $ref,
            "environment" => $environment
        ],
        "triggering deployment"
    );
}

/**
 * Fetches an installation token for other components to use
 * @return string a GitHub App access token for interacting with the repository
 */
function token()
{
    global $token;

    $token = app_token();

    $access_token = github(
        api_base()."/installations/".installation_id()."/access_tokens",
        [],
        "getting access token"
    );

    return $access_token["token"];
}

/**
 * Checks the commit status for the current commit
 */
function get_commit_status(): array
{
    global $payload;

    return github(
        $payload["commit"]["url"]."/status",
        [],
        "getting commit status",
        "application/vnd.github.machine-man-preview+json",
        "GET",
        200
    );
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

/**
 * Gets an app JWT
 * @return string JWT for this GitHub
 */
function app_token()
{
    global $private_key;
    global $app_id;

    $key = new SimpleJWT\Keys\RSAKey(file_get_contents($private_key[which_github()]), 'pem');
    $set = new SimpleJWT\Keys\KeySet();
    $set->add($key);

    $headers = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = ['iss' => $app_id[which_github()], 'exp' => time() + 5];
    $jwt = new SimpleJWT\JWT($headers, $claims);

    return $jwt->encode($set);
}

/**
 * Provides the installation ID for this event.
 */
function installation_id()
{
    global $payload;
    global $token;

    if (isset($payload["installation"]["id"])) {
        return $payload["installation"]["id"];
    }
    if (!isset($token)) {
        $token = app_token();
    }
    $installation = github(
        api_base()."/repos/".$payload["repository"]["full_name"]."/installation",
        [],
        "getting installation information",
        "application/vnd.github.machine-man-preview+json",
        "GET",
        200
    );
    return $installation["id"];
}

/**
 * Returns base API URL for this event
 * @return string the GitHub API base URL
 */
function api_base()
{
    return "https://".(which_github() === "github.com" ? "api.github.com" : which_github()."/api/v3");
}
