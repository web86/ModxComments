<?php
namespace ModxComments\Processors\Web\Comment;

use MODX\Revolution\Processors\Processor;

class Count extends Processor
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
        try{
            $resource=(int)$this->getProperty('resource',0);
            $context=$this->comments->cleanContextKey($this->getProperty('context','web'));

            return $this->success('',[
                'total'=>$this->comments->getCommentCount($resource,$context,(string)$this->getProperty('resource_token','')),
            ]);
        }catch(\InvalidArgumentException $e){
            return $this->failure($e->getMessage());
        }catch(\RuntimeException $e){
            return $this->failure($e->getMessage());
        }catch(\Throwable $e){
            $this->modx->log(\MODX\Revolution\modX::LOG_LEVEL_ERROR,'[ModxComments] Public API error: '.$e->getMessage());
            return $this->failure('server_error');
        }
    }
}
