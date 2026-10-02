<?php
class ModxCommentsMgrCommentStatusProcessor extends modProcessor
{
    public function process()
    {
        $id=(int)$this->getProperty('id',0);
        $status=(string)$this->getProperty('status','');
        if(!in_array($status,array('published','pending','spam'),true)) return $this->failure('invalid_status');

        $corePath=$this->modx->getOption('modxcomments.core_path',null,MODX_CORE_PATH.'components/modxcomments/');
        $this->modx->addPackage('modxcomments',$corePath.'model/');
        $comment=$this->modx->getObject('ModxCommentsComment',$id);
        if(!$comment) return $this->failure('comment_not_found');

        $comment->set('status',$status);
        $comment->set('deletedon',null);
        return $comment->save() ? $this->success() : $this->failure('comment_save_failed');
    }
}
return 'ModxCommentsMgrCommentStatusProcessor';
