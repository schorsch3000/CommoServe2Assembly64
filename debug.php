<?php

$mode = 'multi-user'; // multi-user, single-user or ulti-only
// multi-user: each IP can select their repo
// single-user: all users use the same repo
// ulti-only: all users use Ultimate repo

$storagePath = 'data/'; // storage directory for config-storage (only used in single- and multi-user modes)

$removeFilesAfterSeconds = 60 * 60 * 24 * 7; // 7 days

//END OF CONFIGURATION

$offset = 0;
$resultLength = 5;

if ($removeFilesAfterSeconds > 0 && !random_int(0, 99)) {
    ClientSettings::cleanup($storagePath, $removeFilesAfterSeconds);
}

$clientName = null;
switch ($mode) {
    case 'ulti-only':
        break;
    case 'single-user':
        $clientName = 'THEONEANDONLY';
        break;
    case 'multi-user':
        $clientName = $_SERVER['REMOTE_ADDR'];
        break;
    default:
        sendText(['ERROR: Invalid mode configuration.']);
        exit();
}

const client2repo = [
    'Ultimate' => 'Assembly64',
    'Commodore' => 'CommoServe',
];
$settings = new ClientSettings($clientName, $storagePath);
$queryStore = new QueryStore($storagePath);

$query = $_GET['query'] ?? '';

if (str_contains($query, '(name:"+")')) {
    $settings->set('client-id', 'Ultimate');
    sendText(
        'You are currently using: ' . client2repo[$settings->get('client-id')],
    );

    exit();
} elseif (str_contains($query, '(name:"-")')) {
    $settings->set('client-id', 'Commodore');
    sendText(
        'You are currently using: ' . client2repo[$settings->get('client-id')],
    );

    exit();
} elseif (str_contains($query, '(name:"?")')) {
    sendText([
        'C64 Ultimate Repo Switcher help:',
        "  Search for name '+' for Assembly64",
        "  Search for name '-' for CommoServe",
        "  Search for name '?' for this help.",
        '',
        'Default is Assembly64.',
        'Settings are stored to your public IP',
        '',
        'You are currently using: ' . client2repo[$settings->get('client-id')],
    ]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$body = file_get_contents('php://input');

$allowedHeaders = [
    'Accept-Encoding',
    'Host',
    'User-Agent',
    'Client-Id',
    'Connection',
];

$ch = curl_init();

$hostIP = '185.187.254.229';

if (preg_match('#^/leet/search/entries/(Q[0-9a-f]{32})/#', $uri, $matches)) {
    $query = $queryStore->getQueryFromID($matches[1]);

    if ($query !== null) {
        $offset = $query[1];
        $query = $query[0];

        $uri =
            '/leet/search/aql/' .
            ($resultLength + 1) .
            "/$offset?" .
            http_build_query(['query' => $query]);
    }
}

if (str_contains($uri, '/aql') and !str_contains($uri, '/aql/')) {
    $uri = str_replace(
        '/aql',
        '/aql/' . ($resultLength + 1) . "/$offset",
        $uri,
    );
}
$fullUrl = 'http://' . $hostIP . $uri;

trigger_error($fullUrl, E_USER_NOTICE);

curl_setopt($ch, CURLOPT_URL, $fullUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HEADER, true);
$headers = [
    'Host: hackerswithstyle.se',
    'Accept:',
    'Content-Length:',
    'Content-Type:',
];
$headers[] = 'Client-Id: ' . $settings->get('client-id');

$clientHeaders = (function_exists('apache_request_headers')
    ? 'apache_request_headers'
    : 'getallheaders')();

foreach ($clientHeaders as $key => $value) {
    if (in_array($key, $allowedHeaders)) {
        $headers[] = $key . ': ' . $value;
    }
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
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
if (!str_contains($uri, '/aql/')) {
    echo $body;
    exit();
}
$result = json_decode($body, true);

$resultCount = count($result ?? []);

while (count($result ?? []) > $resultLength) {
    array_pop($result);
}

if (isset($result[0])) {
    if ($offset > 0) {
        array_unshift($result, [
            'name' => 'Previous page',
            'id' => $queryStore->getIDFromQuery([
                $query,
                max(0, $offset - $resultLength),
            ]),
        ]);
    }

    if ($resultCount > $resultLength) {
        $result[] = [
            'name' => 'Next page',
            'id' => $queryStore->getIDFromQuery([
                $query,
                $offset + $resultLength,
            ]),
        ];
    }
}

echo json_encode($result, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT);

exit();

class QueryStore {
    private $path;
    public function __construct($path) {
        $this->path = $path;
    }
    function getIDFromQuery($query) {
        $hash = 'Q' . md5(serialize($query));
        file_put_contents(
            $this->path . "/$hash.php",
            "<?php\nreturn " . var_export($query, true) . ";\n",
        );
        return $hash;
    }
    function getQueryFromID($id) {
        if (!preg_match('/^Q[a-f0-9]{32}$/', $id)) {
            return null;
        }
        $path = $this->path . "/$id.php";
        if (!file_exists($path)) {
            return null;
        }
        return include $path;
    }
}
