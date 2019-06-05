<?php declare(strict_types = 1);

// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingTraversableReturnTypeHintSpecification,Generic.NamingConventions.CamelCapsFunctionName.NotCamelCaps,SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingTraversableParameterTypeHintSpecification,Squiz.Arrays.ArrayBracketSpacing.SpaceBeforeBracket

/**
 * Verifies and parses the payload
 *
 * @return array the GitHub webhook payload
 *
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
function payload(): array
{
    global $webhook_secret;
    [$algo, $hash] = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2);
    $payload = file_get_contents('php://input');
    if (false === $payload) {
        http_response_code(500);
        die('Could not read php://input');
    }
    $payloadHash = hash_hmac($algo, $payload, $webhook_secret);
    if ($hash !== $payloadHash) {
        http_response_code(401);
        die('Signature verification failed');
    }

    return json_decode($payload, true);
}

/**
 * Injects an authentication token for the given URL if one is available in the config file
 *
 * @param string $url The URL to tokenize
 *
 * @return string      The URL, possibly with an authentication token inserted
 */
function add_access_token(string $url): string
{
    global $token;
    $clone_url = explode('/', $url);
    $clone_url[2] = 'x-access-token:' . $token . '@' . $clone_url[2];
    return implode('/', $clone_url);
}

/**
 * Sends $data to $url
 *
 * @param string $url  The GitHub API URL to hit
 * @param array  $data The data to send
 *
 * @SuppressWarnings(PHPMD.ExitExpression)
 * @SuppressWarnings(PHPMD.ElseExpression)
 */
function github(
    string $url,
    array $data,
    string $action = '',
    string $accept = 'application/vnd.github.machine-man-preview+json',
    string $method = 'POST',
    int $expected_status = 201,
    ?string $which_github = null
): array {
    global $token;
    global $app_id;
    global $is_slack;
    $curl = curl_init($url);
    if (false === $curl) {
        http_response_code(500);
        exit('Could not initialize cURL');
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: ' . $accept,
        'User-Agent: GitHub App ID ' . $app_id[($which_github ?? which_github())],
        'Authorization: Bearer ' . $token,
    ]);
    if ([] !== $data) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if (is_bool($response)) {
        if ($is_slack) {
            echo 'Error ' . $action . ': ' . $code;
            curl_close($curl);
            exit;
        }

        http_response_code(500);
        echo 'Error ' . $action . "\n" . $url . "\n" . json_encode($data) . "\n" . $code;
        curl_close($curl);
        exit;
    }

    if ($code !== $expected_status) {
        if ($is_slack) {
            echo 'Error ' . $action . ': ' . json_decode($response, true)['message'];
            curl_close($curl);
            exit;
        }

        http_response_code(500);
        echo 'Error ' . $action . "\n" . $method . ' ' . $url . "\n" . json_encode($data) . "\n" . $code . ' '
            . $response;
        curl_close($curl);
        exit;
    }

    curl_close($curl);
    return json_decode($response, true);
}

/**
 * Sends a status for this deployment to the GitHub API
 *
 * @param string $state       (pending|success|error|inactive|failure)
 * @param string $description A description. This is not displayed anywhere as far as I can tell.
 */
function set_status(string $state, string $description): void
{
    global $payload;
    global $log_location;
    global $url_prefix;
    static $didfail = false;
    global $environment_url;
    if ('failure' === $state) {
        $didfail = true;
    }
    if ('error' === $state) {
        $didfail = true;
    }
    if ($didfail && 'success' === $state) {
        return; // don't send this
    }
    if ('deployment' !== $_SERVER['HTTP_X_GITHUB_EVENT']) {
        return;
    }

    $data = [
        'state' => $state,
        'log_url' => 'https://' . $_SERVER['SERVER_NAME'] . '/' . $url_prefix
            . $payload['repository']['name'] . '/' . $payload['deployment']['environment'] . '/'
            . $payload['deployment']['sha'] . '/' . $payload['deployment']['id']
            . ('in_progress' === $state ? '/' : '/plain.txt'),
        'description' => $description,
    ];

    if (isset($environment_url[$payload['repository']['name']][$payload['deployment']['environment']])) {
        $data['environment_url'] = $environment_url[$payload['repository']['name']]
            [$payload['deployment']['environment']];
    }

    github(
        $payload['deployment']['statuses_url'],
        $data,
        'setting status',
        'application/vnd.github.ant-man-preview+json, application/vnd.github.flash-preview+json'
    );
    if ('in_progress' === $state) {
        return;
    }

    file_put_contents($log_location . '/plain.txt', "\n# " . $description . "\n", FILE_APPEND);
}

/**
 * Triggers a deployment for the ref that triggered this event
 *
 * @param string $ref The git ref that triggered the deployment
 * @param string $environment The environment being deployed
 *
 * @return void
 */
function trigger_deployment(string $ref, string $environment): void
{
    global $payload;
    github(
        $payload['repository']['deployments_url'],
        [
            'ref' => $ref,
            'environment' => $environment,
        ],
        'triggering deployment'
    );
}

/**
 * Fetches an installation token for other components to use
 *
 * @return string a GitHub App access token for interacting with the repository
 */
function token(): string
{
    global $token;

    $token = app_token();

    $access_token = github(
        api_base() . '/app/installations/' . installation_id() . '/access_tokens',
        [],
        'getting access token'
    );

    return $access_token['token'];
}

/**
 * Checks the commit status for the current commit
 */
function get_commit_status(): array
{
    global $payload;

    return github(
        $payload['commit']['url'] . '/status',
        [],
        'getting commit status',
        'application/vnd.github.machine-man-preview+json',
        'GET',
        200
    );
}

/**
 * Provides the primary GitHub domain for this event. Used for looking up app
 * registration information.
 *
 * @return string primary GitHub domain
 */
function which_github(): string
{
    global $payload;
    return explode('/', $payload['repository']['clone_url'])[2];
}

/**
 * Gets an app JWT
 *
 * @return string JWT for this GitHub
 *
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
function app_token(): string
{
    global $private_key;
    global $app_id;

    $key = file_get_contents($private_key[which_github()]);
    if (false === $key) {
        http_response_code(500);
        exit('Could not read private key for ' . which_github());
    }

    $key = new SimpleJWT\Keys\RSAKey($key, 'pem');
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
function installation_id(): int
{
    global $payload;
    global $token;

    if (isset($payload['installation']['id'])) {
        return $payload['installation']['id'];
    }
    if (!isset($token)) {
        $token = app_token();
    }
    $installation = github(
        api_base() . '/repos/' . $payload['repository']['full_name'] . '/installation',
        [],
        'getting installation information',
        'application/vnd.github.machine-man-preview+json',
        'GET',
        200
    );
    return $installation['id'];
}

/**
 * Returns base API URL for this event
 *
 * @return string the GitHub API base URL
 */
function api_base(): string
{
    return 'https://' . ('github.com' === which_github() ? 'api.github.com' : which_github() . '/api/v3');
}

/**
 * Either returns the token for the user or exits for OAuth flow
 *
 * @return string token for user
 *
 * @SuppressWarnings(PHPMD.ExitExpression)
 */
function user_token(string $which_github): string
{
    global $slack_to_oauth;
    global $oauth_client_id;
    if (isset($slack_to_oauth[$_POST['team_id']][$_POST['user_id']][$which_github])) {
        return $slack_to_oauth[$_POST['team_id']][$_POST['user_id']][$which_github];
    }
    $state = md5(uniqid('', true));

    $success = file_put_contents(
        __DIR__ . '/config.php',
        "\n" . '$handshakes[\'' . $state . '\'] = [\'team\' => \'' . $_POST['team_id'] . '\', \'user\' => \''
            . $_POST['user_id'] . '\', \'github\' => \'' . $which_github . '\']; // this line can be deleted' . "\n",
        FILE_APPEND
    );

    if (false === $success) {
        exit(
            "Looks like you're new here! Unfortunately, I wasn't able to create an OAuth link for you to link your "
                . 'Slack account to GitHub. Please make sure `config.php` is writable.'
        );
    }

    exit(
        "Looks like you're new here! *<https://" . $which_github . '/login/oauth/authorize?client_id='
            . $oauth_client_id[$which_github] . '&state=' . $state
            . '|Click here>* to link your Slack account to GitHub.'
    );
}
