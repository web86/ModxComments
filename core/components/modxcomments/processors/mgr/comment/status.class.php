<?php
class ModxCommentsMgrCommentStatusProcessor extends modProcessor
{
    public function process()
    {
        $id=(int)$this->getProperty('id',0);
        $status=(string)$this->getProperty('status','');

        if(!in_array($status,array('published','pending','spam'),true)){
            return $this->failure('invalid_status');
        }

        $corePath=$this->modx->getOption(
            'modxcomments.core_path',
            null,
            MODX_CORE_PATH.'components/modxcomments/'
        );

        require_once $corePath.'model/modxcomments/modxcomments.class.php';
        $comments=new ModxComments($this->modx);

        $comment=$this->modx->getObject('ModxCommentsComment',$id);
        if(!$comment){
            return $this->failure('comment_not_found');
        }

        $oldStatus=(string)$comment->get('status');
        $comment->set('status',$status);
        $comment->set('deletedon',null);

        if(!$comment->save()){
            return $this->failure('comment_save_failed');
        }

        if($status==='published' && $oldStatus!=='published'){
            $comments->notifyReplyAuthor($comment);
            $this->modx->invokeEvent('ModxCommentsOnCommentPublish',array(
                'comment'=>$comment,
                'service'=>$comments,
            ));
        }

        return $this->success();
    }
}
return 'ModxCommentsMgrCommentStatusProcessor';
