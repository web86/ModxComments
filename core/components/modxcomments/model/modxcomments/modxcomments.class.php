<?php
/**
 * Core service for ModxComments.
 * Business logic lives here; processors should remain thin.
 */
class ModxComments
{
    /** @var modX */
    protected $modx;

    /** @var array */
    public $config = array();

    public function __construct(modX $modx, array $config = array())
    {
        $this->modx = $modx;

        $corePath = $this->modx->getOption(
            'modxcomments.core_path',
            null,
            MODX_CORE_PATH . 'components/modxcomments/'
        );
        $modelPath = $corePath . 'model/';

        $defaults = array(
            'corePath' => $corePath,
            'modelPath' => $modelPath,
            'allowGuests' => true,
            'maxDepth' => 5,
            'maxLength' => 5000,
            'rateLimitCount' => 5,
            'rateLimitWindow' => 60,
            'guestStatus' => 'published',
            'userStatus' => 'published',
        );

        $settings = array(
            'allowGuests' => (bool) $this->modx->getOption('modxcomments.allow_guests', null, $defaults['allowGuests']),
            'maxDepth' => (int) $this->modx->getOption('modxcomments.max_depth', null, $defaults['maxDepth']),
            'maxLength' => (int) $this->modx->getOption('modxcomments.max_length', null, $defaults['maxLength']),
            'rateLimitCount' => (int) $this->modx->getOption('modxcomments.rate_limit_count', null, $defaults['rateLimitCount']),
            'rateLimitWindow' => (int) $this->modx->getOption('modxcomments.rate_limit_window', null, $defaults['rateLimitWindow']),
            'guestStatus' => (string) $this->modx->getOption('modxcomments.guest_status', null, $defaults['guestStatus']),
            'userStatus' => (string) $this->modx->getOption('modxcomments.user_status', null, $defaults['userStatus']),
        );

        $this->config = array_merge($defaults, $settings, $config);

        $this->modx->addPackage('modxcomments', $modelPath);
    }

    public function getPublicConfig()
    {
        return array(
            'allowGuests' => (bool) $this->config['allowGuests'],
            'maxDepth' => (int) $this->config['maxDepth'],
            'maxLength' => (int) $this->config['maxLength'],
        );
    }

    public function getCurrentUser()
    {
        $authenticated = $this->modx->user && $this->modx->user->isAuthenticated($this->modx->context->key);
        if (!$authenticated) {
            return array('id' => 0, 'authenticated' => false, 'name' => '');
        }

        $name = (string) $this->modx->user->get('username');
        $profile = $this->modx->user->getOne('Profile');
        if ($profile) {
            $fullName = trim((string) $profile->get('fullname'));
            if ($fullName !== '') {
                $name = $fullName;
            }
        }

        return array(
            'id' => (int) $this->modx->user->get('id'),
            'authenticated' => true,
            'name' => $name,
        );
    }

    public function getCsrfToken()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['modxcomments']['csrf'])) {
            try {
                $token = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $token = hash('sha256', uniqid('', true) . mt_rand());
            }
            $_SESSION['modxcomments']['csrf'] = $token;
        }
        return $_SESSION['modxcomments']['csrf'];
    }

    public function validateCsrfToken($token)
    {
        if (!is_string($token) || $token === '') {
            return false;
        }
        $known = $this->getCsrfToken();
        return function_exists('hash_equals') ? hash_equals($known, $token) : $known === $token;
    }

    public function getComments($resourceId, $contextKey, $limit = 200)
    {
        $resourceId = (int) $resourceId;
        $limit = max(1, min(500, (int) $limit));
        $this->assertResource($resourceId, $contextKey);

        $c = $this->modx->newQuery('ModxCommentsComment');
        $c->where(array(
            'resource_id' => $resourceId,
            'context_key' => $contextKey,
            'status' => 'published',
        ));
        $c->sortby('path', 'ASC');
        $c->limit($limit);

        $items = array();
        foreach ($this->modx->getCollection('ModxCommentsComment', $c) as $comment) {
            $items[] = $this->serializeComment($comment);
        }

        return array(
            'total' => count($items),
            'comments' => $items,
        );
    }

    public function createComment(array $data)
    {
        $resourceId = isset($data['resource']) ? (int) $data['resource'] : 0;
        $contextKey = isset($data['context']) ? $this->cleanContextKey($data['context']) : 'web';
        $parentId = isset($data['parent']) ? (int) $data['parent'] : 0;
        $content = isset($data['content']) ? trim((string) $data['content']) : '';

        $this->assertResource($resourceId, $contextKey);
        $this->assertCanCreate();
        $this->assertContent($content);
        $this->assertRateLimit();

        $user = $this->getCurrentUser();
        $authorName = '';
        $authorEmail = '';
        $userId = 0;

        if ($user['authenticated']) {
            $userId = (int) $user['id'];
            $authorName = (string) $user['name'];
            $profile = $this->modx->user->getOne('Profile');
            if ($profile) {
                $authorEmail = trim((string) $profile->get('email'));
            }
        } else {
            $authorName = trim(isset($data['author_name']) ? (string) $data['author_name'] : '');
            $authorEmail = trim(isset($data['author_email']) ? (string) $data['author_email'] : '');
            if ($authorName === '') {
                throw new InvalidArgumentException('author_name_required');
            }
            if ($this->stringLength($authorName) > 190) {
                throw new InvalidArgumentException('author_name_too_long');
            }
            if ($authorEmail !== '' && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('author_email_invalid');
            }
        }

        $depth = 0;
        $threadId = 0;
        $parentPath = '';
        if ($parentId > 0) {
            /** @var ModxCommentsComment|null $parent */
            $parent = $this->modx->getObject('ModxCommentsComment', array(
                'id' => $parentId,
                'resource_id' => $resourceId,
                'context_key' => $contextKey,
            ));
            if (!$parent || $parent->get('status') === 'deleted') {
                throw new InvalidArgumentException('parent_not_found');
            }
            $depth = (int) $parent->get('depth') + 1;
            if ($depth > (int) $this->config['maxDepth']) {
                throw new InvalidArgumentException('max_depth_reached');
            }
            $threadId = (int) $parent->get('thread_id');
            if ($threadId < 1) {
                $threadId = (int) $parent->get('id');
            }
            $parentPath = (string) $parent->get('path');
        }

        $now = date('Y-m-d H:i:s');
        $ipHash = $this->hashClientValue(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
        $uaHash = $this->hashClientValue(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');

        /** @var ModxCommentsComment $comment */
        $comment = $this->modx->newObject('ModxCommentsComment');
        $comment->fromArray(array(
            'resource_id' => $resourceId,
            'context_key' => $contextKey,
            'parent_id' => $parentId,
            'thread_id' => $threadId,
            'depth' => $depth,
            'path' => '',
            'user_id' => $userId,
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'content' => $content,
            'content_html' => $this->renderPlainText($content),
            'content_hash' => hash('sha256', $resourceId . '|' . $parentId . '|' . $content),
            'status' => $user['authenticated'] ? $this->config['userStatus'] : $this->config['guestStatus'],
            'createdon' => $now,
            'ip_hash' => $ipHash,
            'user_agent_hash' => $uaHash,
        ), '', true, true);

        if (!$comment->save()) {
            throw new RuntimeException('comment_save_failed');
        }

        $id = (int) $comment->get('id');
        if ($parentId === 0) {
            $threadId = $id;
        }
        $segment = str_pad((string) $id, 10, '0', STR_PAD_LEFT);
        $path = $parentPath === '' ? $segment : $parentPath . '.' . $segment;

        $comment->set('thread_id', $threadId);
        $comment->set('path', $path);
        if (!$comment->save()) {
            throw new RuntimeException('comment_path_save_failed');
        }

        return $this->serializeComment($comment);
    }

    public function cleanContextKey($contextKey)
    {
        $contextKey = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $contextKey);
        return $contextKey !== '' ? $contextKey : 'web';
    }

    protected function assertResource($resourceId, $contextKey)
    {
        if ($resourceId < 1) {
            throw new InvalidArgumentException('resource_required');
        }
        $resource = $this->modx->getObject('modResource', array(
            'id' => $resourceId,
            'context_key' => $contextKey,
            'deleted' => 0,
        ));
        if (!$resource) {
            throw new InvalidArgumentException('resource_not_found');
        }
    }

    protected function assertCanCreate()
    {
        $user = $this->getCurrentUser();
        if (!$user['authenticated'] && !$this->config['allowGuests']) {
            throw new RuntimeException('authentication_required');
        }
    }

    protected function assertContent($content)
    {
        if ($content === '') {
            throw new InvalidArgumentException('content_required');
        }
        if ($this->stringLength($content) > (int) $this->config['maxLength']) {
            throw new InvalidArgumentException('content_too_long');
        }
    }

    protected function assertRateLimit()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        if ($ip === '') {
            return;
        }

        $hash = $this->hashClientValue($ip);
        $after = date('Y-m-d H:i:s', time() - (int) $this->config['rateLimitWindow']);
        $c = $this->modx->newQuery('ModxCommentsComment');
        $c->where(array(
            'ip_hash' => $hash,
            'createdon:>=' => $after,
        ));
        $count = (int) $this->modx->getCount('ModxCommentsComment', $c);
        if ($count >= (int) $this->config['rateLimitCount']) {
            throw new RuntimeException('rate_limit_exceeded');
        }
    }

    protected function hashClientValue($value)
    {
        $salt = isset($this->modx->siteId) ? (string) $this->modx->siteId : (string) $this->modx->getOption('site_id', null, 'modxcomments');
        return hash('sha256', $salt . '|' . (string) $value);
    }

    protected function renderPlainText($text)
    {
        $pattern = '~(https?://[^\s<>]+)~iu';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('~^https?://[^\s<>]+$~iu', $part)) {
                $safe = htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
                $html .= '<a href="' . $safe . '" rel="nofollow ugc noopener" target="_blank">' . $safe . '</a>';
            } else {
                $html .= htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
            }
        }
        return nl2br($html, false);
    }

    protected function serializeComment($comment)
    {
        return array(
            'id' => (int) $comment->get('id'),
            'parent' => (int) $comment->get('parent_id'),
            'thread' => (int) $comment->get('thread_id'),
            'depth' => (int) $comment->get('depth'),
            'author' => array(
                'id' => (int) $comment->get('user_id'),
                'name' => (string) $comment->get('author_name'),
            ),
            'content' => (string) $comment->get('content'),
            'contentHtml' => (string) $comment->get('content_html'),
            'created' => (string) $comment->get('createdon'),
            'edited' => (bool) $comment->get('editedon'),
            'canReply' => ((int) $comment->get('depth') < (int) $this->config['maxDepth']),
        );
    }

    protected function stringLength($value)
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
