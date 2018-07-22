<?php

require __DIR__ . '/vendor/autoload.php';
require_once 'config.php';
require_once 'util.php';

header("Content-Type: application/json");

$is_slack = true;

// Make sure this workspace has an owner
if (!array_key_exists($_POST["team_id"], $slack_owner_id)) {
    die(
        "This workspace isn't configured for deployments yet. Contact your DevOps lead.\n\nWorkspace ID: ".$_POST["team_id"]."\nUser ID: ".$_POST["user_id"]
    );
}

if (time() - intval($_SERVER["HTTP_X_SLACK_REQUEST_TIMESTAMP"]) > 60) {
    die('Signature expired ('.(time() - intval($_SERVER["HTTP_X-SLACK-REQUEST-TIMESTAMP"])).'). Contact <@'.$slack_owner_id[$_POST["team_id"]].'> for further assistance.');
}

$payload = 'v0:'.$_SERVER["HTTP_X_SLACK_REQUEST_TIMESTAMP"].":".file_get_contents('php://input');

foreach ($slack_signing_secret as $secret) {
    $payloadHash = hash_hmac("SHA256", $payload, $slack_signing_secret);
    if ($_SERVER["HTTP_X_SLACK_SIGNATURE"] === "v0=".$payloadHash) {
        break;
    }
}

// Make sure the signature matches
if ($_SERVER["HTTP_X_SLACK_SIGNATURE"] !== "v0=".$payloadHash) {
    if ($_POST["user_id"] === $slack_owner_id[$_POST["team_id"]]) {
        die(
            'Signature verification failed. Check to make sure signing secrets match between Slack and your server.'
        );
    } else {
        die(
            'Signature verification failed. Contact <@'.$slack_owner_id[$_POST["team_id"]].'> for further assistance.'
        );
    }
}

if ($_POST["text"] === "config") {
    if ($_POST["user_id"] === $slack_owner_id[$_POST["team_id"]]) {
        echo "*Basic checks passed:* :heavy_check_mark:\n*Authorized users:*";
        foreach ($slack_authorized_users[$_POST["team_id"]] as $user) {
            echo ' <@'.$user.'>';
        }
        echo "\n*GitHub account:* ".$slack_gh_org[$_POST["team_id"]]."\n*Repositories for this channel:*";
        foreach ($slack_channel_repos[$_POST["team_id"]][$_POST["channel_id"]] as $repo) {
            echo ' '.$repo.' '.(isset($github_installation_ids[$slack_gh_org[$_POST["team_id"]]."/".$repo]) ? "(".$github_installation_ids[$slack_gh_org[$_POST["team_id"]]."/".$repo] : "(missing IID");
            echo '@'.(isset($which_github[$slack_gh_org[$_POST["team_id"]]."/".$repo]) ? $which_github[$slack_gh_org[$_POST["team_id"]]."/".$repo] : "missing which github");
            echo "),";
        }
        die();
    } else {
        die(
            "`config` is only available to the owner registered for this workspace."
        );
    }
}

if (!array_key_exists($_POST["channel_id"], $slack_channel_repos[$_POST["team_id"]])) {
    die(
        "This command can't be used in this channel."
    );
}

if (!in_array($_POST["user_id"], $slack_authorized_users[$_POST["team_id"]])) {
    die(
        "You're not authorized to use this slash command. User ID: ".$_POST["user_id"]
    );
}

$repos_for_channel = $slack_channel_repos[$_POST["team_id"]][$_POST["channel_id"]];

if ($_POST["text"] === "help") {
    if (count($repos_for_channel) === 0) {
        die("No repositories can be deployed from this channel.");
    }
    die(
        "The following repositories can be deployed from this channel: *".
        implode(", ", $repos_for_channel).
        "*\n\nTo trigger a deployment, use */deploy".(count($repos_for_channel) > 1 ? " [repository]" : "")." [git ref] [environment]*"
    );
}

$input = explode(" ", $_POST["text"]);

if (count($repos_for_channel) === 0) {
    die("No repositories can be deployed from this channel.");
} elseif (count($repos_for_channel) === 1) {
    if (count($input) !== 2) {
        die('Please provide a git ref and environment name.');
    }
    if (!in_array($input[1], $environments)) {
        die('Environment must be one of *'.implode(", ", $environments).'*');
    }
    $payload = [];
    $payload["repository"] = [];
    $payload["installation"] = [];
    $payload["repository"]["clone_url"] = "https://".$which_github[$slack_gh_org[$_POST["team_id"]]."/".$repos_for_channel[0]];
    $payload["installation"]["id"] = $github_installation_ids[$slack_gh_org[$_POST["team_id"]]."/".$repos_for_channel[0]];
    $token = token();
    $api_base = which_github() === "github.com" ? "api.github.com" : which_github()."/api/v3";
    github(
        'https://'.$api_base.'/repos/'.$slack_gh_org[$_POST["team_id"]]."/".$repos_for_channel[0]."/deployments",
        [
            "ref" => $input[0],
            "environment" => $input[1],
            "auto_merge" => false,
        ],
        "triggering deployment"
    );
} else {
    if (count($input) !== 3) {
        die('Please provide a repository, git ref, and environment name.');
    }
    if (!in_array($input[0], $repos_for_channels)) {
        die('Repository must be one of *'.implode(", ", $repos_for_channel).'*');
    }
    if (!in_array($input[2], $environments)) {
        die('Environment must be one of *'.implode(", ", $environments).'*');
    }
    $payload["repository"]["clone_url"] = "https://".$which_github[$slack_gh_org[$_POST["team_id"]]."/".$input[0]];
    $payload["installation"]["id"] = $github_installation_ids[$slack_gh_org[$_POST["team_id"]]."/".$input[0]];
    $token = token();
    github(
        'https://'.$api_base.'/repos/'.$slack_gh_org[$_POST["team_id"]]."/".$input[0]."/deployments",
        [
            "ref" => $input[1],
            "environment" => $input[2],
            "auto_merge" => false,
        ],
        "triggering deployment"
    );
}

echo '{"response_type": "in_channel"}';
