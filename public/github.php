<?php

// This script is allowed to run for up to 10 minutes.
set_time_limit(600);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';

global $always_email;

$payload = payload();

switch ($_SERVER["HTTP_X_GITHUB_EVENT"]) {
    case "ping":
        echo "Hello GitHub!";
        break;
    case "status":
        $repo = $payload["repository"]["name"];
        $branch = $payload["branches"][0]["name"];
        if (isset($repositories[$repo]["status"][$branch])) {
            // Verify commit status is good
            $token = token();
            $status = get_commit_status();
            if ($status["state"] !== "success") {
                echo "Current commit status of ".$payload["sha"]." is ".$status['state']." - not deploying";
                exit;
            }
            if (isset($required_status_checks[$payload["repository"]["full_name"]])
                && $required_status_checks[$payload["repository"]["full_name"]] !== $status["total_count"]) {
                echo $status["total_count"]." of ".$required_status_checks[$payload["repository"]["full_name"]]
                    ." required status checks completed - not deploying";
                exit;
            }
            echo "Requesting deployment of ".$payload["repository"]["full_name"]."/".$branch." to "
                .$repositories[$repo]["status"][$branch]."\n";
            trigger_deployment($payload["sha"], $repositories[$repo]["status"][$branch]);
        } else {
            echo "No applicable configuration found.\n\n";
            echo "Repository: ".$repo."\n";
            echo "Event:      ".$_SERVER["HTTP_X_GITHUB_EVENT"]."\n";
            echo "Branch:     ".$branch;
        }
        break;
    case 'check_suite':
        $repo = $payload["repository"]["name"];
        $branch = $payload["check_suite"]["head_branch"];
        if (isset($repositories[$repo]["checks"][$branch])) {
            if ($payload['action'] === 'completed' && $payload['check_suite']['conclusion'] === 'success') {
                echo "Requesting deployment of ".$payload["repository"]["full_name"]."/".$branch." to "
                    .$repositories[$repo]["checks"][$branch]."\n";
                $token = token();
                trigger_deployment($payload["sha"], $repositories[$repo]["checks"][$branch]);
            } else {
                echo "Action is ".$payload['action'].", conclusion is ".$payload['check_suite']['conclusion']
                .' - not deploying';
                exit;
            }
        } else {
            echo "No applicable configuration found.\n\n";
            echo "Repository: ".$repo."\n";
            echo "Event:      checks\n";
            echo "Branch:     ".$branch;
        }
        break;
    case "push":
        $repo = $payload["repository"]["name"];
        $branch = substr($payload["ref"], 11);
        if (isset($repositories[$repo]["push"][$branch])) {
            echo "Requesting deployment of ".$payload["repository"]["full_name"]."/".$branch." to "
                .$repositories[$repo]["push"][$branch]."\n";
            $token = token();
            trigger_deployment($payload["ref"], $repositories[$repo]["push"][$branch]);
        } else {
            echo "No applicable configuration found.\n\n";
            echo "Repository: ".$repo."\n";
            echo "Event:      ".$_SERVER["HTTP_X_GITHUB_EVENT"]."\n";
            echo "Branch:     ".$branch;
        }
        break;
    case "deployment":
        if ($payload["deployment"]["environment"] === "github-pages") {
            echo "Not deploying GitHub Pages build.";
            exit;
        } else {
            echo "Deployed ".$payload["repository"]["full_name"]." to ".$payload["deployment"]["environment"]
                ."\nhttps://".$_SERVER["SERVER_NAME"]."/".$payload["repository"]["name"]."/"
                .$payload["deployment"]["environment"]."/".$payload["deployment"]["sha"]."/"
                .$payload["deployment"]["id"]."/plain.txt";
        }
        $token = token();

        $this_instance = $payload["repository"]["name"]."/".$payload["deployment"]["environment"];
        $directory = '/var/www/'.$this_instance;

        $return_value = 0;

        $error = false;

        $log_location = __DIR__."/".$this_instance."/".$payload["deployment"]["sha"]."/".$payload["deployment"]["id"];

        mkdir($log_location, 0700, true);

        copy(__DIR__."/../log-index.html", $log_location."/index.html");
        copy(__DIR__."/../worker.js", $log_location."/worker.js");
        file_put_contents(
            $log_location."/plain.txt",
            "Delivery ID:    ".$_SERVER["HTTP_X_GITHUB_DELIVERY"]."\n".
            "Deployment ID:  ".$payload["deployment"]["id"]."\n".
            "Environment:    ".$payload["deployment"]["environment"]."\n".
            "Repository:     ".$payload["repository"]["full_name"]."\n".
            "Commit:         ".$payload["deployment"]["sha"]."\n",
            FILE_APPEND
        );

        file_put_contents(
            $log_location."/title",
            $payload["deployment"]["environment"]." | ".$payload["repository"]["full_name"],
            FILE_APPEND
        );

        set_status("pending", "Deployment started");

        if (file_exists($directory.'/pre-deploy-hook.sh')) {
            file_put_contents($log_location."/plain.txt", "\n# Executing pre-deploy-hook.sh\n", FILE_APPEND);
            passthru(
                '/bin/bash -x -e -o pipefail '.$directory.'/pre-deploy-hook.sh >> '.$log_location.'/plain.txt 2>&1',
                $return_value
            );
            if ($return_value !== 0) {
                set_status("failure", "The pre-deploy-hook encountered an error.");
                $error = true;
                goto finish;
            }
        }

        $return_value = 0;

        file_put_contents($log_location."/plain.txt", "\n# Executing git operations\n", FILE_APPEND);

        passthru(
            "/bin/bash -x -e -o pipefail ".__DIR__."/../deployment.sh "
            .$payload["repository"]["name"]."/".$payload["deployment"]["environment"]." "
            .add_access_token($payload["repository"]["clone_url"])
            ." ".$payload["deployment"]["sha"]." >> ".$log_location."/plain.txt 2>&1",
            $return_value
        );

        if ($return_value !== 0) {
            set_status("failure", "Git operations encountered an error.");
            $error = true;
            goto finish;
        }

        $return_value = 0;

        if (file_exists($directory.'/post-deploy-hook.sh')) {
            file_put_contents($log_location."/plain.txt", "\n# Executing post-deploy-hook.sh\n", FILE_APPEND);
            passthru(
                '/bin/bash -x -e -o pipefail '.$directory.'/post-deploy-hook.sh >> '.$log_location.'/plain.txt 2>&1',
                $return_value
            );
            if ($return_value !== 0) {
                set_status("failure", "The post-deploy-hook encountered an error.");
                $error = true;
                goto finish;
            }
        }

        finish:
        // Transmit and store completion information
        if (isset($email_from, $email_to) && ($always_email || $error)) {
            mail(
                $email_to,
                "[".$payload["repository"]["full_name"]."] New deployment triggered",
                "Please review the log at "."https://".$_SERVER["SERVER_NAME"]."/"
                    .$payload["repository"]["name"]."/".$payload["deployment"]["environment"]."/"
                    .$payload["deployment"]["sha"]."/".$payload["deployment"]["id"]."/plain.txt",
                "From: ".$email_from
            );
        }

        set_status("success", "The deployment completed successfully.");
        break;
    default:
        echo "Unrecognized event ".$_SERVER["HTTP_X_GITHUB_EVENT"];
        break;
}
