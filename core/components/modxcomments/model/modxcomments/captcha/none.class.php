<?php
require_once dirname(__FILE__) . '/captchaprovider.interface.php';

class ModxCommentsCaptchaNone implements ModxCommentsCaptchaProviderInterface
{
    public function verify($token, array $context = array())
    {
        return true;
    }
}
