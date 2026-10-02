<?php
class ModxcommentsIndexManagerController extends modExtraManagerController
{
    public function getLanguageTopics()
    {
        return array('modxcomments:default');
    }

    public function checkPermissions()
    {
        return true;
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
