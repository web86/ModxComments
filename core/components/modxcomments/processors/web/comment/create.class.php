<?php
class ModxCommentsCommentCreateProcessor extends modProcessor
{
    /** @var ModxComments */
    protected $comments;

    public function initialize()
    {
        $corePath = $this->modx->getOption('modxcomments.core_path', null, MODX_CORE_PATH . 'components/modxcomments/');
        require_once $corePath . 'model/modxcomments/modxcomments.class.php';
        $this->comments = new ModxComments($this->modx);
        return true;
    }

    public function process()
    {
        $token = isset($_SERVER['HTTP_X_MODXCOMMENTS_CSRF'])
            ? (string) $_SERVER['HTTP_X_MODXCOMMENTS_CSRF']
            : (string) $this->getProperty('_csrf', '');

        if (!$this->comments->validateCsrfToken($token)) {
            return $this->failure('csrf_invalid');
        }

        try {
            $comment = $this->comments->createComment($this->getProperties());
            return $this->success('', array('comment' => $comment));
        } catch (InvalidArgumentException $e) {
            return $this->failure($e->getMessage());
        } catch (RuntimeException $e) {
            return $this->failure($e->getMessage());
        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, '[ModxComments] ' . $e->getMessage());
            return $this->failure('server_error');
        }
    }
}
return 'ModxCommentsCommentCreateProcessor';
