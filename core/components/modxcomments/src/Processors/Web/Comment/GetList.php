<?php
namespace ModxComments\Processors\Web\Comment;

use MODX\Revolution\Processors\Processor;

class GetList extends Processor
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
            $page=max(1,(int)$this->getProperty('page',1));
            $perPage=(int)$this->getProperty('per_page',0);
            if($perPage<1) $perPage=null;

            return $this->success('',$this->comments->getComments($resource,$context,$page,$perPage,(string)$this->getProperty('resource_token','')));
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
