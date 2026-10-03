<?php
use MODX\Revolution\modX;
use xPDO\xPDO;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$root=dirname(dirname(dirname(dirname(__FILE__))));
require_once $root.'/config.core.php';
require_once MODX_CORE_PATH.'vendor/autoload.php';

$input=$_REQUEST;
$contentType=isset($_SERVER['CONTENT_TYPE'])?strtolower((string)$_SERVER['CONTENT_TYPE']):'';
if(strpos($contentType,'application/json')!==false){
    $json=json_decode(file_get_contents('php://input'),true);
    if(is_array($json)) $input=array_merge($input,$json);
}

$context=isset($input['context'])?preg_replace('/[^a-zA-Z0-9_-]/','',(string)$input['context']):'web';
if($context==='') $context='web';

$modx=modX::getInstance(null,array(
    xPDO::OPT_CONN_INIT=>array(xPDO::OPT_CONN_MUTABLE=>true),
));
$modx->initialize($context);

try{
    $modx->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
}catch(Exception $e){
    $modx->log(modX::LOG_LEVEL_WARN,'[ModxComments] Could not switch connection to utf8mb4: '.$e->getMessage());
}

if(strtoupper(isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:'GET')==='OPTIONS'){
    http_response_code(204);
    exit;
}

$action=isset($input['action'])?strtolower(trim((string)$input['action'])):'';
$allowed=array(
    'web/init'=>array('GET','ModxComments\\Processors\\Web\\Init'),
    'web/comment/getlist'=>array('GET','ModxComments\\Processors\\Web\\Comment\\GetList'),
    'web/comment/count'=>array('GET','ModxComments\\Processors\\Web\\Comment\\Count'),
    'web/comment/create'=>array('POST','ModxComments\\Processors\\Web\\Comment\\Create'),
    'web/comment/update'=>array('POST','ModxComments\\Processors\\Web\\Comment\\Update'),
    'web/comment/delete'=>array('POST','ModxComments\\Processors\\Web\\Comment\\Delete'),
    'web/comment/vote'=>array('POST','ModxComments\\Processors\\Web\\Comment\\Vote'),
);

if(!isset($allowed[$action])){
    http_response_code(404);
    echo json_encode(array('success'=>false,'message'=>'action_not_found','object'=>array()));
    exit;
}

$method=strtoupper(isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:'GET');
if($method!==$allowed[$action][0]){
    http_response_code(405);
    header('Allow: '.$allowed[$action][0]);
    echo json_encode(array('success'=>false,'message'=>'method_not_allowed','object'=>array()));
    exit;
}

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');

$corePath=$modx->getOption('modxcomments.core_path',null,MODX_CORE_PATH.'components/modxcomments/');
$modx->addPackage('ModxComments\\Model',$corePath.'src/',null,'ModxComments\\');
$processorClass=$allowed[$action][1];
$response=$modx->runProcessor($processorClass,$input);

if(!$response){
    http_response_code(500);
    echo json_encode(array('success'=>false,'message'=>'processor_not_found','object'=>array()));
    exit;
}

$payload=$response->getResponse();
if(empty($payload['success'])) http_response_code(400);

echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
