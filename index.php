<?php

$mode = 'multi-user'; // multi-user, single-user or ulti-only
// multi-user: each IP can select their repo
// single-user: all users use the same repo
// ulti-only: all users use Ultimate repo

$storagePath = 'sessions/'; // storage directory for config-storage (only used in single- and multi-user modes)

$removeFilesAfterSeconds = 60 * 60 * 24 * 7; // 7 days

//END OF CONFIGURATION

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

$client = $settings->get('repo');

if (!in_array($client, array_keys(client2repo))) {
    $client = 'Ultimate';
    $settings->set('repo', $client);
}

$query = $_GET['query'] ?? '';

if (str_contains($query, '(name:"+")')) {
    $client = 'Ultimate';
    $settings->set('repo', $client);
    sendText('You are currently using: ' . client2repo[$settings->get('repo')]);

    exit();
} elseif (str_contains($query, '(name:"-")')) {
    $client = 'Commodore';
    $settings->set('repo', $client);
    sendText('You are currently using: ' . client2repo[$settings->get('repo')]);

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
        'You are currently using: ' . client2repo[$settings->get('repo')],
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

$headers = '';
$requestHeaders = getallheaders();
foreach ($allowedHeaders as $header) {
    if (isset($requestHeaders[$header])) {
        if ($header === 'Client-Id') {
            $requestHeaders[$header] = $client;
        }
        $headers .= $header . ': ' . $requestHeaders[$header] . "\r\n";
    }
}

$request = "$method $uri HTTP/1.1\r\n" . $headers . "\r\n" . $body;

$fp = fsockopen('hackerswithstyle.se', 80, $errno, $errstr, 30);
if (!$fp) {
    echo "ERROR: $errno - $errstr<br />\n";
} else {
    fwrite($fp, $request);
    $response = '';
    while (!feof($fp)) {
        $response .= fgets($fp, 1024);
    }
    fclose($fp);
    $debugHeaders = '';
    $header_end = strpos($response, "\r\n\r\n");
    if ($header_end === false) {
        http_response_code(502);
        echo 'Bad Gateway: Malformed response from upstream.';
        exit();
    }
    $header_text = substr($response, 0, $header_end);
    $body = substr($response, $header_end + 4);

    $header_lines = explode("\r\n", $header_text);
    $http_sent = false;
    foreach ($header_lines as $hdr) {
        if (stripos($hdr, 'HTTP/') === 0) {
            if (!$http_sent) {
                header($hdr);
                $http_sent = true;
            }
        } elseif (!empty($hdr)) {
            header($hdr, false);
            $debugHeaders .= $hdr . "\n";
        }
    }
    $is_chunked = false;
    foreach ($header_lines as $hdr) {
        if (stripos($hdr, 'Transfer-Encoding: chunked') === 0) {
            $is_chunked = true;
            break;
        }
    }
    if ($is_chunked) {
        $body = dechunk($body);
    }
    echo $body;
}

function dechunk($chunked): string {
    $body = '';
    while (true) {
        $pos = strpos($chunked, "\r\n");
        if ($pos === false) {
            break;
        }
        $len = hexdec(substr($chunked, 0, $pos));
        if ($len === 0) {
            break;
        }
        $body .= substr($chunked, $pos + 2, $len);
        $chunked = substr($chunked, $pos + 2 + $len + 2);
    }
    return $body;
}

function sendText($rows) {
    header('Content-Type: application/json');
    $result = [];
    foreach ((array) $rows as $row) {
        $result[] = ['name' => $row];
    }
    echo json_encode($result);
}

class ClientSettings {
    private $id;
    private $path;
    public const DEFAULTS = ['repo' => 'Assembly64'];
    public function __construct($id, $path) {
        $this->id = $id;
        $this->path = $path;
    }
    public function set($key, $value) {
        if ($this->id === null) {
            return;
        }
        $storageFile = $this->getStoragePath($key);
        file_put_contents(
            $storageFile,
            "<?php\nreturn " . var_export($value, true) . ";\n",
        );
    }
    public function get($key) {
        if ($this->id === null) {
            return self::DEFAULTS[$key] ?? null;
        }
        $storageFile = $this->getStoragePath($key);
        if (file_exists($storageFile)) {
            touch($storageFile);
            return include $storageFile;
        }
        return self::DEFAULTS[$key] ?? null;
    }
    public function remove($key) {
        if ($this->id === null) {
            return;
        }
        $storageFile = $this->getStoragePath($key);
        if (file_exists($storageFile)) {
            unlink($storageFile);
        }
    }
    static function cleanup($path, $maxAgeSeconds = 60 * 60 * 24) {
        $now = time();
        foreach (glob($path . '/*') as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) > $maxAgeSeconds) {
                    unlink($file);
                }
            }
        }
    }
    private function getStoragePath($key) {
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $key)) {
            throw new Exception('Invalid key');
        }
        return $this->path . '/' . $this->id . '#' . $key . '.php';
    }
}
