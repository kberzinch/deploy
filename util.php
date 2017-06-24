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
function tokenize(string $url)
{
    global $token;
    $clone_url = explode("/", $url);
    if (isset($token[$clone_url[2]])) {
        $clone_url[2] = $token[$clone_url[2]]."@".$clone_url[2];
    }
    return implode("/", $clone_url);
}

/**
 * Sends $data to $url
 * @param  string $url  The GitHub API URL to hit
 * @param  array  $data The data to send
 */
function github(string $url, array $data)
{
    $ch = curl_init(tokenize($url));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        "Accept: application/vnd.github.ant-man-preview+json",
        "User-Agent: ".$_SERVER["SERVER_NAME"]
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) {
        var_dump($response);
    }
    curl_close($ch);
}

/**
 * Sends a status for this deployment to the GitHub API
 * @param string $state       (pending|success|error|inactive|failure)
 * @param string $description A description. This is not displayed anywhere as far as I can tell.
 */
function set_status(string $state, string $description)
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
                .$payload["repository"]["name"]."/".$payload["deployment"]["sha"],
            "description" => $description
        )
    );
}

/**
 * Triggers a deployment for the ref that triggered this event
 */
function trigger_deployment()
{
    global $payload;
    github(
        $payload["repository"]["deployments_url"],
        array(
            "ref" => $payload["ref"],
        )
    );
}
