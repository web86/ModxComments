<?php
/**
 * Runtime service router for the unified MODX 2/3 package.
 */
$versionData = @include MODX_CORE_PATH . 'docs/version.inc.php';
$fullVersion = is_array($versionData) && !empty($versionData['full_version'])
    ? (string) $versionData['full_version']
    : (is_array($versionData) && !empty($versionData['version']) ? (string) $versionData['version'] : '2.0.0');
$isModx3 = version_compare($fullVersion, '3.0.0', '>=');

$serviceFile = dirname(dirname(__DIR__))
    . '/compat/'
    . ($isModx3 ? 'modx3' : 'modx2')
    . '/service.class.php';

require_once $serviceFile;
