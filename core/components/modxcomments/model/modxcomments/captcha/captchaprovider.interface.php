<?php
interface ModxCommentsCaptchaProviderInterface
{
    /**
     * @param string $token
     * @param array $context
     * @return bool
     */
    public function verify($token, array $context = array());
}
