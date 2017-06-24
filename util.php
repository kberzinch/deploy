<?php

require_once "config.php";

function tokenize(string $url)
{
    global $token;
    $clone_url = explode("/", $url);
    if (isset($token[$clone_url[2]])) {
        $clone_url[2] = $token[$clone_url[4]]."@".$clone_url[2];
    }
    return implode("/", $clone_url);
}

function set_status(string $state, string $description, array $payload)
{
    static $didfail = false;
    if ($state === "failure") {
        $didfail = true;
    }
    if ($didfail && $state === "success") {
        return; // don't send this
    }
    if ($_SERVER["HTTP_X_GITHUB_EVENT"] !== "deployment") {
        return;
    }
    $ch = curl_init(tokenize($payload["deployment"]["statuses_url"]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        "Accept: application/vnd.github.ant-man-preview+json",
        "User-Agent: ".$_SERVER["SERVER_NAME"]
    ));
    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        json_encode(
            array(
                "state" => $state,
                "log_url" => "https://".$_SERVER["SERVER_NAME"]."/".$payload["repository"]["name"]."/".$payload["deployment"]["sha"],
                "description" => $description
            )
        )
    );
    curl_exec($ch);
    curl_close($ch);
}
