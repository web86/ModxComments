<?php
if(!isset($object) || !is_object($object) || !isset($object->xpdo)) return false;

$modx=$object->xpdo;
$versionData=@include MODX_CORE_PATH.'docs/version.inc.php';
$fullVersion=is_array($versionData) && !empty($versionData['full_version'])
    ? (string)$versionData['full_version']
    : (is_array($versionData) && !empty($versionData['version']) ? (string)$versionData['version'] : '2.0.0');
$isModx3=version_compare($fullVersion,'3.0.0','>=');

$categoryClass=$isModx3 ? 'MODX\\Revolution\\modCategory' : 'modCategory';
$menuClass=$isModx3 ? 'MODX\\Revolution\\modMenu' : 'modMenu';
$namespaceClass=$isModx3 ? 'MODX\\Revolution\\modNamespace' : 'modNamespace';
$settingClass=$isModx3 ? 'MODX\\Revolution\\modSystemSetting' : 'modSystemSetting';
$eventClass=$isModx3 ? 'MODX\\Revolution\\modEvent' : 'modEvent';
$transportClass=$isModx3 ? 'xPDO\\Transport\\xPDOTransport' : 'xPDOTransport';
$xpdoClass=$isModx3 ? 'xPDO\\xPDO' : 'xPDO';
$modxClass=$isModx3 ? 'MODX\\Revolution\\modX' : 'modX';

$packageActionKey=constant($transportClass.'::PACKAGE_ACTION');
$actionInstall=constant($transportClass.'::ACTION_INSTALL');
$actionUninstall=constant($transportClass.'::ACTION_UNINSTALL');
$action=isset($options[$packageActionKey])?$options[$packageActionKey]:$actionInstall;
$logError=constant($modxClass.'::LOG_LEVEL_ERROR');
$tablePrefixOption=constant($xpdoClass.'::OPT_TABLE_PREFIX');

$logInstallError=function($message) use ($modx,$logError){
    $modx->log($logError,'[ModxComments] '.$message);
};

$saveRequired=function($record,$label) use ($logInstallError){
    if(!$record){
        $logInstallError('Could not instantiate required install object: '.$label);
        return false;
    }

    try{
        if($record->save()){
            return true;
        }
    }catch(Throwable $e){
        $logInstallError('Could not save '.$label.': '.$e->getMessage());
        return false;
    }

    $logInstallError('Could not save required install object: '.$label);
    return false;
};

if($action===$actionUninstall){
    $menu=$modx->getObject($menuClass,'modxcomments');
    if($menu) $menu->remove();

    foreach($modx->getCollection($settingClass,array('namespace'=>'modxcomments')) as $setting){
        $setting->remove();
    }

    $namespace=$modx->getObject($namespaceClass,'modxcomments');
    if($namespace) $namespace->remove();

    return true;
}

$corePath=MODX_CORE_PATH.'components/modxcomments/';

$namespace=$modx->getObject($namespaceClass,'modxcomments');
if(!$namespace){
    $namespace=$modx->newObject($namespaceClass);
    $namespace->set('name','modxcomments');
}
if(!$namespace){
    $logInstallError('Could not instantiate namespace object.');
    return false;
}
$namespace->set('path','{core_path}components/modxcomments/');
$namespace->set('assets_path','{assets_path}components/modxcomments/');
if(!$saveRequired($namespace,'namespace modxcomments')){
    return false;
}

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
    'resource_signing_key'=>array('', 'text-password'),
);

foreach($settings as $key=>$spec){
    $fullKey='modxcomments.'.$key;
    $setting=$modx->getObject($settingClass,$fullKey);

    if(!$setting){
        $setting=$modx->newObject($settingClass);
        if(!$setting){
            $logInstallError('Could not instantiate system setting '.$fullKey.'.');
            return false;
        }
        $setting->set('key',$fullKey);
        $setting->set('value',$spec[0]);
    }

    if($key==='resource_signing_key' && trim((string)$setting->get('value'))===''){
        try{
            $settingValue=bin2hex(random_bytes(32));
        }catch(Exception $e){
            $settingValue=hash('sha256',uniqid('',true).mt_rand());
        }
        $setting->set('value',$settingValue);
    }

    $setting->set('xtype',$spec[1]);
    $setting->set('namespace','modxcomments');
    $setting->set('area','modxcomments');
    if(!$saveRequired($setting,'system setting '.$fullKey)){
        return false;
    }
}

$signingSetting=$modx->getObject($settingClass,'modxcomments.resource_signing_key');
if(!$signingSetting || trim((string)$signingSetting->get('value'))===''){
    $logInstallError('Resource signing key was not persisted.');
    return false;
}

$menu=$modx->getObject($menuClass,'modxcomments');
if(!$menu){
    $menu=$modx->newObject($menuClass);
    if(!$menu){
        $logInstallError('Could not instantiate manager menu object.');
        return false;
    }
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
    'permissions'=>'',
),'',true,true);
if(!$saveRequired($menu,'manager menu modxcomments')){
    return false;
}

$installedMenu=$modx->getObject($menuClass,'modxcomments');
if(!$installedMenu
    || (string)$installedMenu->get('namespace')!=='modxcomments'
    || (string)$installedMenu->get('action')!=='index'
){
    $logInstallError('Manager menu verification failed after save.');
    return false;
}

$prefix=preg_replace(
    '/[^a-zA-Z0-9_]/',
    '',
    $modx->getOption($tablePrefixOption,null,'')
);
$commentsTable=$prefix.'modxcomments_comments';
$votesTable=$prefix.'modxcomments_votes';

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
    $modx->log($logError,'[ModxComments] Could not create component tables: '.$e->getMessage());
}

foreach(array($commentsTable,$votesTable) as $tableName){
    try{
        $modx->exec(
            'ALTER TABLE '.$tableName
            .' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
    }catch(Exception $e){
        $modx->log(
            $logError,
            '[ModxComments] utf8mb4 migration failed for '
            .$tableName.': '.$e->getMessage()
        );
    }
}

try{
    $statement=$modx->query(
        'SHOW COLUMNS FROM '.$commentsTable.' LIKE "guest_owner_hash"'
    );
    $hasGuestOwner=$statement && $statement->fetch(PDO::FETCH_ASSOC);
    if(!$hasGuestOwner){
        $modx->exec(
            'ALTER TABLE '.$commentsTable
            .' ADD COLUMN guest_owner_hash CHAR(64) NOT NULL DEFAULT "" AFTER user_id'
        );
    }
}catch(Exception $e){
    $modx->log(
        $logError,
        '[ModxComments] guest_owner_hash migration failed: '.$e->getMessage()
    );
}

try{
    $statement=$modx->query(
        'SHOW COLUMNS FROM '.$commentsTable.' LIKE "reply_notifiedon"'
    );
    $hasReplyNotified=$statement && $statement->fetch(PDO::FETCH_ASSOC);
    if(!$hasReplyNotified){
        $modx->exec(
            'ALTER TABLE '.$commentsTable
            .' ADD COLUMN reply_notifiedon DATETIME NULL DEFAULT NULL AFTER deletedon'
        );
    }
}catch(Exception $e){
    $modx->log(
        $logError,
        '[ModxComments] reply_notifiedon migration failed: '.$e->getMessage()
    );
}

$expectedFiles=array(
    MODX_CORE_PATH.'components/modxcomments/controllers/index.class.php',
    MODX_CORE_PATH.'components/modxcomments/model/modxcomments/modxcomments.class.php',
    MODX_CORE_PATH.'components/modxcomments/compat/modx2/service.class.php',
    MODX_CORE_PATH.'components/modxcomments/compat/modx3/service.class.php',
    MODX_ASSETS_PATH.'components/modxcomments/js/comments.js',
);

if($isModx3){
    $expectedFiles[]=MODX_CORE_PATH.'components/modxcomments/src/Model/Comment.php';
}else{
    $expectedFiles[]=MODX_CORE_PATH.'components/modxcomments/model/modxcomments/modxcommentscomment.class.php';
}

foreach($expectedFiles as $expectedPath){
    if(!file_exists($expectedPath)){
        $modx->log(
            $logError,
            '[ModxComments] Installed file/path is missing: '.$expectedPath
        );
    }
}

foreach(array(
    'ModxCommentsBeforeCommentCreate',
    'ModxCommentsOnCommentCreate',
    'ModxCommentsOnCommentUpdate',
    'ModxCommentsOnCommentDelete',
    'ModxCommentsOnCommentPublish',
    'ModxCommentsOnCommentVote'
) as $eventName){
    $event=$modx->getObject($eventClass,$eventName);
    if(!$event){
        $event=$modx->newObject($eventClass);
        $event->fromArray(
            array(
                'name'=>$eventName,
                'service'=>6,
                'groupname'=>'ModxComments'
            ),
            '',
            true,
            true
        );
        if(!$saveRequired($event,'event '.$eventName)){
            return false;
        }
    }
}

$cacheManager=$modx->getCacheManager();
if(!$cacheManager){
    $logInstallError('Could not load cache manager after installation.');
    return false;
}
$cacheManager->refresh(array(
    'system_settings'=>array(),
    'context_settings'=>array(),
    'lexicon_topics'=>array(),
    'menu'=>array(),
    'resource'=>array(),
));

$logInfo=constant($modxClass.'::LOG_LEVEL_INFO');
$modx->log(
    $logInfo,
    '[ModxComments] Install resolver completed for MODX '.$fullVersion
    .'; settings, signing key and manager menu verified.'
);

return true;
