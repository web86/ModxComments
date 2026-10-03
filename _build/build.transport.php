<?php
/**
 * Build the universal MODX 2.8 + MODX 3 transport package.
 *
 * Important: build the universal transport on MODX 2.8.x. The resulting
 * legacy-shaped transport vehicle is installable on both supported lines,
 * while the packaged runtime selects the appropriate MODX/xPDO layer.
 */
$root=dirname(__DIR__);

require_once $root.'/config.core.php';
require_once MODX_CORE_PATH.'config/'.MODX_CONFIG_KEY.'.inc.php';
require_once MODX_CORE_PATH.'model/modx/modx.class.php';
require_once MODX_CORE_PATH.'model/modx/transport/modpackagebuilder.class.php';

$versionData=@include MODX_CORE_PATH.'docs/version.inc.php';
$fullVersion=is_array($versionData) && !empty($versionData['full_version'])
    ? (string)$versionData['full_version']
    : (is_array($versionData) && !empty($versionData['version']) ? (string)$versionData['version'] : '');

if($fullVersion!=='' && version_compare($fullVersion,'3.0.0','>=')){
    fwrite(
        STDERR,
        "Universal ModxComments transport must be built on MODX 2.8.x. "
        ."Build on MODX 2, then install the same ZIP on MODX 2 or MODX 3.\n"
    );
    exit(1);
}

$modx=new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

$readPackageText=function($path,$fallback) use ($modx){
    if(is_file($path) && is_readable($path)){
        $data=file_get_contents($path);
        if($data!==false && trim($data)!==''){
            return $data;
        }
    }

    $modx->log(
        modX::LOG_LEVEL_WARN,
        '[ModxComments] Package metadata file is missing/unreadable: '
        .$path.'. Using embedded fallback.'
    );

    return $fallback;
};

$builder=new modPackageBuilder($modx);
$builder->createPackage('modxcomments','1.0.0','beta2');
$builder->registerNamespace(
    'modxcomments',
    false,
    true,
    '{core_path}components/modxcomments/'
);

$category=$modx->newObject('modCategory');
$category->set('category','ModxComments');

$snippetSource=file_get_contents(
    $root.'/core/components/modxcomments/elements/snippets/snippet.modxcomments.php'
);
$snippetSource=preg_replace('/^\s*<\?(?:php)?\s*/i','',$snippetSource);
$snippetSource=preg_replace('/\?>\s*$/','',$snippetSource);

$snippet=$modx->newObject('modSnippet');
$snippet->fromArray(array(
    'name'=>'ModxComments',
    'description'=>'AJAX-first comments for MODX Revolution 2.8 and 3.x.',
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
    $chunk=$modx->newObject('modChunk');
    $chunk->fromArray(array(
        'name'=>$chunkName,
        'description'=>'ModxComments email notification template.',
        'snippet'=>file_get_contents(
            $root.'/core/components/modxcomments/elements/chunks/'.$chunkFile
        ),
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
    'license'=>$readPackageText(
        $root.'/LICENSE',
        "ModxComments\n\nCopyright (c) 2026 web86.\nAll rights reserved.\n"
    ),
    'readme'=>$readPackageText(
        $root.'/docs/INSTALL.md',
        "ModxComments 1.0.0-beta2\nSupports MODX Revolution 2.8.x and 3.x.\n"
    ),
    'changelog'=>$readPackageText(
        $root.'/CHANGELOG.md',
        "ModxComments 1.0.0-beta2\nUnified MODX 2.8 + MODX 3 package.\n"
    ),
    'requires'=>array(
        'php'=>'>=7.4.0',
        'modx'=>'>=2.8.0,<3.3.0',
    ),
));

$builder->pack();

echo "Built universal ModxComments 1.0.0-beta2 transport package for MODX 2.8 + MODX 3.0-3.2.\n";
