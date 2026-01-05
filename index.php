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

$fullUrl = 'http://' . $hostIP . $uri;

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
    if (stripos($headerLine, 'Content-Length:') === 0) {
        continue;
    }
    if (stripos($headerLine, 'Transfer-Encoding:') === 0) {
        continue;
    }
    if (stripos($headerLine, 'Connection:') === 0) {
        continue;
    }
    if (stripos($headerLine, 'Content-Encoding:') === 0) {
        continue;
    }
    if (stripos($headerLine, 'HTTP/') === 0) {
        header($headerLine);
        continue;
    }
    if (trim($headerLine) === '') {
        continue;
    }
    header($headerLine);
}
http_response_code($httpCode);
echo $body;

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
    private $data = [];
    public const DEFAULTS = ['client-id' => 'Ultimate'];
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
        $this->data[$key] = $value;
    }
    public function get($key) {
        if ($this->id === null) {
            return self::DEFAULTS[$key] ?? null;
        }
        if (isset($this->data[$key])) {
            $this->load($key);
        }
        return $this->data[$key];
    }
    private function load($key) {
        $storageFile = $this->getStoragePath($key);
        if (file_exists($storageFile)) {
            $this->data[$key] = include $storageFile;
        } else {
            $this->data[$key] = self::DEFAULTS[$key] ?? null;
        }
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
        $path = $this->path . '/' . $this->id . '#' . $key . '.php';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        return $path;
    }
}
