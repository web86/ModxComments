<?php
class ModxCommentsMgrCommentRemoveProcessor extends modProcessor
{
    public function process()
    {
        if (
            !$this->modx->user
            || !(
                (bool) $this->modx->user->get('sudo')
                || $this->modx->user->isMember('Administrator')
            )
        ) {
            return $this->failure('access_denied');
        }
        $id=(int)$this->getProperty('id',0);
        $corePath=$this->modx->getOption('modxcomments.core_path',null,MODX_CORE_PATH.'components/modxcomments/');
        $this->modx->addPackage('modxcomments',$corePath.'model/');
        $comment=$this->modx->getObject('ModxCommentsComment',$id);
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
