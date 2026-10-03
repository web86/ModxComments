<?php
namespace ModxComments\Processors\Web\Comment;

use MODX\Revolution\Processors\Processor;

class Delete extends Processor
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
        $token=isset($_SERVER['HTTP_X_MODXCOMMENTS_CSRF'])
            ? (string)$_SERVER['HTTP_X_MODXCOMMENTS_CSRF']
            : (string)$this->getProperty('_csrf','');

        if(!$this->comments->validateCsrfToken($token)) return $this->failure('csrf_invalid');

        try{
            return $this->success('',['comment'=>$this->comments->deleteComment((int)$this->getProperty('id',0))]);
        }catch(\Throwable $e){
            return $this->failure($e->getMessage());
        }
    }
}
