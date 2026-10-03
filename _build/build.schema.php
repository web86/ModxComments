<?php
/**
 * MODX 3 development helper: generate the xPDO 3 model and create tables.
 */
use MODX\Revolution\modX;
use ModxComments\Model\Comment;
use ModxComments\Model\Vote;
use xPDO\xPDO;

$root=dirname(__DIR__);
require_once $root.'/config.core.php';
require_once MODX_CORE_PATH.'vendor/autoload.php';

$modx=modX::getInstance(null,array(
    xPDO::OPT_CONN_INIT=>array(xPDO::OPT_CONN_MUTABLE=>true),
));
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

$corePath=$root.'/core/components/modxcomments/';
$modelPath=$corePath.'src/';
$schema=$corePath.'model/schema/modxcomments.mysql.schema.xml';

$manager=$modx->getManager();
$generator=$manager->getGenerator();

if(!$generator->parseSchema($schema,$modelPath,array(
    'compile'=>0,
    'update'=>1,
    'regenerate'=>1,
    'namespacePrefix'=>'ModxComments\\',
))){
    fwrite(STDERR,"Could not parse MODX 3 schema\n");
    exit(1);
}

if(!$modx->addPackage('ModxComments\\Model',$modelPath,null,'ModxComments\\')){
    fwrite(STDERR,"Could not add ModxComments model package\n");
    exit(1);
}

$manager->createObjectContainer(Comment::class);
$manager->createObjectContainer(Vote::class);

echo "ModxComments MODX 3 schema/model ready.\n";
