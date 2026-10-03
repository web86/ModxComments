<?php
use MODX\Revolution\modCategory;
use MODX\Revolution\modChunk;
use MODX\Revolution\modSnippet;
use MODX\Revolution\modX;
use MODX\Revolution\Transport\modPackageBuilder;
use xPDO\Transport\xPDOTransport;
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
$readPackageText=function($path,$fallback) use ($modx){
    if(is_file($path) && is_readable($path)){
        $data=file_get_contents($path);
        if($data!==false && trim($data)!=='') return $data;
    }
    $modx->log(modX::LOG_LEVEL_WARN,'[ModxComments] Package metadata file is missing/unreadable: '.$path.'. Using embedded fallback.');
    return $fallback;
};

$builder=new modPackageBuilder($modx);
$builder->createPackage('modxcomments','0.3.0','beta8');
$builder->registerNamespace('modxcomments',false,true,'{core_path}components/modxcomments/');

$category=$modx->newObject(modCategory::class);
$category->set('category','ModxComments');

$snippetSource=file_get_contents($root.'/core/components/modxcomments/elements/snippets/snippet.modxcomments.php');
$snippetSource=preg_replace('/^\s*<\?(?:php)?\s*/i','',$snippetSource);
$snippetSource=preg_replace('/\?>\s*$/','',$snippetSource);

$snippet=$modx->newObject(modSnippet::class);
$snippet->fromArray(array(
    'name'=>'ModxComments',
    'description'=>'AJAX-first comments for MODX 3 resources.',
    'snippet'=>trim($snippetSource),
),'',true,true);
$category->addMany($snippet);

$emailChunks=array(
    'ModxCommentsEmailAdminSubject'=>'email.admin.subject.tpl',
    'ModxCommentsEmailAdminBody'=>'email.admin.body.tpl',
    'ModxCommentsEmailReplySubject'=>'email.reply.subject.tpl',
    'ModxCommentsEmailReplyBody'=>'email.reply.body.tpl',
);
foreach($emailChunks as $chunkName=>$chunkFile){
    $chunk=$modx->newObject(modChunk::class);
    $chunk->fromArray(array(
        'name'=>$chunkName,
        'description'=>'ModxComments email notification template.',
        'snippet'=>file_get_contents($root.'/core/components/modxcomments/elements/chunks/'.$chunkFile),
    ),'',true,true);
    $category->addMany($chunk);
}

$vehicle=$builder->createVehicle($category,array(
    xPDOTransport::PRESERVE_KEYS=>false,
    xPDOTransport::UPDATE_OBJECT=>true,
    xPDOTransport::UNIQUE_KEY=>'category',
    xPDOTransport::RELATED_OBJECTS=>true,
    xPDOTransport::RELATED_OBJECT_ATTRIBUTES=>array(
        'Snippets'=>array(
            xPDOTransport::PRESERVE_KEYS=>false,
            xPDOTransport::UPDATE_OBJECT=>true,
            xPDOTransport::UNIQUE_KEY=>'name',
        ),
        'Chunks'=>array(
            xPDOTransport::PRESERVE_KEYS=>false,
            xPDOTransport::UPDATE_OBJECT=>false,
            xPDOTransport::UNIQUE_KEY=>'name',
        ),
    ),
));

$vehicle->resolve('file',array(
    'source'=>$root.'/core/components/modxcomments',
    'target'=>"return MODX_CORE_PATH . 'components/';",
));
$vehicle->resolve('file',array(
    'source'=>$root.'/assets/components/modxcomments',
    'target'=>"return MODX_ASSETS_PATH . 'components/';",
));
$vehicle->resolve('php',array(
    'source'=>$root.'/_build/resolvers/resolve.install.php',
));

$builder->putVehicle($vehicle);
$builder->setPackageAttributes(array(
    'license'=>$readPackageText($root.'/LICENSE',"ModxComments\n\nCopyright (c) 2026 web86.\nAll rights reserved.\n"),
    'readme'=>$readPackageText($root.'/docs/INSTALL.md',"ModxComments for MODX 3\n\nInstall the package and add [[ModxComments]].\n"),
    'changelog'=>$readPackageText($root.'/CHANGELOG.md',"ModxComments 0.3.0-beta8 — MODX 3 branch.\n"),
    'requires'=>array(
        'php'=>'>=7.4.0',
        'modx'=>'>=3.0.0',
    ),
));
$builder->pack();

echo "Built ModxComments 0.3.0-beta8 transport package for MODX 3.\n";
