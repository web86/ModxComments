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
 'threads_per_page'=>array('20','numberfield'),
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

$prefix=preg_replace('/[^a-zA-Z0-9_]/','',$modx->getOption(xPDO::OPT_TABLE_PREFIX,null,''));
$commentsTable=$prefix.'modxcomments_comments';
$votesTable=$prefix.'modxcomments_votes';

/*
 * Do not call addPackage() here. On a clean MODX install this PHP resolver can
 * run before the file resolver has made core/components/modxcomments/model/
 * available. Create/migrate the component tables directly; the generated xPDO
 * model files are available later during normal runtime.
 */
try{
    $modx->exec(
        'CREATE TABLE IF NOT EXISTS '.$commentsTable.' ('
        .'id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,'
        .'resource_id INT(10) UNSIGNED NOT NULL DEFAULT 0,'
        .'context_key VARCHAR(100) NOT NULL DEFAULT "web",'
        .'parent_id INT(10) UNSIGNED NOT NULL DEFAULT 0,'
        .'thread_id INT(10) UNSIGNED NOT NULL DEFAULT 0,'
        .'depth TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,'
        .'path VARCHAR(255) NOT NULL DEFAULT "",'
        .'user_id INT(10) UNSIGNED NOT NULL DEFAULT 0,'
        .'guest_owner_hash CHAR(64) NOT NULL DEFAULT "",'
        .'author_name VARCHAR(190) NOT NULL DEFAULT "",'
        .'author_email VARCHAR(254) NOT NULL DEFAULT "",'
        .'content TEXT NOT NULL,'
        .'content_html TEXT NOT NULL,'
        .'content_hash CHAR(64) NOT NULL DEFAULT "",'
        .'status VARCHAR(32) NOT NULL DEFAULT "published",'
        .'createdon DATETIME NOT NULL,'
        .'editedon DATETIME NULL DEFAULT NULL,'
        .'deletedon DATETIME NULL DEFAULT NULL,'
        .'reply_notifiedon DATETIME NULL DEFAULT NULL,'
        .'ip_hash CHAR(64) NOT NULL DEFAULT "",'
        .'user_agent_hash CHAR(64) NOT NULL DEFAULT "",'
        .'PRIMARY KEY (id),'
        .'KEY resource_context_status (resource_id,context_key,status),'
        .'KEY parent_id (parent_id),'
        .'KEY thread_path (thread_id,path),'
        .'KEY user_id (user_id),'
        .'KEY ip_created (ip_hash,createdon)'
        .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $modx->exec(
        'CREATE TABLE IF NOT EXISTS '.$votesTable.' ('
        .'id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,'
        .'comment_id INT(10) UNSIGNED NOT NULL DEFAULT 0,'
        .'voter_hash CHAR(64) NOT NULL DEFAULT "",'
        .'value TINYINT(2) NOT NULL DEFAULT 1,'
        .'createdon DATETIME NOT NULL,'
        .'PRIMARY KEY (id),'
        .'UNIQUE KEY comment_voter (comment_id,voter_hash),'
        .'KEY comment_value (comment_id,value)'
        .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}catch(Exception $e){
    $modx->log(modX::LOG_LEVEL_ERROR,'[ModxComments] Could not create component tables: '.$e->getMessage());
}

foreach(array($commentsTable,$votesTable) as $tableName){
    try{
        $modx->exec('ALTER TABLE '.$tableName.' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }catch(Exception $e){
        $modx->log(modX::LOG_LEVEL_ERROR,'[ModxComments] utf8mb4 migration failed for '.$tableName.': '.$e->getMessage());
    }
}

try{
    $statement=$modx->query('SHOW COLUMNS FROM '.$commentsTable.' LIKE "guest_owner_hash"');
    $hasGuestOwner=$statement && $statement->fetch(PDO::FETCH_ASSOC);
    if(!$hasGuestOwner){
        $modx->exec('ALTER TABLE '.$commentsTable.' ADD COLUMN guest_owner_hash CHAR(64) NOT NULL DEFAULT "" AFTER user_id');
    }
}catch(Exception $e){
    $modx->log(modX::LOG_LEVEL_ERROR,'[ModxComments] guest_owner_hash migration failed: '.$e->getMessage());
}

try{
    $statement=$modx->query('SHOW COLUMNS FROM '.$commentsTable.' LIKE "reply_notifiedon"');
    $hasReplyNotified=$statement && $statement->fetch(PDO::FETCH_ASSOC);
    if(!$hasReplyNotified){
        $modx->exec('ALTER TABLE '.$commentsTable.' ADD COLUMN reply_notifiedon DATETIME NULL DEFAULT NULL AFTER deletedon');
    }
}catch(Exception $e){
    $modx->log(modX::LOG_LEVEL_ERROR,'[ModxComments] reply_notifiedon migration failed: '.$e->getMessage());
}

$expectedFiles=array(
    MODX_CORE_PATH.'components/modxcomments/controllers/index.class.php',
    MODX_CORE_PATH.'components/modxcomments/model/',
    MODX_ASSETS_PATH.'components/modxcomments/js/comments.js',
);

foreach($expectedFiles as $expectedPath){
    if(!file_exists($expectedPath)){
        $modx->log(modX::LOG_LEVEL_ERROR,'[ModxComments] Installed file/path is missing: '.$expectedPath);
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
