<?php
namespace ModxComments\Processors\Web;

use MODX\Revolution\Processors\Processor;

class Init extends Processor
{
    protected $comments;

    public function initialize()
    {
        $corePath=$this->modx->getOption('modxcomments.core_path',null,MODX_CORE_PATH.'components/modxcomments/');
        require_once $corePath.'model/modxcomments/modxcomments.class.php';
        $this->comments=new \ModxComments($this->modx);
        return true;
    }

    public function process()
    {
        return $this->success('',[
            'csrf'=>$this->comments->getCsrfToken(),
            'user'=>$this->comments->getCurrentUser(),
            'settings'=>$this->comments->getPublicConfig(),
            'i18n'=>$this->comments->getFrontendLexicon(),
        ]);
    }
}
