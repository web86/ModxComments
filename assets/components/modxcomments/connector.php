<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once $root . '/config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$input = $_REQUEST;
$contentType = isset($_SERVER['CONTENT_TYPE']) ? strtolower((string) $_SERVER['CONTENT_TYPE']) : '';
if (strpos($contentType, 'application/json') !== false) {
    $json = json_decode(file_get_contents('php://input'), true);
    if (is_array($json)) {
        $input = array_merge($input, $json);
    }
}

$context = isset($input['context']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $input['context']) : 'web';
if ($context === '') $context = 'web';

$modx = new modX();
$modx->initialize($context);

// Comments contain emoji and other 4-byte Unicode characters.
try {
    $modx->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (Exception $e) {
    $modx->log(modX::LOG_LEVEL_WARN, '[ModxComments] Could not switch connection to utf8mb4: ' . $e->getMessage());
}

if (strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = isset($input['action']) ? strtolower(trim((string) $input['action'])) : '';
$allowed = array(
    'web/init' => 'GET',
    'web/comment/getlist' => 'GET',
    'web/comment/create' => 'POST',
    'web/comment/update' => 'POST',
    'web/comment/delete' => 'POST',
    'web/comment/vote' => 'POST',
);

if (!isset($allowed[$action])) {
    http_response_code(404);
    echo json_encode(array('success' => false, 'message' => 'action_not_found', 'object' => array()));
    exit;
}

$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
if ($method !== $allowed[$action]) {
    http_response_code(405);
    header('Allow: ' . $allowed[$action]);
    echo json_encode(array('success' => false, 'message' => 'method_not_allowed', 'object' => array()));
    exit;
}

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');

$corePath = $modx->getOption('modxcomments.core_path', null, MODX_CORE_PATH . 'components/modxcomments/');
$response = $modx->runProcessor($action, $input, array('processors_path' => $corePath . 'processors/'));

if (!$response) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'processor_not_found', 'object' => array()));
    exit;
}

$payload = $response->getResponse();
if (empty($payload['success'])) http_response_code(400);

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
