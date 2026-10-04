<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private, max-age=0');

$versionData=@include MODX_CORE_PATH.'docs/version.inc.php';
$fullVersion=is_array($versionData) && !empty($versionData['full_version'])
    ? (string)$versionData['full_version']
    : (is_array($versionData) && !empty($versionData['version']) ? (string)$versionData['version'] : '2.0.0');
$isModx3=version_compare($fullVersion,'3.0.0','>=');
$corePath=$modx->getOption(
    'modxcomments.core_path',
    null,
    MODX_CORE_PATH.'components/modxcomments/'
);

$input=array_merge(
    isset($_GET) && is_array($_GET) ? $_GET : array(),
    isset($_POST) && is_array($_POST) ? $_POST : array()
);

$managerUser=$modx->getAuthenticatedUser('mgr');
$isManagerAdmin=$managerUser && (
    (bool)$managerUser->get('sudo')
    || $managerUser->isMember('Administrator')
);

if(!$isManagerAdmin){
    http_response_code(403);
    echo json_encode(array(
        'success'=>false,
        'message'=>'access_denied',
    ));
    exit;
}

$providedToken='';
if(isset($_SERVER['HTTP_MODAUTH'])){
    $providedToken=(string)$_SERVER['HTTP_MODAUTH'];
}elseif(isset($input['HTTP_MODAUTH'])){
    $providedToken=(string)$input['HTTP_MODAUTH'];
}

$expectedToken=$modx->user
    ? (string)$modx->user->getUserToken($modx->context->get('key'))
    : '';

$tokenValid=$providedToken!=='' && $expectedToken!=='' && (
    function_exists('hash_equals')
        ? hash_equals($expectedToken,$providedToken)
        : $expectedToken===$providedToken
);

if(!$tokenValid){
    http_response_code(401);
    echo json_encode(array(
        'success'=>false,
        'message'=>'invalid_manager_token',
    ));
    exit;
}

$action=isset($input['action'])?(string)$input['action']:'';

$routes=array(
    'mgr/comment/getlist'=>array(
        'methods'=>array('GET','POST'),
        'modx2'=>'mgr/comment/getlist',
        'modx3'=>'ModxComments\\Processors\\Mgr\\Comment\\GetList',
        'file'=>$corePath.'src/Processors/Mgr/Comment/GetList.php',
    ),
    'ModxComments\\Processors\\Mgr\\Comment\\GetList'=>array(
        'methods'=>array('GET','POST'),
        'modx2'=>'mgr/comment/getlist',
        'modx3'=>'ModxComments\\Processors\\Mgr\\Comment\\GetList',
        'file'=>$corePath.'src/Processors/Mgr/Comment/GetList.php',
    ),
    'mgr/comment/status'=>array(
        'methods'=>array('POST'),
        'modx2'=>'mgr/comment/status',
        'modx3'=>'ModxComments\\Processors\\Mgr\\Comment\\Status',
        'file'=>$corePath.'src/Processors/Mgr/Comment/Status.php',
    ),
    'ModxComments\\Processors\\Mgr\\Comment\\Status'=>array(
        'methods'=>array('POST'),
        'modx2'=>'mgr/comment/status',
        'modx3'=>'ModxComments\\Processors\\Mgr\\Comment\\Status',
        'file'=>$corePath.'src/Processors/Mgr/Comment/Status.php',
    ),
    'mgr/comment/remove'=>array(
        'methods'=>array('POST'),
        'modx2'=>'mgr/comment/remove',
        'modx3'=>'ModxComments\\Processors\\Mgr\\Comment\\Remove',
        'file'=>$corePath.'src/Processors/Mgr/Comment/Remove.php',
    ),
    'ModxComments\\Processors\\Mgr\\Comment\\Remove'=>array(
        'methods'=>array('POST'),
        'modx2'=>'mgr/comment/remove',
        'modx3'=>'ModxComments\\Processors\\Mgr\\Comment\\Remove',
        'file'=>$corePath.'src/Processors/Mgr/Comment/Remove.php',
    ),
);

if(!isset($routes[$action])){
    http_response_code(404);
    echo json_encode(array(
        'success'=>false,
        'message'=>'action_not_found',
    ));
    exit;
}

$route=$routes[$action];
$method=strtoupper(isset($_SERVER['REQUEST_METHOD'])?(string)$_SERVER['REQUEST_METHOD']:'GET');
if(!in_array($method,$route['methods'],true)){
    http_response_code(405);
    header('Allow: '.implode(', ',$route['methods']));
    echo json_encode(array(
        'success'=>false,
        'message'=>'method_not_allowed',
    ));
    exit;
}

try{
    $properties=$input;
    unset($properties['action'],$properties['HTTP_MODAUTH']);

    if($isModx3){
        $modelAdded=$modx->addPackage(
            'ModxComments\\Model',
            $corePath.'src/',
            null,
            'ModxComments\\'
        );

        $modelClass='ModxComments\\Model\\Comment';
        $modelFile=$corePath.'src/Model/Comment.php';
        if(!class_exists($modelClass,false) && is_file($modelFile)){
            require_once $modelFile;
        }

        if(!$modelAdded || !class_exists($modelClass)){
            throw new RuntimeException('MODX 3 model could not be loaded.');
        }

        if(!is_file($route['file'])){
            throw new RuntimeException('Manager processor file is missing.');
        }
        require_once $route['file'];

        if(!class_exists($route['modx3'])){
            throw new RuntimeException('Manager processor class could not be loaded.');
        }

        $response=$modx->runProcessor($route['modx3'],$properties);
    }else{
        $processorPath=$corePath.'processors/';
        if(!is_dir($processorPath)){
            throw new RuntimeException('MODX 2 processor path is missing: '.$processorPath);
        }

        $response=$modx->runProcessor(
            $route['modx2'],
            $properties,
            array('processors_path'=>$processorPath)
        );
    }

    if(!$response){
        throw new RuntimeException('Manager processor returned no response.');
    }

    $payload=$response->getResponse();
    if(is_string($payload)){
        echo $payload;
    }else{
        echo json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
        );
    }
}catch(Throwable $e){
    $message='[ModxComments] Manager connector error: '
        .get_class($e).': '.$e->getMessage()
        .' in '.$e->getFile().':'.$e->getLine();

    if($isModx3){
        $modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR,$message);
    }else{
        $modx->log(modX::LOG_LEVEL_ERROR,$message);
    }

    http_response_code(500);
    echo json_encode(array(
        'success'=>false,
        'message'=>'server_error',
    ));
}
