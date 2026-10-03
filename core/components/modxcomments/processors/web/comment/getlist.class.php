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
            $page = max(1, (int) $this->getProperty('page', 1));
            $perPage = (int) $this->getProperty('per_page', 0);
            if ($perPage < 1) $perPage = null;
            return $this->success('', $this->comments->getComments($resource, $context, $page, $perPage));
        } catch (Exception $e) {
            return $this->failure($e->getMessage());
        }
    }
}
return 'ModxCommentsCommentGetListProcessor';
