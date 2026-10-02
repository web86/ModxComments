<?php
class ModxCommentsMgrCommentGetListProcessor extends modObjectGetListProcessor
{
    public $classKey='ModxCommentsComment';
    public $languageTopics=array('modxcomments:default');
    public $defaultSortField='createdon';
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
        if($status!=='') $c->where(array('status'=>$status));
        if($query!=='') $c->where(array('author_name:LIKE'=>'%'.$query.'%','OR:content:LIKE'=>'%'.$query.'%'));
        return $c;
    }

    public function prepareRow(xPDOObject $object)
    {
        $row=$object->toArray();
        $resource=$this->modx->getObject('modResource',(int)$object->get('resource_id'));
        $row['resource_title']=$resource ? $resource->get('pagetitle').' (#'.$resource->get('id').')' : '#'.$object->get('resource_id');
        $text=strip_tags((string)$row['content']);
        $row['content']=function_exists('mb_substr') ? mb_substr($text,0,240,'UTF-8') : substr($text,0,240);
        return $row;
    }
}
return 'ModxCommentsMgrCommentGetListProcessor';
