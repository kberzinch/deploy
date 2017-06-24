<?php

require_once 'config.php';
require_once 'util.php';

global $payload = payload();

// Trigger a deployment if the branch matches
if ('refs/heads/'.$_GET["branch"] === $data["ref"]) {
    trigger_deployment();
}
