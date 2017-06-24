<?php

require_once "config.php";

function tokenize(string $url)
{
    $clone_url = explode("/", $url);
    if (isset($token[$clone_url[4]])) {
        $clone_url[4] = $token."@".$clone_url[4];
    }
    return implode("/", $clone_url);
}

function set_status(string $state, string $description, mixed $payload)
{
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
