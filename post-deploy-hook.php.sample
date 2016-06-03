<?php

if (!$_SERVER['REQUEST_URI'] == '/pull.php') {
    http_response_code(401);
	echo 'Not authorized';
}

unset($email_to); // comment this line and uncomment the one below to enable another email
// $email_to = 'another email address';

if (isset($email_from, $email_to)) {
    mail($email_to, "[".$data["repository"]["full_name"]."] New ".$_SERVER["HTTP_X_GITHUB_EVENT"]." triggered a deployment", ob_get_contents(), "From: ".$email_from);
}
