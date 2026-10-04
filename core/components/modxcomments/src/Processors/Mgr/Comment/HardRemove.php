<?php
namespace ModxComments\Processors\Mgr\Comment;

use MODX\Revolution\Processors\Processor;
use ModxComments\Model\Comment;
use ModxComments\Model\Vote;
use RuntimeException;
use Throwable;

class HardRemove extends Processor
{
    public function process()
    {
        if (
            !$this->modx->user
            || !(
                (bool)$this->modx->user->get('sudo')
                || $this->modx->user->isMember('Administrator')
            )
        ) {
            return $this->failure('access_denied');
        }

        $id=(int)$this->getProperty('id',0);
        if($id<1){
            return $this->failure('comment_not_found');
        }

        $comment=$this->modx->getObject(Comment::class,$id);
        if(!$comment){
            return $this->failure('comment_not_found');
        }

        $ids=[$id];
        $frontier=[$id];

        while(!empty($frontier)){
            $children=$this->modx->getCollection(
                Comment::class,
                ['parent_id:IN'=>$frontier]
            );

            $next=[];
            foreach($children as $child){
                $childId=(int)$child->get('id');
                if($childId>0 && !in_array($childId,$ids,true)){
                    $ids[]=$childId;
                    $next[]=$childId;
                }
            }

            $frontier=$next;
        }

        $commentTable=$this->modx->getTableName(Comment::class);
        $voteTable=$this->modx->getTableName(Vote::class);
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
                \MODX\Revolution\modX::LOG_LEVEL_ERROR,
                '[ModxComments] Permanent comment deletion failed: '.$e->getMessage()
            );
            return $this->failure('comment_delete_failed');
        }

        return $this->success('',[
            'deleted'=>(int)$commentsDeleted,
            'comment_ids'=>$ids,
        ]);
    }
}
