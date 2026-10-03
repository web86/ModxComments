<?php
/**
 * Namespace bootstrap for the unified package.
 *
 * MODX 3 uses this to register the namespaced xPDO model.
 * MODX 2 keeps using the legacy model package.
 */
if(class_exists('MODX\\Revolution\\modX')){
    $modx->addPackage(
        'ModxComments\\Model',
        $namespace['path'].'src/',
        null,
        'ModxComments\\'
    );
}
