<?php
class ModxCommentsCommentGetListProcessor extends modProcessor
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
        try {
            $resource = (int) $this->getProperty('resource', 0);
            $context = $this->comments->cleanContextKey($this->getProperty('context', 'web'));
            $limit = (int) $this->getProperty('limit', 200);
            return $this->success('', $this->comments->getComments($resource, $context, $limit));
        } catch (Exception $e) {
            return $this->failure($e->getMessage());
        }
    }
}
return 'ModxCommentsCommentGetListProcessor';
