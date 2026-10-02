<?php
if(!isset($object)||!($object instanceof modCategory)) return true;

$modx=$object->xpdo;
$action=isset($options[xPDOTransport::PACKAGE_ACTION])?$options[xPDOTransport::PACKAGE_ACTION]:xPDOTransport::ACTION_INSTALL;

if($action===xPDOTransport::ACTION_UNINSTALL){
    $menu=$modx->getObject('modMenu','modxcomments');
    if($menu) $menu->remove();

    foreach($modx->getCollection('modSystemSetting',array('namespace'=>'modxcomments')) as $setting){
        $setting->remove();
    }

    $namespace=$modx->getObject('modNamespace','modxcomments');
    if($namespace) $namespace->remove();

    return true;
}

$corePath=MODX_CORE_PATH.'components/modxcomments/';
$modelPath=$corePath.'model/';

$namespace=$modx->getObject('modNamespace','modxcomments');
if(!$namespace){
    $namespace=$modx->newObject('modNamespace');
    $namespace->set('name','modxcomments');
}
$namespace->set('path','{core_path}components/modxcomments/');
$namespace->set('assets_path','{assets_path}components/modxcomments/');
$namespace->save();

$settings=array(
 'allow_guests'=>array('1','combo-boolean'),
 'max_depth'=>array('5','numberfield'),
 'max_length'=>array('5000','numberfield'),
 'edit_time'=>array('900','numberfield'),
 'rate_limit_count'=>array('5','numberfield'),
 'rate_limit_window'=>array('60','numberfield'),
 'guest_status'=>array('published','textfield'),
 'user_status'=>array('published','textfield'),
 'turnstile_enabled'=>array('0','combo-boolean'),
 'turnstile_site_key'=>array('','textfield'),
 'turnstile_secret_key'=>array('','text-password'),
 'turnstile_guests_only'=>array('1','combo-boolean'),
 'notify_admin'=>array('0','combo-boolean'),
 'notify_admin_email'=>array('','textfield'),
 'notify_replies'=>array('0','combo-boolean'),
);

foreach($settings as $key=>$spec){
    $fullKey='modxcomments.'.$key;
    $setting=$modx->getObject('modSystemSetting',$fullKey);
    if(!$setting){
        $setting=$modx->newObject('modSystemSetting');
        $setting->set('key',$fullKey);
        $setting->set('value',$spec[0]);
    }
    $setting->set('xtype',$spec[1]);
    $setting->set('namespace','modxcomments');
    $setting->set('area','modxcomments');
    $setting->save();
}

$legacyActions=$modx->getCollection('modAction',array('namespace'=>'modxcomments'));
foreach($legacyActions as $legacyAction){
    $legacyAction->remove();
}

$menu=$modx->getObject('modMenu','modxcomments');
if(!$menu){
    $menu=$modx->newObject('modMenu');
    $menu->set('text','modxcomments');
}
$menu->fromArray(array(
    'description'=>'modxcomments',
    'parent'=>'components',
    'menuindex'=>0,
    'action'=>'index',
    'namespace'=>'modxcomments',
    'params'=>'',
    'handler'=>'',
),'',true,true);
$menu->save();

if($modx->addPackage('modxcomments',$modelPath)){
    $manager=$modx->getManager();
    $manager->createObjectContainer('ModxCommentsComment');
    $manager->createObjectContainer('ModxCommentsVote');

    // Existing MODX installations often use MySQL "utf8" (3-byte).
    // Convert only our tables so 4-byte emoji are preserved.
    $prefix=$modx->getOption(xPDO::OPT_TABLE_PREFIX,null,'');
    foreach(array('modxcomments_comments','modxcomments_votes') as $table){
        $tableName=preg_replace('/[^a-zA-Z0-9_]/','',$prefix.$table);
        try{
            $modx->exec('ALTER TABLE '.$tableName.' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }catch(Exception $e){
            $modx->log(modX::LOG_LEVEL_ERROR,'[ModxComments] utf8mb4 migration failed for '.$tableName.': '.$e->getMessage());
        }
    }

    $commentsTable=preg_replace('/[^a-zA-Z0-9_]/','',$prefix.'modxcomments_comments');
    try{
        $modx->exec('ALTER TABLE '.$commentsTable.' ADD COLUMN reply_notifiedon DATETIME NULL DEFAULT NULL AFTER deletedon');
    }catch(Exception $e){
        // Expected on repeat upgrades when the column already exists.
    }
}

foreach(array('ModxCommentsOnCommentCreate','ModxCommentsOnCommentUpdate','ModxCommentsOnCommentDelete') as $eventName){
    $event=$modx->getObject('modEvent',$eventName);
    if(!$event){
        $event=$modx->newObject('modEvent');
        $event->fromArray(array('name'=>$eventName,'service'=>6,'groupname'=>'ModxComments'),'',true,true);
        $event->save();
    }
}

$modx->getCacheManager()->refresh(array(
    'system_settings'=>array(),
    'context_settings'=>array(),
    'lexicon_topics'=>array(),
    'menu'=>array(),
    'resource'=>array(),
));

return true;
