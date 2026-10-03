<?php
/**
 * Runtime service router for the unified MODX 2/3 package.
 */
$isModx3 = class_exists('MODX\\Revolution\\modX');

$serviceFile = dirname(dirname(__DIR__))
    . '/compat/'
    . ($isModx3 ? 'modx3' : 'modx2')
    . '/service.class.php';

require_once $serviceFile;
