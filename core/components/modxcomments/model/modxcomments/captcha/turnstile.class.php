<?php
require_once dirname(__FILE__) . '/http.class.php';

class ModxCommentsTurnstile extends ModxCommentsCaptchaHttpProvider
{
    protected $secret;

    public function __construct($modx, $secret)
    {
        parent::__construct($modx);
        $this->secret = trim((string) $secret);
    }

    public function verify($token, array $context = array())
    {
        $token = trim((string) $token);
        if ($this->secret === '' || $token === '') return false;

        $data = array(
            'secret' => $this->secret,
            'response' => $token,
        );

        $remoteIp = $this->remoteIp($context);
        if ($remoteIp !== '') {
            $data['remoteip'] = $remoteIp;
        }

        $decoded = $this->postForm(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            $data
        );

        return is_array($decoded) && !empty($decoded['success']);
    }
}
