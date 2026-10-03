<?php
use MODX\Revolution\Processors\Processor;
use MODX\Revolution\modX;

class ModxCommentsWebInitProcessor extends Processor
{
    /** @var ModxComments */
    protected $comments;

    public function initialize()
    {
        $corePath = $this->modx->getOption('modxcomments.core_path', null, MODX_CORE_PATH . 'components/modxcomments/');
        require_once $corePath . 'model/modxcomments/modxcomments.class.php';
        $this->comments = new ModxComments($this->modx);
        return true;
    }

    public function process()
    {
        return $this->success('', array(
            'csrf' => $this->comments->getCsrfToken(),
            'user' => $this->comments->getCurrentUser(),
            'settings' => $this->comments->getPublicConfig(),
            'i18n' => $this->comments->getFrontendLexicon(),
        ));
    }
}
return 'ModxCommentsWebInitProcessor';
