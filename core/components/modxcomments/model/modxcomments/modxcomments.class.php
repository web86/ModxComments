<?php
class ModxComments
{
    protected $modx;
    public $config = array();

    public function __construct(modX $modx, array $config = array())
    {
        $this->modx = $modx;
        $corePath = $modx->getOption('modxcomments.core_path', null, MODX_CORE_PATH . 'components/modxcomments/');
        $modelPath = $corePath . 'model/';

        $defaults = array(
            'corePath' => $corePath,
            'modelPath' => $modelPath,
            'allowGuests' => true,
            'maxDepth' => 5,
            'maxLength' => 5000,
            'editTime' => 900,
            'rateLimitCount' => 5,
            'rateLimitWindow' => 60,
            'guestStatus' => 'published',
            'userStatus' => 'published',
            'turnstileEnabled' => false,
            'turnstileSiteKey' => '',
            'turnstileSecretKey' => '',
            'turnstileGuestsOnly' => true,
        );

        $settings = array(
            'allowGuests' => (bool) $modx->getOption('modxcomments.allow_guests', null, $defaults['allowGuests']),
            'maxDepth' => (int) $modx->getOption('modxcomments.max_depth', null, $defaults['maxDepth']),
            'maxLength' => (int) $modx->getOption('modxcomments.max_length', null, $defaults['maxLength']),
            'editTime' => (int) $modx->getOption('modxcomments.edit_time', null, $defaults['editTime']),
            'rateLimitCount' => (int) $modx->getOption('modxcomments.rate_limit_count', null, $defaults['rateLimitCount']),
            'rateLimitWindow' => (int) $modx->getOption('modxcomments.rate_limit_window', null, $defaults['rateLimitWindow']),
            'guestStatus' => (string) $modx->getOption('modxcomments.guest_status', null, $defaults['guestStatus']),
            'userStatus' => (string) $modx->getOption('modxcomments.user_status', null, $defaults['userStatus']),
            'turnstileEnabled' => (bool) $modx->getOption('modxcomments.turnstile_enabled', null, $defaults['turnstileEnabled']),
            'turnstileSiteKey' => (string) $modx->getOption('modxcomments.turnstile_site_key', null, $defaults['turnstileSiteKey']),
            'turnstileSecretKey' => (string) $modx->getOption('modxcomments.turnstile_secret_key', null, $defaults['turnstileSecretKey']),
            'turnstileGuestsOnly' => (bool) $modx->getOption('modxcomments.turnstile_guests_only', null, $defaults['turnstileGuestsOnly']),
        );

        $this->config = array_merge($defaults, $settings, $config);
        $modx->addPackage('modxcomments', $modelPath);
    }

    public function getPublicConfig()
    {
        $user = $this->getCurrentUser();

        return array(
            'allowGuests' => (bool) $this->config['allowGuests'],
            'maxDepth' => (int) $this->config['maxDepth'],
            'maxLength' => (int) $this->config['maxLength'],
            'editTime' => (int) $this->config['editTime'],
            'guestEmailRequired' => true,
            'captcha' => array(
                'enabled' => $this->shouldUseTurnstile($user),
                'provider' => 'turnstile',
                'siteKey' => (string) $this->config['turnstileSiteKey'],
            ),
        );
    }

    public function getCurrentUser()
    {
        $authenticated = $this->modx->user
            && $this->modx->context
            && $this->modx->user->isAuthenticated($this->modx->context->key);

        if (!$authenticated) {
            return array('id' => 0, 'authenticated' => false, 'name' => '');
        }

        $name = (string) $this->modx->user->get('username');
        $profile = $this->modx->user->getOne('Profile');
        if ($profile && trim((string) $profile->get('fullname')) !== '') {
            $name = trim((string) $profile->get('fullname'));
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
        if (!is_string($token) || $token === '') return false;
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
            'status:IN' => array('published', 'deleted'),
        ));
        $c->sortby('path', 'ASC');
        $c->limit($limit);

        $items = array();
        foreach ($this->modx->getCollection('ModxCommentsComment', $c) as $comment) {
            $items[] = $this->serializeComment($comment);
        }

        return array('total' => count($items), 'comments' => $items);
    }

    public function createComment(array $data)
    {
        $resourceId = isset($data['resource']) ? (int) $data['resource'] : 0;
        $contextKey = isset($data['context']) ? $this->cleanContextKey($data['context']) : 'web';
        $parentId = isset($data['parent']) ? (int) $data['parent'] : 0;
        $content = isset($data['content']) ? trim((string) $data['content']) : '';

        $this->assertResource($resourceId, $contextKey);
        $this->assertCanCreate();
        $this->assertHoneypot($data);
        $this->assertContent($content);
        $this->assertRateLimit();

        $user = $this->getCurrentUser();
        $this->assertCaptcha($data, $user);

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

            if ($authorName === '') throw new InvalidArgumentException('author_name_required');
            if ($this->stringLength($authorName) > 190) throw new InvalidArgumentException('author_name_too_long');
            if ($authorEmail === '') throw new InvalidArgumentException('author_email_required');
            if (!filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('author_email_invalid');
            }
        }

        $depth = 0;
        $threadId = 0;
        $parentPath = '';

        if ($parentId > 0) {
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
            if ($threadId < 1) $threadId = (int) $parent->get('id');
            $parentPath = (string) $parent->get('path');
        }

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
            'createdon' => date('Y-m-d H:i:s'),
            'ip_hash' => $this->hashClientValue(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''),
            'user_agent_hash' => $this->hashClientValue(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''),
        ), '', true, true);

        if (!$comment->save()) throw new RuntimeException('comment_save_failed');

        $id = (int) $comment->get('id');
        if ($parentId === 0) $threadId = $id;

        $segment = str_pad((string) $id, 10, '0', STR_PAD_LEFT);
        $comment->set('thread_id', $threadId);
        $comment->set('path', $parentPath === '' ? $segment : $parentPath . '.' . $segment);

        if (!$comment->save()) throw new RuntimeException('comment_path_save_failed');

        $this->modx->invokeEvent('ModxCommentsOnCommentCreate', array(
            'comment' => $comment,
            'service' => $this,
        ));

        return $this->serializeComment($comment);
    }

    public function updateComment(array $data)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $content = isset($data['content']) ? trim((string) $data['content']) : '';
        $this->assertContent($content);

        $comment = $this->getOwnedEditableComment($id);
        $comment->set('content', $content);
        $comment->set('content_html', $this->renderPlainText($content));
        $comment->set('content_hash', hash('sha256',
            $comment->get('resource_id') . '|' . $comment->get('parent_id') . '|' . $content
        ));
        $comment->set('editedon', date('Y-m-d H:i:s'));

        if (!$comment->save()) throw new RuntimeException('comment_save_failed');

        $this->modx->invokeEvent('ModxCommentsOnCommentUpdate', array(
            'comment' => $comment,
            'service' => $this,
        ));

        return $this->serializeComment($comment);
    }

    public function deleteComment($id)
    {
        $comment = $this->getOwnedEditableComment((int) $id);
        $comment->set('status', 'deleted');
        $comment->set('deletedon', date('Y-m-d H:i:s'));
        $comment->set('content', '');
        $comment->set('content_html', '');
        $comment->set('content_hash', '');

        if (!$comment->save()) throw new RuntimeException('comment_delete_failed');

        $this->modx->invokeEvent('ModxCommentsOnCommentDelete', array(
            'comment' => $comment,
            'service' => $this,
        ));

        return $this->serializeComment($comment);
    }

    public function voteComment($id, $value)
    {
        $id = (int) $id;
        $value = (int) $value;

        if ($id < 1) throw new InvalidArgumentException('comment_required');
        if (!in_array($value, array(-1, 1), true)) throw new InvalidArgumentException('vote_invalid');

        $comment = $this->modx->getObject('ModxCommentsComment', $id);
        if (!$comment || $comment->get('status') !== 'published') {
            throw new InvalidArgumentException('comment_not_found');
        }

        $voterHash = $this->getVoterHash();
        $vote = $this->modx->getObject('ModxCommentsVote', array(
            'comment_id' => $id,
            'voter_hash' => $voterHash,
        ));

        $myVote = 0;

        if ($vote) {
            if ((int) $vote->get('value') === $value) {
                $vote->remove();
            } else {
                $vote->set('value', $value);
                if (!$vote->save()) throw new RuntimeException('vote_save_failed');
                $myVote = $value;
            }
        } else {
            $vote = $this->modx->newObject('ModxCommentsVote');
            $vote->fromArray(array(
                'comment_id' => $id,
                'voter_hash' => $voterHash,
                'value' => $value,
                'createdon' => date('Y-m-d H:i:s'),
            ), '', true, true);

            if (!$vote->save()) throw new RuntimeException('vote_save_failed');
            $myVote = $value;
        }

        return array(
            'commentId' => $id,
            'votes' => $this->getVoteSummary($id, $voterHash),
            'myVote' => $myVote,
        );
    }

    public function cleanContextKey($contextKey)
    {
        $contextKey = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $contextKey);
        return $contextKey !== '' ? $contextKey : 'web';
    }

    protected function getOwnedEditableComment($id)
    {
        if ($id < 1) throw new InvalidArgumentException('comment_required');

        $comment = $this->modx->getObject('ModxCommentsComment', $id);
        if (!$comment || $comment->get('status') === 'deleted') {
            throw new InvalidArgumentException('comment_not_found');
        }

        $user = $this->getCurrentUser();
        if (!$user['authenticated'] || (int) $comment->get('user_id') !== (int) $user['id']) {
            throw new RuntimeException('permission_denied');
        }

        if (!$this->isWithinEditWindow($comment)) {
            throw new RuntimeException('edit_window_expired');
        }

        return $comment;
    }

    protected function isWithinEditWindow($comment)
    {
        $seconds = (int) $this->config['editTime'];
        if ($seconds <= 0) return false;

        $created = strtotime((string) $comment->get('createdon'));
        return $created && (time() - $created) <= $seconds;
    }

    protected function shouldUseTurnstile(array $user)
    {
        if (!$this->config['turnstileEnabled'] || trim($this->config['turnstileSiteKey']) === '') {
            return false;
        }

        return !$this->config['turnstileGuestsOnly'] || !$user['authenticated'];
    }

    protected function assertCaptcha(array $data, array $user)
    {
        if (!$this->shouldUseTurnstile($user)) return;

        require_once $this->config['corePath'] . 'model/modxcomments/captcha/turnstile.class.php';
        $provider = new ModxCommentsTurnstile($this->modx, $this->config['turnstileSecretKey']);
        $token = isset($data['captcha_token']) ? (string) $data['captcha_token'] : '';
        $remoteIp = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

        if (!$provider->verify($token, $remoteIp)) {
            throw new RuntimeException('captcha_failed');
        }
    }

    protected function assertHoneypot(array $data)
    {
        $value = isset($data['website']) ? trim((string) $data['website']) : '';
        if ($value !== '') {
            throw new RuntimeException('spam_detected');
        }
    }

    protected function assertResource($resourceId, $contextKey)
    {
        if ($resourceId < 1) throw new InvalidArgumentException('resource_required');

        $resource = $this->modx->getObject('modResource', array(
            'id' => $resourceId,
            'context_key' => $contextKey,
            'deleted' => 0,
        ));

        if (!$resource) throw new InvalidArgumentException('resource_not_found');
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
        if ($content === '') throw new InvalidArgumentException('content_required');
        if ($this->stringLength($content) > (int) $this->config['maxLength']) {
            throw new InvalidArgumentException('content_too_long');
        }
    }

    protected function assertRateLimit()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        if ($ip === '') return;

        $c = $this->modx->newQuery('ModxCommentsComment');
        $c->where(array(
            'ip_hash' => $this->hashClientValue($ip),
            'createdon:>=' => date('Y-m-d H:i:s', time() - (int) $this->config['rateLimitWindow']),
        ));

        if ((int) $this->modx->getCount('ModxCommentsComment', $c) >= (int) $this->config['rateLimitCount']) {
            throw new RuntimeException('rate_limit_exceeded');
        }
    }

    protected function getVoterHash()
    {
        $user = $this->getCurrentUser();
        if ($user['authenticated']) {
            return hash('sha256', 'user|' . (int) $user['id'] . '|' . $this->hashClientValue('vote'));
        }

        $cookieName = 'modxcomments_voter';
        $token = isset($_COOKIE[$cookieName]) ? preg_replace('/[^a-f0-9]/i', '', (string) $_COOKIE[$cookieName]) : '';

        if (strlen($token) < 32) {
            try {
                $token = bin2hex(random_bytes(24));
            } catch (Exception $e) {
                $token = hash('sha256', uniqid('', true) . mt_rand());
            }

            setcookie(
                $cookieName,
                $token,
                time() + 31536000,
                '/',
                '',
                !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                true
            );
            $_COOKIE[$cookieName] = $token;
        }

        return hash('sha256', 'guest|' . $token . '|' . $this->hashClientValue('vote'));
    }

    protected function getVoteSummary($commentId, $voterHash = null)
    {
        $commentId = (int) $commentId;
        if ($voterHash === null) $voterHash = $this->getVoterHash();

        $up = (int) $this->modx->getCount('ModxCommentsVote', array(
            'comment_id' => $commentId,
            'value' => 1,
        ));
        $down = (int) $this->modx->getCount('ModxCommentsVote', array(
            'comment_id' => $commentId,
            'value' => -1,
        ));

        $mine = $this->modx->getObject('ModxCommentsVote', array(
            'comment_id' => $commentId,
            'voter_hash' => $voterHash,
        ));

        return array(
            'up' => $up,
            'down' => $down,
            'score' => $up - $down,
            'mine' => $mine ? (int) $mine->get('value') : 0,
        );
    }

    protected function hashClientValue($value)
    {
        $salt = isset($this->modx->siteId)
            ? (string) $this->modx->siteId
            : (string) $this->modx->getOption('site_id', null, 'modxcomments');

        return hash('sha256', $salt . '|' . (string) $value);
    }

    protected function renderPlainText($text)
    {
        $pattern = '~(https?://[^\s<>]+)~iu';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';

        foreach ($parts as $part) {
            if ($part === '') continue;

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
        $deleted = $comment->get('status') === 'deleted';
        $user = $this->getCurrentUser();
        $owned = $user['authenticated'] && (int) $comment->get('user_id') === (int) $user['id'];
        $editable = !$deleted && $owned && $this->isWithinEditWindow($comment);
        $replyTo = null;

        $parentId = (int) $comment->get('parent_id');
        if ($parentId > 0) {
            $parent = $this->modx->getObject('ModxCommentsComment', $parentId);
            if ($parent) {
                $parentText = trim(preg_replace('/\s+/u', ' ', (string) $parent->get('content')));
                if ($this->stringLength($parentText) > 180) {
                    $parentText = $this->stringSlice($parentText, 0, 177) . '…';
                }

                $replyTo = array(
                    'id' => (int) $parent->get('id'),
                    'author' => $parent->get('status') === 'deleted' ? '' : (string) $parent->get('author_name'),
                    'excerpt' => $parent->get('status') === 'deleted' ? '' : $parentText,
                    'deleted' => $parent->get('status') === 'deleted',
                );
            }
        }

        return array(
            'id' => (int) $comment->get('id'),
            'parent' => $parentId,
            'thread' => (int) $comment->get('thread_id'),
            'depth' => (int) $comment->get('depth'),
            'status' => (string) $comment->get('status'),
            'deleted' => $deleted,
            'author' => array(
                'id' => (int) $comment->get('user_id'),
                'name' => $deleted ? '' : (string) $comment->get('author_name'),
            ),
            'content' => $deleted ? '' : (string) $comment->get('content'),
            'contentHtml' => $deleted ? '' : (string) $comment->get('content_html'),
            'replyTo' => $replyTo,
            'votes' => $deleted ? array('up' => 0, 'down' => 0, 'score' => 0, 'mine' => 0) : $this->getVoteSummary((int) $comment->get('id')),
            'created' => (string) $comment->get('createdon'),
            'edited' => (bool) $comment->get('editedon'),
            'canReply' => !$deleted && ((int) $comment->get('depth') < (int) $this->config['maxDepth']),
            'canEdit' => $editable,
            'canDelete' => $editable,
        );
    }

    protected function stringLength($value)
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    protected function stringSlice($value, $start, $length)
    {
        return function_exists('mb_substr')
            ? mb_substr($value, $start, $length, 'UTF-8')
            : substr($value, $start, $length);
    }
}
