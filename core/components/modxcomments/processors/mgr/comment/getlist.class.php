<?php
class ModxCommentsMgrCommentGetListProcessor extends modObjectGetListProcessor
{
    public $classKey='ModxCommentsComment';
    public $languageTopics=array('modxcomments:default');
    public $defaultSortField='thread_id';
    public $defaultSortDirection='DESC';

    public function initialize()
    {
        if (
            !$this->modx->user
            || !(
                (bool) $this->modx->user->get('sudo')
                || $this->modx->user->isMember('Administrator')
            )
        ) {
            return 'access_denied';
        }
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

        $c=$this->modx->newQuery($this->classKey);
        $c=$this->prepareQueryBeforeCount($c);
        $data['total']=$this->modx->getCount($this->classKey,$c);

        /*
         * Newest threads first, but preserve materialized-path order inside
         * each thread so parent/reply structure remains visually coherent.
         */
        $c->sortby('thread_id','DESC');
        $c->sortby('path','ASC');

        if($limit>0){
            $c->limit($limit,$start);
        }

        $data['results']=$this->modx->getCollection($this->classKey,$c);
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
