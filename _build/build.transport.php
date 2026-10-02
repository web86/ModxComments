<?php
$root=dirname(__DIR__);

require_once $root.'/config.core.php';
require_once MODX_CORE_PATH.'config/'.MODX_CONFIG_KEY.'.inc.php';
require_once MODX_CORE_PATH.'model/modx/modx.class.php';
require_once MODX_CORE_PATH.'model/modx/transport/modpackagebuilder.class.php';

$modx=new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

/*
 * Always generate the xPDO model before packing. This makes a clean checkout
 * sufficient for building an installable package; running build.schema.php
 * manually is no longer required.
 */
$corePath=$root.'/core/components/modxcomments/';
$modelPath=$corePath.'model/';
$schema=$modelPath.'schema/modxcomments.mysql.schema.xml';

$manager=$modx->getManager();
$generator=$manager->getGenerator();
if(!$generator->parseSchema($schema,$modelPath)){
    fwrite(STDERR,"Could not parse ModxComments schema\n");
    exit(1);
}

$builder=new modPackageBuilder($modx);
$builder->createPackage('modxcomments','0.2.0','beta2');
$builder->registerNamespace('modxcomments',false,true,'{core_path}components/modxcomments/');

$category=$modx->newObject('modCategory');
$category->set('category','ModxComments');

$snippet=$modx->newObject('modSnippet');
$snippet->fromArray(array(
    'name'=>'ModxComments',
    'description'=>'AJAX-first comments for MODX resources.',
    'snippet'=>file_get_contents($root.'/core/components/modxcomments/elements/snippets/snippet.modxcomments.php'),
),'',true,true);
$category->addMany($snippet);

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
    ),
));

$vehicle->resolve('file',array(
    'source'=>$root.'/core/components/modxcomments/',
    'target'=>"return MODX_CORE_PATH . 'components/modxcomments/';",
));
$vehicle->resolve('file',array(
    'source'=>$root.'/assets/components/modxcomments/',
    'target'=>"return MODX_ASSETS_PATH . 'components/modxcomments/';",
));
$vehicle->resolve('php',array(
    'source'=>$root.'/_build/resolvers/resolve.install.php',
));

$builder->putVehicle($vehicle);
$builder->setPackageAttributes(array(
    'license'=>file_exists($root.'/LICENSE')?file_get_contents($root.'/LICENSE'):'',
    'readme'=>file_get_contents($root.'/README.md'),
    'changelog'=>file_exists($root.'/CHANGELOG.md')?file_get_contents($root.'/CHANGELOG.md'):'',
));
$builder->pack();

echo "Built ModxComments 0.2.0-beta2 transport package.\n";
