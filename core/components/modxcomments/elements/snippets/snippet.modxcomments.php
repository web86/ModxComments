<?php
/** @var modX $modx */
$resourceId = isset($resource) ? (int) $resource : ($modx->resource ? (int) $modx->resource->get('id') : 0);
$contextKey = isset($context) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $context) : ($modx->context ? $modx->context->key : 'web');
if ($contextKey === '' || strtolower($contextKey) === 'mgr') {
    $contextKey = 'web';
}
$assetsUrl = rtrim($modx->getOption('modxcomments.assets_url', null, $modx->getOption('assets_url') . 'components/modxcomments/'), '/') . '/';
$apiUrl = $assetsUrl . 'connector.php';

$signingKey = trim((string) $modx->getOption('modxcomments.resource_signing_key', null, ''));

if ($signingKey === '') {
    $versionData = @include MODX_CORE_PATH . 'docs/version.inc.php';
    $fullVersion = is_array($versionData) && !empty($versionData['full_version'])
        ? (string) $versionData['full_version']
        : (is_array($versionData) && !empty($versionData['version']) ? (string) $versionData['version'] : '2.0.0');
    $isModx3 = version_compare($fullVersion, '3.0.0', '>=');
    $settingClass = $isModx3
        ? 'MODX\\Revolution\\modSystemSetting'
        : 'modSystemSetting';
    $setting = $modx->getObject($settingClass, 'modxcomments.resource_signing_key');

    if (!$setting) {
        $setting = $modx->newObject($settingClass);
        if ($setting) {
            $setting->set('key', 'modxcomments.resource_signing_key');
            $setting->set('namespace', 'modxcomments');
            $setting->set('area', 'modxcomments');
            $setting->set('xtype', 'text-password');
        }
    }

    if ($setting && trim((string) $setting->get('value')) === '') {
        try {
            $setting->set('value', bin2hex(random_bytes(32)));
        } catch (Throwable $e) {
            $setting->set('value', hash('sha256', uniqid('', true) . mt_rand()));
        }
    }

    if ($setting && $setting->save()) {
        $signingKey = trim((string) $setting->get('value'));
        $cacheManager = $modx->getCacheManager();
        if ($cacheManager) {
            $cacheManager->refresh(array(
                'system_settings' => array(),
                'context_settings' => array(),
                'resource' => array(),
            ));
        }
    }
}

if ($signingKey === '') {
    if (!isset($isModx3)) {
        $versionData = @include MODX_CORE_PATH . 'docs/version.inc.php';
        $fullVersion = is_array($versionData) && !empty($versionData['full_version'])
            ? (string) $versionData['full_version']
            : (is_array($versionData) && !empty($versionData['version']) ? (string) $versionData['version'] : '2.0.0');
        $isModx3 = version_compare($fullVersion, '3.0.0', '>=');
    }
    $logLevel = $isModx3
        ? constant('MODX\\Revolution\\modX::LOG_LEVEL_ERROR')
        : constant('modX::LOG_LEVEL_ERROR');
    $modx->log($logLevel, '[ModxComments] Resource signing key is missing; comments widget was not initialized.');

    return '<div class="modx-comments mc-status mc-status-error">'
        . 'ModxComments configuration error: resource signing key is missing.'
        . '</div>';
}

$resourceToken = hash_hmac('sha256', $resourceId . '|' . $contextKey, $signingKey);

$modx->regClientCSS($assetsUrl . 'css/comments.css');
$modx->regClientScript($assetsUrl . 'js/comments.js');

return '<div class="modx-comments"'
    . ' data-modx-comments'
    . ' data-resource="' . $resourceId . '"'
    . ' data-context="' . htmlspecialchars($contextKey, ENT_QUOTES, 'UTF-8') . '"'
    . ' data-resource-token="' . htmlspecialchars($resourceToken, ENT_QUOTES, 'UTF-8') . '"'
    . ' data-api="' . htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') . '"></div>';
