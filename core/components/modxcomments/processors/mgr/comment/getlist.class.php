<?php
class ModxCommentsMgrCommentGetListProcessor extends modObjectGetListProcessor
{
    public $classKey='ModxCommentsComment';
    public $languageTopics=array('modxcomments:default');
    public $defaultSortField='thread_id';
    public $defaultSortDirection='DESC';

    public function initialize()
    {
        $corePath=$this->modx->getOption('modxcomments.core_path',null,MODX_CORE_PATH.'components/modxcomments/');
        $this->modx->addPackage('modxcomments',$corePath.'model/');
        return parent::initialize();
    }

    public function prepareQueryBeforeCount(xPDOQuery $c)
    {
        $status=trim((string)$this->getProperty('status',''));
        $query=trim((string)$this->getProperty('query',''));

        if($status!==''){
            $c->where(array('status'=>$status));
        }

        if($query!==''){
            $c->where(array(
                'author_name:LIKE'=>'%'.$query.'%',
                'OR:author_email:LIKE'=>'%'.$query.'%',
                'OR:content:LIKE'=>'%'.$query.'%',
            ));
        }

        return $c;
    }

    public function getData()
    {
        $data=array();
        $limit=(int)$this->getProperty('limit',20);
        $start=(int)$this->getProperty('start',0);

        $base=$this->modx->newQuery($this->classKey);
        $base=$this->prepareQueryBeforeCount($base);
        $data['total']=$this->modx->getCount($this->classKey,$base);

        $rootQuery=$this->modx->newQuery($this->classKey);
        $rootQuery=$this->prepareQueryBeforeCount($rootQuery);
        $rootQuery->where(array('parent_id'=>0));
        $rootQuery->sortby('createdon','DESC');

        if($limit>0){
            $rootQuery->limit($limit,$start);
        }

        $roots=$this->modx->getCollection($this->classKey,$rootQuery);
        $results=array();

        foreach($roots as $root){
            $threadId=(int)$root->get('thread_id');
            if($threadId<1){
                $threadId=(int)$root->get('id');
            }

            $threadQuery=$this->modx->newQuery($this->classKey);
            $threadQuery->where(array('thread_id'=>$threadId));
            $threadQuery->sortby('path','ASC');

            foreach($this->modx->getCollection($this->classKey,$threadQuery) as $comment){
                $results[]=$comment;
            }
        }

        $data['results']=$results;
        return $data;
    }

    public function prepareRow(xPDOObject $object)
    {
        $row=$object->toArray();

        $resource=$this->modx->getObject('modResource',(int)$object->get('resource_id'));
        $row['resource_title']=$resource
            ? $resource->get('pagetitle').' (#'.$resource->get('id').')'
            : '#'.$object->get('resource_id');

        $text=trim(preg_replace('/\s+/u',' ',strip_tags((string)$row['content'])));
        $row['content']=function_exists('mb_substr')
            ? mb_substr($text,0,320,'UTF-8')
            : substr($text,0,320);

        $row['parent_author']='';
        $row['parent_excerpt']='';
        $row['is_admin']=false;

        $parentId=(int)$object->get('parent_id');
        if($parentId>0){
            $parent=$this->modx->getObject('ModxCommentsComment',$parentId);
            if($parent){
                $row['parent_author']=(string)$parent->get('author_name');
                $parentText=trim(preg_replace('/\s+/u',' ',strip_tags((string)$parent->get('content'))));
                $row['parent_excerpt']=function_exists('mb_substr')
                    ? mb_substr($parentText,0,120,'UTF-8')
                    : substr($parentText,0,120);
            }
        }

        $userId=(int)$object->get('user_id');
        if($userId>0){
            $user=$this->modx->getObject('modUser',$userId);
            if($user){
                $row['is_admin']=(bool)$user->get('sudo') || $user->isMember('Administrator');
            }
        }

        return $row;
    }
}
return 'ModxCommentsMgrCommentGetListProcessor';
