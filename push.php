<?php

require_once 'config.php';
require_once 'util.php';

$payload = payload();

// Trigger a deployment if the branch matches
if ('refs/heads/'.$_GET["branch"] === $payload["ref"]) {
    echo "Deployment triggered."
    trigger_deployment($_GET["environment"]);
} else {
    echo "Not triggering deployment\n".
    "Target branch: ".$_GET["branch"]."\n".
    "This ref:      ".$payload["ref"];
}
