<?php
$versionData=@include MODX_CORE_PATH.'docs/version.inc.php';
$fullVersion=is_array($versionData) && !empty($versionData['full_version'])
    ? (string)$versionData['full_version']
    : (is_array($versionData) && !empty($versionData['version']) ? (string)$versionData['version'] : '2.0.0');
$isModx3=version_compare($fullVersion,'3.0.0','>=');

$baseControllerClass=$isModx3
    ? 'MODX\\Revolution\\modExtraManagerController'
    : 'modExtraManagerController';

if(!class_exists('ModxCommentsManagerControllerBase',false)){
    class_alias($baseControllerClass,'ModxCommentsManagerControllerBase');
}

class ModxcommentsIndexManagerController extends ModxCommentsManagerControllerBase
{
    public function getLanguageTopics()
    {
        return array('modxcomments:default');
    }

    public function checkPermissions()
    {
        if (!$this->modx->user) return false;

        return (bool) $this->modx->user->get('sudo')
            || $this->modx->user->isMember('Administrator');
    }

    public function getPageTitle()
    {
        return $this->modx->lexicon('modxcomments');
    }

    public function loadCustomCssJs()
    {
        $assetsUrl=$this->modx->getOption(
            'modxcomments.assets_url',
            null,
            MODX_ASSETS_URL.'components/modxcomments/'
        );

        $this->addCss($assetsUrl.'mgr/css/mgr.css');
        $this->addJavascript($assetsUrl.'mgr/js/mgr.js');
        $this->addHtml(
            '<script type="text/javascript">Ext.onReady(function(){'
            .'MODx.add({xtype:"modxcomments-panel-home"});'
            .'});</script>'
        );
    }

    public function getTemplateFile()
    {
        return $this->modx->getOption(
            'modxcomments.core_path',
            null,
            MODX_CORE_PATH.'components/modxcomments/'
        ).'elements/templates/home.tpl';
    }
}
