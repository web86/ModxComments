<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$root=dirname(dirname(dirname(dirname(__FILE__))));
require_once $root.'/config.core.php';

$versionData=@include MODX_CORE_PATH.'docs/version.inc.php';
$fullVersion=is_array($versionData) && !empty($versionData['full_version'])
    ? (string)$versionData['full_version']
    : (is_array($versionData) && !empty($versionData['version']) ? (string)$versionData['version'] : '2.0.0');
$isModx3=version_compare($fullVersion,'3.0.0','>=');

if($isModx3){
    require_once MODX_CORE_PATH.'vendor/autoload.php';
}else{
    require_once MODX_CORE_PATH.'config/'.MODX_CONFIG_KEY.'.inc.php';
    require_once MODX_CORE_PATH.'model/modx/modx.class.php';
}

$input=$_REQUEST;
$contentType=isset($_SERVER['CONTENT_TYPE'])?strtolower((string)$_SERVER['CONTENT_TYPE']):'';
if(strpos($contentType,'application/json')!==false){
    $contentLength=isset($_SERVER['CONTENT_LENGTH'])?(int)$_SERVER['CONTENT_LENGTH']:0;
    if($contentLength>65536){
        http_response_code(413);
        echo json_encode(array('success'=>false,'message'=>'request_too_large','object'=>array()));
        exit;
    }

    $raw=file_get_contents('php://input');
    if(strlen($raw)>65536){
        http_response_code(413);
        echo json_encode(array('success'=>false,'message'=>'request_too_large','object'=>array()));
        exit;
    }

    $json=json_decode($raw,true);
    if(is_array($json)) $input=array_merge($input,$json);
}

$context=isset($input['context'])
    ? preg_replace('/[^a-zA-Z0-9_-]/','',(string)$input['context'])
    : 'web';
if($context==='' || strtolower($context)==='mgr') $context='web';

if($isModx3){
    $modx=\MODX\Revolution\modX::getInstance(null,array(
        \xPDO\xPDO::OPT_CONN_INIT=>array(\xPDO\xPDO::OPT_CONN_MUTABLE=>true),
    ));
}else{
    $modx=new modX();
}
$modx->initialize($context);

try{
    $modx->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
}catch(Exception $e){
    $modx->log(
        $isModx3 ? \MODX\Revolution\modX::LOG_LEVEL_WARN : modX::LOG_LEVEL_WARN,
        '[ModxComments] Could not switch connection to utf8mb4: '.$e->getMessage()
    );
}

if(strtoupper(isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:'GET')==='OPTIONS'){
    http_response_code(204);
    exit;
}

$action=isset($input['action'])?strtolower(trim((string)$input['action'])):'';

$routes=array(
    'web/init'=>array(
        'method'=>'GET',
        'modx2'=>'web/init',
        'modx3'=>'ModxComments\\Processors\\Web\\Init',
    ),
    'web/comment/getlist'=>array(
        'method'=>'GET',
        'modx2'=>'web/comment/getlist',
        'modx3'=>'ModxComments\\Processors\\Web\\Comment\\GetList',
    ),
    'web/comment/count'=>array(
        'method'=>'GET',
        'modx2'=>'web/comment/count',
        'modx3'=>'ModxComments\\Processors\\Web\\Comment\\Count',
    ),
    'web/comment/create'=>array(
        'method'=>'POST',
        'modx2'=>'web/comment/create',
        'modx3'=>'ModxComments\\Processors\\Web\\Comment\\Create',
    ),
    'web/comment/update'=>array(
        'method'=>'POST',
        'modx2'=>'web/comment/update',
        'modx3'=>'ModxComments\\Processors\\Web\\Comment\\Update',
    ),
    'web/comment/delete'=>array(
        'method'=>'POST',
        'modx2'=>'web/comment/delete',
        'modx3'=>'ModxComments\\Processors\\Web\\Comment\\Delete',
    ),
    'web/comment/vote'=>array(
        'method'=>'POST',
        'modx2'=>'web/comment/vote',
        'modx3'=>'ModxComments\\Processors\\Web\\Comment\\Vote',
    ),
);

if(!isset($routes[$action])){
    http_response_code(404);
    echo json_encode(array('success'=>false,'message'=>'action_not_found','object'=>array()));
    exit;
}

$method=strtoupper(isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:'GET');
if($method!==$routes[$action]['method']){
    http_response_code(405);
    header('Allow: '.$routes[$action]['method']);
    echo json_encode(array('success'=>false,'message'=>'method_not_allowed','object'=>array()));
    exit;
}

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');

$corePath=$modx->getOption('modxcomments.core_path',null,MODX_CORE_PATH.'components/modxcomments/');

if($isModx3){
    $modx->addPackage('ModxComments\\Model',$corePath.'src/',null,'ModxComments\\');
    $response=$modx->runProcessor($routes[$action]['modx3'],$input);
}else{
    $response=$modx->runProcessor(
        $routes[$action]['modx2'],
        $input,
        array('processors_path'=>$corePath.'processors/')
    );
}

if(!$response){
    http_response_code(500);
    echo json_encode(array('success'=>false,'message'=>'processor_not_found','object'=>array()));
    exit;
}

$payload=$response->getResponse();

if(is_string($payload)){
    $decoded=json_decode($payload,true);
    if(is_array($decoded) && empty($decoded['success'])) http_response_code(400);
    echo $payload;
}else{
    if(empty($payload['success'])) http_response_code(400);
    echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
