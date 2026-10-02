<?php
/**
 * Development helper: parse the schema, generate model classes/maps, and create the table.
 * Run from the MODX site root: php _build/build.schema.php
 */
$root = dirname(__DIR__);
require_once $root . '/config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

$corePath = MODX_CORE_PATH . 'components/modxcomments/';
$modelPath = $corePath . 'model/';
$schema = $modelPath . 'schema/modxcomments.mysql.schema.xml';

$manager = $modx->getManager();
$generator = $manager->getGenerator();

if (!$generator->parseSchema($schema, $modelPath)) {
    fwrite(STDERR, "Could not parse schema\n");
    exit(1);
}

if (!$modx->addPackage('modxcomments', $modelPath)) {
    fwrite(STDERR, "Could not add package\n");
    exit(1);
}

if (!$manager->createObjectContainer('ModxCommentsComment')) {
    fwrite(STDERR, "Could not create comments table (it may already exist).\n");
}

echo "ModxComments schema/model ready.\n";
