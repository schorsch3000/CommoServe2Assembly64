<?php

$logRequests = false; // append every request to {logPath}/requests.log

// writable, but not served by the webserver
$logPath = getenv('COMMO2ASSEMBLY_LOG_PATH') ?: '../app_data/';

//END OF CONFIGURATION

$query = $_GET['query'] ?? '';

// Pseudo repo injected into the presets. Searching it is forwarded to
// CommoServe, every other search goes to Assembly64.
const commoServeRepo = 'commoserve';
$commoServeRepoTerm = 'repo\s*:\s*"?' . commoServeRepo . '\b"?';
$commoServeRepoPattern =
    '/\(\s*' .
    $commoServeRepoTerm .
    '\s*\)|\b' .
    $commoServeRepoTerm .
    '/i';

if (str_contains($query, '(name:"?")')) {
    sendText([
        'C64 Ultimate Repo Switcher help:',
        "  Pick repository 'CommoServe'",
        '  to search CommoServe, any other',
        '  search uses Assembly64.',
        "  Search for name '?' for this help.",
        '',
        'You are able to flip pages by adding',
        '#<pagenr> to your name',
        'eg.: searching giana#2 will get you',
        'page 2 of giana',
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$body = file_get_contents('php://input');

// Host and Client-Id are set by us, Accept-Encoding is handled by curl.
$allowedHeaders = ['user-agent', 'connection'];

$ch = curl_init();

$hostIP = '185.187.254.229';

// Presets and downloads always work with the Ultimate client id, even for
// CommoServe items. Only searching CommoServe needs the Commodore one.
$isPresets = str_starts_with($uri, '/leet/search/aql/presets');
$clientId = 'Ultimate';

if (str_contains($uri, '/aql') and !str_contains($uri, '/aql/')) {
    if (preg_match($commoServeRepoPattern, $query)) {
        // CommoServe does not know our pseudo repo, drop it from the query.
        $clientId = 'Commodore';
        $query = preg_replace($commoServeRepoPattern, '', $query);
        // Remove operators left dangling by that.
        $op = '(?:&|\band\b)';
        $query = preg_replace('/' . $op . '(\s*' . $op . ')+/i', '&', $query);
        $query = preg_replace('/(^|\()\s*' . $op . '\s*/i', '$1', $query);
        $query = preg_replace('/\s*' . $op . '\s*(\)|$)/i', '$1', $query);
        $query = preg_replace('/\(\s*\)/', '', $query);
        $query = trim($query);
    }

    $start = 0;
    if (preg_match('/\(name:"[^"]*#(\d+)"\)/', $query, $matches)) {
        $start = max(0, (int) $matches[1] * 40 - 40);
        $query = preg_replace('/(\(name:"[^"]*)#\d+("\))/', '$1$2', $query);
    }
    $params = array_merge($_GET, ['query' => $query]);
    $uri =
        str_replace('/aql', "/aql/$start/40", parse_url($uri, PHP_URL_PATH)) .
        '?' .
        http_build_query($params);
}
$fullUrl = 'http://' . $hostIP . $uri;

if ($logRequests) {
    logRequest($logPath, $clientId, $uri);
}

curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HEADER, true);
// Let curl negotiate and decompress, Content-Encoding is not passed on.
curl_setopt($ch, CURLOPT_ENCODING, '');
$headers = [
    'Host: hackerswithstyle.se',
    'Accept:',
    'Content-Length:',
    'Content-Type:',
];
$headers[] = 'Client-Id: ' . $clientId;

$clientHeaders = (function_exists('apache_request_headers')
    ? 'apache_request_headers'
    : 'getallheaders')();

foreach ($clientHeaders as $key => $value) {
    if (in_array(strtolower($key), $allowedHeaders)) {
        $headers[] = $key . ': ' . $value;
    }
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
if ($response === false) {
    http_response_code(502);
    sendText(['ERROR: Database not reachable:', curl_error($ch)]);
    exit();
}
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$headerLines = explode("\r\n", $header);
foreach ($headerLines as $headerLine) {
    if (
        stripos($headerLine, 'Content-Length:') === 0 ||
        stripos($headerLine, 'Transfer-Encoding:') === 0 ||
        stripos($headerLine, 'Connection:') === 0 ||
        stripos($headerLine, 'Content-Encoding:') === 0 ||
        stripos($headerLine, 'HTTP/') === 0 ||
        trim($headerLine) === ''
    ) {
        continue;
    }
    header($headerLine);
}
http_response_code($httpCode);
if ($isPresets && $httpCode === 200) {
    $body = addCommoServeRepo($body);
}
echo $body;

function addCommoServeRepo($body) {
    $presets = json_decode($body, true);
    if (!is_array($presets)) {
        return $body;
    }
    foreach ($presets as &$preset) {
        if (($preset['type'] ?? null) !== 'repo') {
            continue;
        }
        array_unshift($preset['values'], [
            'aqlKey' => commoServeRepo,
            'name' => 'CommoServe',
        ]);
    }
    unset($preset);
    return json_encode($presets, JSON_UNESCAPED_SLASHES);
}

function logRequest($path, $clientId, $uri) {
    $logFile = $path . '/requests.log';
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    // Escape control characters, a request must not forge log lines.
    $clean = fn($text) => addcslashes(urldecode($text), "\0..\37\177");
    file_put_contents(
        $logFile,
        sprintf(
            "%s %s %s %s -> %s %s\n",
            date('c'),
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['REQUEST_METHOD'],
            $clean($_SERVER['REQUEST_URI']),
            $clientId,
            $clean($uri),
        ),
        FILE_APPEND | LOCK_EX,
    );
}

function sendText($rows) {
    header('Content-Type: application/json');
    $result = [];
    foreach ((array) $rows as $row) {
        $result[] = ['name' => $row];
    }
    echo json_encode($result);
}
