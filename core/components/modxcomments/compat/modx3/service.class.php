<?php
use MODX\Revolution\modX;
use MODX\Revolution\modResource;
use MODX\Revolution\modChunk;
use MODX\Revolution\Mail\modMail;
use MODX\Revolution\Mail\modPHPMailer;
use ModxComments\Model\Comment;
use ModxComments\Model\Vote;

class ModxComments
{
    protected $modx;
    public $config = array();

    public function __construct(modX $modx, array $config = array())
    {
        $this->modx = $modx;
        $corePath = $modx->getOption('modxcomments.core_path', null, MODX_CORE_PATH . 'components/modxcomments/');
        $modelPath = $corePath . 'src/';

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
            'notifyAdmin' => false,
            'notifyAdminEmail' => '',
            'notifyReplies' => false,
            'threadsPerPage' => 20,
            'resourceSigningKey' => '',
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
            'notifyAdmin' => (bool) $modx->getOption('modxcomments.notify_admin', null, $defaults['notifyAdmin']),
            'notifyAdminEmail' => trim((string) $modx->getOption('modxcomments.notify_admin_email', null, $defaults['notifyAdminEmail'])),
            'notifyReplies' => (bool) $modx->getOption('modxcomments.notify_replies', null, $defaults['notifyReplies']),
            'threadsPerPage' => (int) $modx->getOption('modxcomments.threads_per_page', null, $defaults['threadsPerPage']),
            'resourceSigningKey' => (string) $modx->getOption('modxcomments.resource_signing_key', null, $defaults['resourceSigningKey']),
        );

        $this->config = array_merge($defaults, $settings, $config);
        $modx->addPackage('ModxComments\\Model', $modelPath, null, 'ModxComments\\');
    }

    public function getPublicConfig()
    {
        $user = $this->getCurrentUser();

        return array(
            'allowGuests' => (bool) $this->config['allowGuests'],
            'maxDepth' => (int) $this->config['maxDepth'],
            'maxLength' => (int) $this->config['maxLength'],
            'editTime' => (int) $this->config['editTime'],
            'threadsPerPage' => max(1, (int) $this->config['threadsPerPage']),
            'locale' => (string) $this->modx->getOption('cultureKey', null, 'en'),
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
        $user = null;
        $source = '';

        if (
            $this->modx->user
            && $this->modx->context
            && $this->modx->user->isAuthenticated($this->modx->context->key)
        ) {
            $user = $this->modx->user;
            $source = (string) $this->modx->context->key;
        }

        // A manager login belongs to the "mgr" context and normally does not
        // authenticate the same browser in "web". For trusted administrators,
        // reuse the valid mgr session as a frontend identity.
        if (!$user) {
            $mgrUser = $this->modx->getAuthenticatedUser('mgr');
            if (
                $mgrUser
                && (
                    (bool) $mgrUser->get('sudo')
                    || $mgrUser->isMember('Administrator')
                )
            ) {
                $user = $mgrUser;
                $source = 'mgr';
            }
        }

        if (!$user) {
            return array(
                'id' => 0,
                'authenticated' => false,
                'name' => '',
                'source' => '',
                'managerAdmin' => false,
            );
        }

        $name = (string) $user->get('username');
        $profile = $user->getOne('Profile');
        if ($profile && trim((string) $profile->get('fullname')) !== '') {
            $name = trim((string) $profile->get('fullname'));
        }

        return array(
            'id' => (int) $user->get('id'),
            'authenticated' => true,
            'name' => $name,
            'source' => $source,
            'managerAdmin' => $source === 'mgr',
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

    public function getComments($resourceId, $contextKey, $page = 1, $perPage = null, $resourceToken = '')
    {
        $resourceId = (int) $resourceId;
        $page = max(1, (int) $page);
        $perPage = $perPage === null ? (int) $this->config['threadsPerPage'] : (int) $perPage;
        $perPage = max(1, min(100, $perPage));

        $this->assertResourceToken($resourceId, $contextKey, $resourceToken);
        $this->assertResource($resourceId, $contextKey);

        $rootCriteria = array(
            'resource_id' => $resourceId,
            'context_key' => $contextKey,
            'parent_id' => 0,
            'status:IN' => array('published', 'deleted'),
        );

        $totalThreads = (int) $this->modx->getCount(Comment::class, $rootCriteria);
        $pages = max(1, (int) ceil($totalThreads / $perPage));
        if ($page > $pages) $page = $pages;

        $rootsQuery = $this->modx->newQuery(Comment::class);
        $rootsQuery->where($rootCriteria);
        $rootsQuery->sortby('createdon', 'DESC');
        $rootsQuery->sortby('id', 'DESC');
        $rootsQuery->limit($perPage, ($page - 1) * $perPage);

        $items = array();
        foreach ($this->modx->getCollection(Comment::class, $rootsQuery) as $root) {
            $threadId = (int) $root->get('thread_id');
            if ($threadId < 1) $threadId = (int) $root->get('id');

            $threadQuery = $this->modx->newQuery(Comment::class);
            $threadQuery->where(array(
                'resource_id' => $resourceId,
                'context_key' => $contextKey,
                'thread_id' => $threadId,
                'status:IN' => array('published', 'deleted'),
            ));
            $threadQuery->sortby('path', 'ASC');

            foreach ($this->modx->getCollection(Comment::class, $threadQuery) as $comment) {
                $items[] = $this->serializeComment($comment);
            }
        }

        return array(
            'total' => $this->getCommentCount($resourceId, $contextKey, $resourceToken),
            'totalThreads' => $totalThreads,
            'comments' => $items,
            'pagination' => array(
                'page' => $page,
                'pages' => $pages,
                'perPage' => $perPage,
                'totalThreads' => $totalThreads,
            ),
        );
    }

    public function getCommentCount($resourceId, $contextKey, $resourceToken = '')
    {
        $resourceId = (int) $resourceId;
        $this->assertResourceToken($resourceId, $contextKey, $resourceToken);
        $this->assertResource($resourceId, $contextKey);

        return (int) $this->modx->getCount(Comment::class, array(
            'resource_id' => $resourceId,
            'context_key' => $contextKey,
            'status' => 'published',
        ));
    }

    public function getFrontendLexicon()
    {
        $this->modx->lexicon->load('modxcomments:frontend');

        $keys = array(
            'comments','sign_in','leave_comment','cancel','name','email','website',
            'insert_link','more_emoji','link_text','url','insert_link_button','comment',
            'write_comment','post_comment','no_comments','no_comments_text','comment_deleted',
            'edit_comment','save','reply','edit','delete','delete_question','delete_replies',
            'awaiting_moderation','comment_rating','like','dislike','guest','permalink',
            'pending','edited','admin','reply_deleted','replying_to','replying_to_id','captcha_required',
            'submitted_pending','submitted','comment_empty','posting','saving','updated',
            'deleting','deleted','previous','next','page'
        );

        $strings = array();
        foreach ($keys as $key) {
            $strings[$key] = $this->modx->lexicon('mc.' . $key);
        }

        foreach (array(
            'csrf_invalid','authentication_required','content_required','content_too_long',
            'author_name_required','author_name_too_long','author_email_required',
            'author_email_invalid','rate_limit_exceeded','captcha_failed','spam_detected',
            'permission_denied','edit_window_expired','comment_not_found','vote_invalid',
            'comment_create_cancelled','resource_token_invalid'
        ) as $errorKey) {
            $strings['error.' . $errorKey] = $this->modx->lexicon('mc.error.' . $errorKey);
        }

        return $strings;
    }

    public function createComment(array $data)
    {
        $beforeResults = $this->modx->invokeEvent('ModxCommentsBeforeCommentCreate', array(
            'data' => &$data,
            'service' => $this,
        ));
        if (is_array($beforeResults) && in_array(false, $beforeResults, true)) {
            throw new RuntimeException('comment_create_cancelled');
        }

        $resourceId = isset($data['resource']) ? (int) $data['resource'] : 0;
        $contextKey = isset($data['context']) ? $this->cleanContextKey($data['context']) : 'web';
        $parentId = isset($data['parent']) ? (int) $data['parent'] : 0;
        $content = isset($data['content']) ? trim((string) $data['content']) : '';

        $resourceToken = isset($data['resource_token']) ? (string) $data['resource_token'] : '';
        $this->assertResourceToken($resourceId, $contextKey, $resourceToken);
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
            $authorUser = $this->modx->getObject('MODX\\Revolution\\modUser', $userId);
            $profile = $authorUser ? $authorUser->getOne('Profile') : null;
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
            $parent = $this->modx->getObject(Comment::class, array(
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

        $comment = $this->modx->newObject(Comment::class);
        $comment->fromArray(array(
            'resource_id' => $resourceId,
            'context_key' => $contextKey,
            'parent_id' => $parentId,
            'thread_id' => $threadId,
            'depth' => $depth,
            'path' => '',
            'user_id' => $userId,
            'guest_owner_hash' => $user['authenticated'] ? '' : $this->getGuestOwnerHash(true),
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

        $this->notifyAdmin($comment);
        if ($comment->get('status') === 'published') {
            $this->notifyReplyAuthor($comment);
            $this->modx->invokeEvent('ModxCommentsOnCommentPublish', array(
                'comment' => $comment,
                'service' => $this,
            ));
        }

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

        $comment = $this->modx->getObject(Comment::class, $id);
        if (!$comment || $comment->get('status') !== 'published') {
            throw new InvalidArgumentException('comment_not_found');
        }

        $voterHash = $this->getVoterHash();
        $vote = $this->modx->getObject(Vote::class, array(
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
            $vote = $this->modx->newObject(Vote::class);
            $vote->fromArray(array(
                'comment_id' => $id,
                'voter_hash' => $voterHash,
                'value' => $value,
                'createdon' => date('Y-m-d H:i:s'),
            ), '', true, true);

            if (!$vote->save()) throw new RuntimeException('vote_save_failed');
            $myVote = $value;
        }

        $summary = $this->getVoteSummary($id, $voterHash);

        $this->modx->invokeEvent('ModxCommentsOnCommentVote', array(
            'comment' => $comment,
            'value' => $myVote,
            'votes' => $summary,
            'service' => $this,
        ));

        return array(
            'commentId' => $id,
            'votes' => $summary,
            'myVote' => $myVote,
        );
    }

    public function notifyReplyAuthor($comment)
    {
        if (!$this->config['notifyReplies']) return false;
        if (!$comment || (int) $comment->get('parent_id') < 1) return false;
        if ($comment->get('status') !== 'published') return false;
        if ($comment->get('reply_notifiedon')) return false;

        $parent = $this->modx->getObject(Comment::class, (int) $comment->get('parent_id'));
        if (!$parent) return false;

        $email = trim((string) $parent->get('author_email'));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        $childEmail = trim((string) $comment->get('author_email'));
        if ($childEmail !== '' && strcasecmp($childEmail, $email) === 0) return false;

        $placeholders = array(
            'author_name' => trim((string) $comment->get('author_name')),
            'content' => $this->plainExcerpt((string) $comment->get('content'), 1200),
            'comment_url' => $this->getCommentUrl($comment),
            'comment_id' => (int) $comment->get('id'),
        );

        $subject = $this->renderEmailChunk(
            'ModxCommentsEmailReplySubject',
            $placeholders,
            '[' . $this->modx->getOption('site_name', null, 'Website') . '] New reply to your comment'
        );
        $body = $this->renderEmailChunk(
            'ModxCommentsEmailReplyBody',
            $placeholders,
            $placeholders['author_name'] . " replied to your comment.\n\n" . $placeholders['content'] . "\n\n" . $placeholders['comment_url']
        );

        $sent = $this->sendMail($email, $subject, $body);
        if ($sent) {
            $comment->set('reply_notifiedon', date('Y-m-d H:i:s'));
            $comment->save();
        }

        return $sent;
    }

    protected function notifyAdmin($comment)
    {
        if (!$this->config['notifyAdmin']) return false;

        $email = $this->config['notifyAdminEmail'];
        if ($email === '') {
            $email = trim((string) $this->modx->getOption('emailsender', null, ''));
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->modx->log(modX::LOG_LEVEL_WARN, '[ModxComments] Admin notification email is not configured.');
            return false;
        }

        $resource = $this->modx->getObject(modResource::class, (int) $comment->get('resource_id'));
        $placeholders = array(
            'status' => (string) $comment->get('status'),
            'resource_title' => $resource ? (string) $resource->get('pagetitle') : ('#' . $comment->get('resource_id')),
            'resource_id' => (int) $comment->get('resource_id'),
            'author_name' => (string) $comment->get('author_name'),
            'author_email' => (string) $comment->get('author_email'),
            'content' => $this->plainExcerpt((string) $comment->get('content'), 2000),
            'comment_url' => $this->getCommentUrl($comment),
            'comment_id' => (int) $comment->get('id'),
        );

        $subject = $this->renderEmailChunk(
            'ModxCommentsEmailAdminSubject',
            $placeholders,
            '[' . $this->modx->getOption('site_name', null, 'Website') . '] New comment (' . $placeholders['status'] . ')'
        );
        $body = $this->renderEmailChunk(
            'ModxCommentsEmailAdminBody',
            $placeholders,
            $placeholders['content'] . "\n\n" . $placeholders['comment_url']
        );

        return $this->sendMail($email, $subject, $body);
    }

    protected function renderEmailChunk($name, array $placeholders, $fallback)
    {
        foreach ($placeholders as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $placeholders[$key] = str_replace(
                    array('[[', ']]'),
                    array('[ [', '] ]'),
                    (string) $value
                );
            }
        }

        $chunk = $this->modx->getObject(modChunk::class, array('name' => $name));
        if (!$chunk) return $fallback;

        $rendered = $chunk->process($placeholders);
        return trim((string) $rendered) !== '' ? (string) $rendered : $fallback;
    }

    protected function sendMail($to, $subject, $body)
    {
        try {
            $mail = $this->modx->getService('mail', modPHPMailer::class);
            if (!$mail) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[ModxComments] MODX mail service is unavailable.');
                return false;
            }

            $from = trim((string) $this->modx->getOption('emailsender', null, ''));
            $fromName = trim((string) $this->modx->getOption('site_name', null, 'ModxComments'));

            if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[ModxComments] MODX emailsender must contain a valid email address for notifications.');
                return false;
            }

            $mail->set(modMail::MAIL_BODY, $body);
            $mail->set(modMail::MAIL_BODY_TEXT, $body);
            $mail->set(modMail::MAIL_FROM, $from);
            $mail->set(modMail::MAIL_FROM_NAME, $fromName);
            $mail->set(modMail::MAIL_SENDER, $from);
            $mail->set(modMail::MAIL_SUBJECT, $subject);
            $mail->set(modMail::MAIL_CHARSET, 'UTF-8');
            $mail->address('to', $to);

            $sent = $mail->send();
            if (!$sent) {
                $this->modx->log(modX::LOG_LEVEL_ERROR, '[ModxComments] Email notification could not be sent to ' . $to);
            }
            $mail->reset();

            return (bool) $sent;
        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[ModxComments] Email notification error: ' . $e->getMessage());
            return false;
        }
    }

    protected function getCommentUrl($comment)
    {
        $resourceId = (int) $comment->get('resource_id');
        $contextKey = (string) $comment->get('context_key');
        $url = $this->modx->makeUrl($resourceId, $contextKey, '', 'full');
        return $url . '#comment-' . (int) $comment->get('id');
    }

    protected function plainExcerpt($text, $limit)
    {
        $text = trim(preg_replace('/\\s+/u', ' ', (string) $text));
        if ($this->stringLength($text) <= $limit) return $text;
        return $this->stringSlice($text, 0, max(1, $limit - 1)) . '…';
    }

    public function cleanContextKey($contextKey)
    {
        $contextKey = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $contextKey);
        if ($contextKey === '' || strtolower($contextKey) === 'mgr') {
            return 'web';
        }
        return $contextKey;
    }

    protected function getOwnedEditableComment($id)
    {
        if ($id < 1) throw new InvalidArgumentException('comment_required');

        $comment = $this->modx->getObject(Comment::class, $id);
        if (!$comment || $comment->get('status') === 'deleted') {
            throw new InvalidArgumentException('comment_not_found');
        }

        $user = $this->getCurrentUser();
        $owned = false;

        if ($user['authenticated']) {
            $owned = (int) $comment->get('user_id') === (int) $user['id'];
        } elseif ((int) $comment->get('user_id') === 0) {
            $knownHash = (string) $comment->get('guest_owner_hash');
            $currentHash = $this->getGuestOwnerHash(false);
            $owned = $knownHash !== '' && $currentHash !== '' && (
                function_exists('hash_equals') ? hash_equals($knownHash, $currentHash) : $knownHash === $currentHash
            );
        }

        if (!$owned) {
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

    public function validateResourceToken($resourceId, $contextKey, $token)
    {
        $resourceId = (int) $resourceId;
        $contextKey = $this->cleanContextKey($contextKey);
        $token = trim((string) $token);
        $key = (string) $this->config['resourceSigningKey'];

        if ($resourceId < 1 || $key === '' || $token === '') return false;

        $expected = hash_hmac('sha256', $resourceId . '|' . $contextKey, $key);
        return function_exists('hash_equals')
            ? hash_equals($expected, $token)
            : $expected === $token;
    }

    protected function assertResourceToken($resourceId, $contextKey, $token)
    {
        if (!$this->validateResourceToken($resourceId, $contextKey, $token)) {
            throw new RuntimeException('resource_token_invalid');
        }
    }

    protected function assertResource($resourceId, $contextKey)
    {
        if ($resourceId < 1) throw new InvalidArgumentException('resource_required');

        $resource = $this->modx->getObject(modResource::class, array(
            'id' => $resourceId,
            'context_key' => $contextKey,
            'deleted' => 0,
            'published' => 1,
        ));

        if (!$resource || (method_exists($resource, 'checkPolicy') && !$resource->checkPolicy('view'))) {
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
        if ($content === '') throw new InvalidArgumentException('content_required');
        if ($this->stringLength($content) > (int) $this->config['maxLength']) {
            throw new InvalidArgumentException('content_too_long');
        }
    }

    protected function assertRateLimit()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        if ($ip === '') return;

        $c = $this->modx->newQuery(Comment::class);
        $c->where(array(
            'ip_hash' => $this->hashClientValue($ip),
            'createdon:>=' => date('Y-m-d H:i:s', time() - (int) $this->config['rateLimitWindow']),
        ));

        if ((int) $this->modx->getCount(Comment::class, $c) >= (int) $this->config['rateLimitCount']) {
            throw new RuntimeException('rate_limit_exceeded');
        }
    }

    protected function setSecurityCookie($name, $value, $expires)
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie($name, $value, array(
            'expires' => (int) $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    }

    protected function getGuestOwnerHash($create = false)
    {
        $cookieName = 'modxcomments_owner';
        $token = isset($_COOKIE[$cookieName])
            ? preg_replace('/[^a-f0-9]/i', '', (string) $_COOKIE[$cookieName])
            : '';

        if (strlen($token) < 32 && $create) {
            try {
                $token = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                $token = hash('sha256', uniqid('', true) . mt_rand());
            }

            $this->setSecurityCookie($cookieName, $token, time() + 31536000);
            $_COOKIE[$cookieName] = $token;
        }

        if (strlen($token) < 32) return '';
        return hash('sha256', 'guest-owner|' . $token . '|' . $this->hashClientValue('owner'));
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

            $this->setSecurityCookie($cookieName, $token, time() + 31536000);
            $_COOKIE[$cookieName] = $token;
        }

        return hash('sha256', 'guest|' . $token . '|' . $this->hashClientValue('vote'));
    }

    protected function getVoteSummary($commentId, $voterHash = null)
    {
        $commentId = (int) $commentId;
        if ($voterHash === null) $voterHash = $this->getVoterHash();

        $up = (int) $this->modx->getCount(Vote::class, array(
            'comment_id' => $commentId,
            'value' => 1,
        ));
        $down = (int) $this->modx->getCount(Vote::class, array(
            'comment_id' => $commentId,
            'value' => -1,
        ));

        $mine = $this->modx->getObject(Vote::class, array(
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
        /*
         * User input remains plain text. We only recognize safe HTTP(S) links:
         *   https://example.com
         *   [Link text](https://example.com)
         */
        $pattern = '~(\\[[^\\]\\r\\n]{1,200}\\]\\(https?://[^\\s<>\\)]+\\)|https?://[^\\s<>]+)~iu';
        $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';

        foreach ($parts as $part) {
            if ($part === '') continue;

            if (preg_match('~^\\[([^\\]\\r\\n]{1,200})\\]\\((https?://[^\\s<>\\)]+)\\)$~iu', $part, $match)) {
                $label = htmlspecialchars($match[1], ENT_QUOTES, 'UTF-8');
                $url = htmlspecialchars($match[2], ENT_QUOTES, 'UTF-8');
                $html .= '<a href="' . $url . '" rel="nofollow ugc noopener" target="_blank">' . $label . '</a>';
            } elseif (preg_match('~^https?://[^\\s<>]+$~iu', $part)) {
                $safe = htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
                $html .= '<a href="' . $safe . '" rel="nofollow ugc noopener" target="_blank">' . $safe . '</a>';
            } else {
                $html .= htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
            }
        }

        return nl2br($html, false);
    }

    protected function getCommentAuthorMeta($comment)
    {
        $userId = (int) $comment->get('user_id');
        $name = (string) $comment->get('author_name');
        $isAdmin = false;

        if ($userId > 0) {
            $authorUser = $this->modx->getObject('MODX\\Revolution\\modUser', $userId);
            if ($authorUser) {
                $isAdmin = (bool) $authorUser->get('sudo') || $authorUser->isMember('Administrator');
                $profile = $authorUser->getOne('Profile');
                if ($profile && trim((string) $profile->get('fullname')) !== '') {
                    $name = trim((string) $profile->get('fullname'));
                } elseif (trim((string) $authorUser->get('username')) !== '') {
                    $name = trim((string) $authorUser->get('username'));
                }
            }
        }

        return array(
            'id' => $userId,
            'name' => $name,
            'isAdmin' => $isAdmin,
        );
    }

    protected function serializeComment($comment)
    {
        $deleted = $comment->get('status') === 'deleted';
        $user = $this->getCurrentUser();
        if ($user['authenticated']) {
            $owned = (int) $comment->get('user_id') === (int) $user['id'];
        } else {
            $knownHash = (string) $comment->get('guest_owner_hash');
            $currentHash = $this->getGuestOwnerHash(false);
            $owned = (int) $comment->get('user_id') === 0
                && $knownHash !== ''
                && $currentHash !== ''
                && (function_exists('hash_equals') ? hash_equals($knownHash, $currentHash) : $knownHash === $currentHash);
        }
        $editable = !$deleted && $owned && $this->isWithinEditWindow($comment);
        $replyTo = null;

        $parentId = (int) $comment->get('parent_id');
        if ($parentId > 0) {
            $parent = $this->modx->getObject(Comment::class, $parentId);
            if ($parent) {
                $parentText = trim(preg_replace('/\s+/u', ' ', (string) $parent->get('content')));
                if ($this->stringLength($parentText) > 180) {
                    $parentText = $this->stringSlice($parentText, 0, 177) . '…';
                }

                $parentAuthor = $this->getCommentAuthorMeta($parent);
                $replyTo = array(
                    'id' => (int) $parent->get('id'),
                    'author' => $parent->get('status') === 'deleted' ? '' : (string) $parentAuthor['name'],
                    'excerpt' => $parent->get('status') === 'deleted' ? '' : $parentText,
                    'deleted' => $parent->get('status') === 'deleted',
                );
            }
        }

        $authorMeta = $this->getCommentAuthorMeta($comment);

        return array(
            'id' => (int) $comment->get('id'),
            'parent' => $parentId,
            'thread' => (int) $comment->get('thread_id'),
            'depth' => (int) $comment->get('depth'),
            'status' => (string) $comment->get('status'),
            'deleted' => $deleted,
            'author' => array(
                'id' => (int) $comment->get('user_id'),
                'name' => $deleted ? '' : (string) $authorMeta['name'],
                'isAdmin' => !$deleted && (bool) $authorMeta['isAdmin'],
            ),
            'content' => $deleted ? '' : (string) $comment->get('content'),
            'contentHtml' => $deleted ? '' : (string) $comment->get('content_html'),
            'replyTo' => $replyTo,
            'votes' => $deleted ? array('up' => 0, 'down' => 0, 'score' => 0, 'mine' => 0) : $this->getVoteSummary((int) $comment->get('id')),
            'created' => (string) $comment->get('createdon'),
            'createdTs' => (int) strtotime((string) $comment->get('createdon')),
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
