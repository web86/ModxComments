<?php
/**
 * Compatibility fallback for MODX 2 manager installations that unexpectedly
 * fall back to modManagerControllerDeprecated.
 *
 * Normal routing should use controllers/default/index.class.php or
 * controllers/index.class.php.
 */

/** @var modX $modx */
$modx->lexicon->load('modxcomments:default');

$assetsUrl = $modx->getOption(
    'modxcomments.assets_url',
    null,
    MODX_ASSETS_URL . 'components/modxcomments/'
);

if (isset($this) && method_exists($this, 'addCss')) {
    $this->addCss($assetsUrl . 'mgr/css/mgr.css');
}
if (isset($this) && method_exists($this, 'addJavascript')) {
    $this->addJavascript($assetsUrl . 'mgr/js/mgr.js');
}
if (isset($this) && method_exists($this, 'addHtml')) {
    $this->addHtml(
        '<script type="text/javascript">Ext.onReady(function(){'
        . 'MODx.add({xtype:"modxcomments-panel-home"});'
        . '});</script>'
    );
}

return '<div id="modxcomments-panel-home-div"></div>';
