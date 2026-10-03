<?php
class ModxCommentsCommentCountProcessor extends modProcessor
{
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

            return $this->success('', array(
                'total' => $this->comments->getCommentCount($resource, $context, (string) $this->getProperty('resource_token', '')),
            ));
        } catch (Exception $e) {
            return $this->failure($e->getMessage());
        }
    }
}
return 'ModxCommentsCommentCountProcessor';
