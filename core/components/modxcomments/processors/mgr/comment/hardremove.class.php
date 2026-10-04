<?php
class ModxCommentsMgrCommentHardRemoveProcessor extends modProcessor
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
        if($id<1){
            return $this->failure('comment_not_found');
        }

        $corePath=$this->modx->getOption(
            'modxcomments.core_path',
            null,
            MODX_CORE_PATH.'components/modxcomments/'
        );
        $this->modx->addPackage('modxcomments',$corePath.'model/');

        $comment=$this->modx->getObject('ModxCommentsComment',$id);
        if(!$comment){
            return $this->failure('comment_not_found');
        }

        $ids=array($id);
        $frontier=array($id);

        while(!empty($frontier)){
            $children=$this->modx->getCollection(
                'ModxCommentsComment',
                array('parent_id:IN'=>$frontier)
            );

            $next=array();
            foreach($children as $child){
                $childId=(int)$child->get('id');
                if($childId>0 && !in_array($childId,$ids,true)){
                    $ids[]=$childId;
                    $next[]=$childId;
                }
            }

            $frontier=$next;
        }

        $commentTable=$this->modx->getTableName('ModxCommentsComment');
        $voteTable=$this->modx->getTableName('ModxCommentsVote');
        $idList=implode(',',array_map('intval',$ids));

        if(!$commentTable || !$voteTable || $idList===''){
            return $this->failure('comment_delete_failed');
        }

        try{
            $this->modx->exec('START TRANSACTION');

            $votesDeleted=$this->modx->exec(
                'DELETE FROM '.$voteTable.' WHERE comment_id IN ('.$idList.')'
            );
            if($votesDeleted===false){
                throw new RuntimeException('vote_delete_failed');
            }

            $commentsDeleted=$this->modx->exec(
                'DELETE FROM '.$commentTable.' WHERE id IN ('.$idList.')'
            );
            if($commentsDeleted===false){
                throw new RuntimeException('comment_delete_failed');
            }

            $this->modx->exec('COMMIT');
        }catch(Throwable $e){
            try{
                $this->modx->exec('ROLLBACK');
            }catch(Throwable $rollbackError){
            }

            $this->modx->log(
                modX::LOG_LEVEL_ERROR,
                '[ModxComments] Permanent comment deletion failed: '.$e->getMessage()
            );
            return $this->failure('comment_delete_failed');
        }

        return $this->success('',array(
            'deleted'=>(int)$commentsDeleted,
            'comment_ids'=>$ids,
        ));
    }
}
return 'ModxCommentsMgrCommentHardRemoveProcessor';
