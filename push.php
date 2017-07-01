<?php

require_once 'config.php';
require_once 'util.php';

$payload = payload();

// Trigger a deployment if the branch matches
if ('refs/heads/'.$_GET["branch"] === $payload["ref"]) {
    trigger_deployment($_GET["environment"]);
}
