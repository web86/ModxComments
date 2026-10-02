<?php
/** @var modX $modx */
$resourceId = isset($resource) ? (int) $resource : ($modx->resource ? (int) $modx->resource->get('id') : 0);
$contextKey = isset($context) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $context) : ($modx->context ? $modx->context->key : 'web');
$assetsUrl = rtrim($modx->getOption('modxcomments.assets_url', null, $modx->getOption('assets_url') . 'components/modxcomments/'), '/') . '/';
$apiUrl = $assetsUrl . 'connector.php';

$modx->regClientCSS($assetsUrl . 'css/comments.css');
$modx->regClientScript($assetsUrl . 'js/comments.js');

return '<div class="modx-comments"'
    . ' data-modx-comments'
    . ' data-resource="' . $resourceId . '"'
    . ' data-context="' . htmlspecialchars($contextKey, ENT_QUOTES, 'UTF-8') . '"'
    . ' data-api="' . htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') . '"></div>';
