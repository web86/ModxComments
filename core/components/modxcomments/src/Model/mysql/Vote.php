<?php
namespace ModxComments\Model\mysql;

class Vote extends \ModxComments\Model\Vote
{
    public static $metaMap = array(
        'package' => 'ModxComments\\Model\\',
        'version' => '3.0',
        'table' => 'modxcomments_votes',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => array(
            'engine' => 'InnoDB',
        ),
        'fields' => array(
            'comment_id' => 0,
            'voter_hash' => '',
            'value' => 1,
            'createdon' => NULL,
        ),
        'fieldMeta' => array(
            'comment_id' => array('dbtype'=>'int','precision'=>'10','attributes'=>'unsigned','phptype'=>'integer','null'=>false,'default'=>0),
            'voter_hash' => array('dbtype'=>'char','precision'=>'64','phptype'=>'string','null'=>false,'default'=>''),
            'value' => array('dbtype'=>'tinyint','precision'=>'2','phptype'=>'integer','null'=>false,'default'=>1),
            'createdon' => array('dbtype'=>'datetime','phptype'=>'datetime','null'=>false),
        ),
        'indexes' => array(
            'comment_voter' => array(
                'alias'=>'comment_voter','primary'=>false,'unique'=>true,'type'=>'BTREE',
                'columns'=>array(
                    'comment_id'=>array('length'=>'','collation'=>'A','null'=>false),
                    'voter_hash'=>array('length'=>'','collation'=>'A','null'=>false),
                ),
            ),
            'comment_value' => array(
                'alias'=>'comment_value','primary'=>false,'unique'=>false,'type'=>'BTREE',
                'columns'=>array(
                    'comment_id'=>array('length'=>'','collation'=>'A','null'=>false),
                    'value'=>array('length'=>'','collation'=>'A','null'=>false),
                ),
            ),
        ),
    );
}
