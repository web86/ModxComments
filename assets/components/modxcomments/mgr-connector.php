<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';

header('Content-Type: application/json; charset=utf-8');

$isModx3=class_exists('MODX\\Revolution\\modX');
$corePath=$modx->getOption(
    'modxcomments.core_path',
    null,
    MODX_CORE_PATH.'components/modxcomments/'
);

$action=isset($_REQUEST['action'])?(string)$_REQUEST['action']:'';

$routes=array(
    'mgr/comment/getlist'=>array(
        'modx2'=>'mgr/comment/getlist',
        'modx3'=>'ModxComments\\Processors\\Mgr\\Comment\\GetList',
        'file'=>$corePath.'src/Processors/Mgr/Comment/GetList.php',
    ),
    'ModxComments\\Processors\\Mgr\\Comment\\GetList'=>array(
        'modx2'=>'mgr/comment/getlist',
        'modx3'=>'ModxComments\\Processors\\Mgr\\Comment\\GetList',
        'file'=>$corePath.'src/Processors/Mgr/Comment/GetList.php',
    ),
    'mgr/comment/status'=>array(
        'modx2'=>'mgr/comment/status',
        'modx3'=>'ModxComments\\Processors\\Mgr\\Comment\\Status',
        'file'=>$corePath.'src/Processors/Mgr/Comment/Status.php',
    ),
    'ModxComments\\Processors\\Mgr\\Comment\\Status'=>array(
        'modx2'=>'mgr/comment/status',
        'modx3'=>'ModxComments\\Processors\\Mgr\\Comment\\Status',
        'file'=>$corePath.'src/Processors/Mgr/Comment/Status.php',
    ),
    'mgr/comment/remove'=>array(
        'modx2'=>'mgr/comment/remove',
        'modx3'=>'ModxComments\\Processors\\Mgr\\Comment\\Remove',
        'file'=>$corePath.'src/Processors/Mgr/Comment/Remove.php',
    ),
    'ModxComments\\Processors\\Mgr\\Comment\\Remove'=>array(
        'modx2'=>'mgr/comment/remove',
        'modx3'=>'ModxComments\\Processors\\Mgr\\Comment\\Remove',
        'file'=>$corePath.'src/Processors/Mgr/Comment/Remove.php',
    ),
);

if(!isset($routes[$action])){
    http_response_code(404);
    echo json_encode(array(
        'success'=>false,
        'message'=>'Unknown ModxComments manager action.',
    ));
    exit;
}

try{
    $route=$routes[$action];

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
            throw new RuntimeException(
                'MODX 3 model could not be loaded. expected='.$modelFile
            );
        }

        if(!is_file($route['file'])){
            throw new RuntimeException('Processor file is missing: '.$route['file']);
        }
        require_once $route['file'];

        if(!class_exists($route['modx3'])){
            throw new RuntimeException('Processor class could not be loaded: '.$route['modx3']);
        }

        $properties=$_REQUEST;
        unset($properties['action']);
        $response=$modx->runProcessor($route['modx3'],$properties);
    }else{
        $response=$modx->runProcessor(
            $route['modx2'],
            $_REQUEST,
            array('processors_path'=>$corePath.'processors/')
        );
    }

    if(!$response){
        throw new RuntimeException('Processor returned no response.');
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
}catch(Exception $e){
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
        'message'=>$message,
    ),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
