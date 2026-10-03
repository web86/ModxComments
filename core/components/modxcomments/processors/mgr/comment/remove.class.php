<?php
use MODX\Revolution\Processors\Processor;
use ModxComments\Model\Comment;

class ModxCommentsMgrCommentRemoveProcessor extends Processor
{
    public function process()
    {
        $id=(int)$this->getProperty('id',0);
        $corePath=$this->modx->getOption('modxcomments.core_path',null,MODX_CORE_PATH.'components/modxcomments/');
        $this->modx->addPackage('ModxComments\\Model',$corePath.'src/',null,'ModxComments\\');
        $comment=$this->modx->getObject(Comment::class,$id);
        if(!$comment) return $this->failure('comment_not_found');

        $comment->set('status','deleted');
        $comment->set('deletedon',date('Y-m-d H:i:s'));
        $comment->set('content','');
        $comment->set('content_html','');
        $comment->set('content_hash','');
        return $comment->save() ? $this->success() : $this->failure('comment_delete_failed');
    }
}
return 'ModxCommentsMgrCommentRemoveProcessor';
