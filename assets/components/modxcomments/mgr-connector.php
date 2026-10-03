<?php
use MODX\Revolution\modX;
use ModxComments\Model\Comment;

require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';

header('Content-Type: application/json; charset=utf-8');

$corePath=$modx->getOption(
    'modxcomments.core_path',
    null,
    MODX_CORE_PATH.'components/modxcomments/'
);

$action=isset($_REQUEST['action']) ? (string)$_REQUEST['action'] : '';

$routes=[
    'mgr/comment/getlist'=>[
        'class'=>'ModxComments\\Processors\\Mgr\\Comment\\GetList',
        'file'=>$corePath.'src/Processors/Mgr/Comment/GetList.php',
    ],
    'ModxComments\\Processors\\Mgr\\Comment\\GetList'=>[
        'class'=>'ModxComments\\Processors\\Mgr\\Comment\\GetList',
        'file'=>$corePath.'src/Processors/Mgr/Comment/GetList.php',
    ],
    'mgr/comment/status'=>[
        'class'=>'ModxComments\\Processors\\Mgr\\Comment\\Status',
        'file'=>$corePath.'src/Processors/Mgr/Comment/Status.php',
    ],
    'ModxComments\\Processors\\Mgr\\Comment\\Status'=>[
        'class'=>'ModxComments\\Processors\\Mgr\\Comment\\Status',
        'file'=>$corePath.'src/Processors/Mgr/Comment/Status.php',
    ],
    'mgr/comment/remove'=>[
        'class'=>'ModxComments\\Processors\\Mgr\\Comment\\Remove',
        'file'=>$corePath.'src/Processors/Mgr/Comment/Remove.php',
    ],
    'ModxComments\\Processors\\Mgr\\Comment\\Remove'=>[
        'class'=>'ModxComments\\Processors\\Mgr\\Comment\\Remove',
        'file'=>$corePath.'src/Processors/Mgr/Comment/Remove.php',
    ],
];

if(!isset($routes[$action])){
    http_response_code(404);
    echo json_encode([
        'success'=>false,
        'message'=>'Unknown ModxComments manager action.',
    ]);
    exit;
}

try{
    $modelAdded=$modx->addPackage(
        'ModxComments\\Model',
        $corePath.'src/',
        null,
        'ModxComments\\'
    );

    $modelFile=$corePath.'src/Model/Comment.php';
    if(!class_exists(Comment::class,false) && is_file($modelFile)){
        require_once $modelFile;
    }

    if(!$modelAdded || !class_exists(Comment::class)){
        throw new RuntimeException(
            'MODX 3 model could not be loaded. addPackage='
            .($modelAdded?'true':'false')
            .'; class='.Comment::class
            .'; expected='.$modelFile
        );
    }

    $route=$routes[$action];
    if(!is_file($route['file'])){
        throw new RuntimeException('Processor file is missing: '.$route['file']);
    }

    require_once $route['file'];

    if(!class_exists($route['class'])){
        throw new RuntimeException('Processor class could not be loaded: '.$route['class']);
    }

    $properties=$_REQUEST;
    unset($properties['action']);

    $response=$modx->runProcessor($route['class'],$properties);
    if(!$response){
        throw new RuntimeException('Processor returned no response: '.$route['class']);
    }

    echo $response->toJSON();
}catch(Throwable $e){
    $message='[ModxComments] Manager connector fatal: '.get_class($e).': '.$e->getMessage();
    $modx->log(modX::LOG_LEVEL_ERROR,$message);

    http_response_code(500);
    echo json_encode([
        'success'=>false,
        'message'=>$message,
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
