<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';

$corePath=$modx->getOption('modxcomments.core_path',null,MODX_CORE_PATH.'components/modxcomments/');
$modx->addPackage('ModxComments\\Model',$corePath.'src/',null,'ModxComments\\');

$modx->request->handleRequest([
    'location'=>'',
]);
