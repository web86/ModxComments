<?php
require_once dirname(__FILE__) . '/http.class.php';

class ModxCommentsHCaptcha extends ModxCommentsCaptchaHttpProvider
{
    protected $secret;
    protected $siteKey;

    public function __construct($modx, $secret, $siteKey = '')
    {
        parent::__construct($modx);
        $this->secret = trim((string) $secret);
        $this->siteKey = trim((string) $siteKey);
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
        if ($this->siteKey !== '') {
            $data['sitekey'] = $this->siteKey;
        }

        $decoded = $this->postForm('https://api.hcaptcha.com/siteverify', $data);
        return is_array($decoded) && !empty($decoded['success']);
    }
}
