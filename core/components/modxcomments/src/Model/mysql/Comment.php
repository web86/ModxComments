<?php
namespace ModxComments\Model\mysql;

class Comment extends \ModxComments\Model\Comment
{
    public static $metaMap = array(
        'package' => 'ModxComments\\Model\\',
        'version' => '3.0',
        'table' => 'modxcomments_comments',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => array(
            'engine' => 'InnoDB',
        ),
        'fields' => array(
            'resource_id' => 0,
            'context_key' => 'web',
            'parent_id' => 0,
            'thread_id' => 0,
            'depth' => 0,
            'path' => '',
            'user_id' => 0,
            'guest_owner_hash' => '',
            'author_name' => '',
            'author_email' => '',
            'content' => '',
            'content_html' => '',
            'content_hash' => '',
            'status' => 'published',
            'createdon' => NULL,
            'editedon' => NULL,
            'deletedon' => NULL,
            'reply_notifiedon' => NULL,
            'ip_hash' => '',
            'user_agent_hash' => '',
        ),
        'fieldMeta' => array(
            'resource_id' => array('dbtype'=>'int','precision'=>'10','attributes'=>'unsigned','phptype'=>'integer','null'=>false,'default'=>0),
            'context_key' => array('dbtype'=>'varchar','precision'=>'100','phptype'=>'string','null'=>false,'default'=>'web'),
            'parent_id' => array('dbtype'=>'int','precision'=>'10','attributes'=>'unsigned','phptype'=>'integer','null'=>false,'default'=>0),
            'thread_id' => array('dbtype'=>'int','precision'=>'10','attributes'=>'unsigned','phptype'=>'integer','null'=>false,'default'=>0),
            'depth' => array('dbtype'=>'tinyint','precision'=>'3','attributes'=>'unsigned','phptype'=>'integer','null'=>false,'default'=>0),
            'path' => array('dbtype'=>'varchar','precision'=>'255','phptype'=>'string','null'=>false,'default'=>''),
            'user_id' => array('dbtype'=>'int','precision'=>'10','attributes'=>'unsigned','phptype'=>'integer','null'=>false,'default'=>0),
            'guest_owner_hash' => array('dbtype'=>'char','precision'=>'64','phptype'=>'string','null'=>false,'default'=>''),
            'author_name' => array('dbtype'=>'varchar','precision'=>'190','phptype'=>'string','null'=>false,'default'=>''),
            'author_email' => array('dbtype'=>'varchar','precision'=>'254','phptype'=>'string','null'=>false,'default'=>''),
            'content' => array('dbtype'=>'text','phptype'=>'string','null'=>false),
            'content_html' => array('dbtype'=>'text','phptype'=>'string','null'=>false),
            'content_hash' => array('dbtype'=>'char','precision'=>'64','phptype'=>'string','null'=>false,'default'=>''),
            'status' => array('dbtype'=>'varchar','precision'=>'32','phptype'=>'string','null'=>false,'default'=>'published'),
            'createdon' => array('dbtype'=>'datetime','phptype'=>'datetime','null'=>false),
            'editedon' => array('dbtype'=>'datetime','phptype'=>'datetime','null'=>true,'default'=>NULL),
            'deletedon' => array('dbtype'=>'datetime','phptype'=>'datetime','null'=>true,'default'=>NULL),
            'reply_notifiedon' => array('dbtype'=>'datetime','phptype'=>'datetime','null'=>true,'default'=>NULL),
            'ip_hash' => array('dbtype'=>'char','precision'=>'64','phptype'=>'string','null'=>false,'default'=>''),
            'user_agent_hash' => array('dbtype'=>'char','precision'=>'64','phptype'=>'string','null'=>false,'default'=>''),
        ),
        'indexes' => array(
            'resource_context_status' => array(
                'alias'=>'resource_context_status','primary'=>false,'unique'=>false,'type'=>'BTREE',
                'columns'=>array(
                    'resource_id'=>array('length'=>'','collation'=>'A','null'=>false),
                    'context_key'=>array('length'=>'','collation'=>'A','null'=>false),
                    'status'=>array('length'=>'','collation'=>'A','null'=>false),
                ),
            ),
            'parent_id' => array(
                'alias'=>'parent_id','primary'=>false,'unique'=>false,'type'=>'BTREE',
                'columns'=>array('parent_id'=>array('length'=>'','collation'=>'A','null'=>false)),
            ),
            'thread_path' => array(
                'alias'=>'thread_path','primary'=>false,'unique'=>false,'type'=>'BTREE',
                'columns'=>array(
                    'thread_id'=>array('length'=>'','collation'=>'A','null'=>false),
                    'path'=>array('length'=>'','collation'=>'A','null'=>false),
                ),
            ),
            'user_id' => array(
                'alias'=>'user_id','primary'=>false,'unique'=>false,'type'=>'BTREE',
                'columns'=>array('user_id'=>array('length'=>'','collation'=>'A','null'=>false)),
            ),
            'ip_created' => array(
                'alias'=>'ip_created','primary'=>false,'unique'=>false,'type'=>'BTREE',
                'columns'=>array(
                    'ip_hash'=>array('length'=>'','collation'=>'A','null'=>false),
                    'createdon'=>array('length'=>'','collation'=>'A','null'=>false),
                ),
            ),
        ),
    );
}
