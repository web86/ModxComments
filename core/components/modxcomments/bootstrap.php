<?php
/**
 * Namespace bootstrap for the unified package.
 *
 * MODX 3 uses this to register the namespaced xPDO model.
 * MODX 2 keeps using the legacy model package.
 */
$versionData=@include MODX_CORE_PATH.'docs/version.inc.php';
$fullVersion=is_array($versionData) && !empty($versionData['full_version'])
    ? (string)$versionData['full_version']
    : (is_array($versionData) && !empty($versionData['version']) ? (string)$versionData['version'] : '2.0.0');

if(version_compare($fullVersion,'3.0.0','>=')){
    $modx->addPackage(
        'ModxComments\\Model',
        $namespace['path'].'src/',
        null,
        'ModxComments\\'
    );
}
