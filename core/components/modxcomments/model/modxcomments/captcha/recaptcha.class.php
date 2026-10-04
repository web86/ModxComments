<?php
require_once dirname(__FILE__) . '/http.class.php';

class ModxCommentsReCaptcha extends ModxCommentsCaptchaHttpProvider
{
    protected $secret;
    protected $version;
    protected $minScore;
    protected $expectedAction;

    public function __construct($modx, $secret, $version = 'v2', $minScore = 0.5, $expectedAction = 'comment')
    {
        parent::__construct($modx);
        $this->secret = trim((string) $secret);
        $this->version = $version === 'v3' ? 'v3' : 'v2';
        $this->minScore = max(0.0, min(1.0, (float) $minScore));
        $this->expectedAction = preg_replace('/[^a-zA-Z0-9_\/]/', '', (string) $expectedAction);
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

        $decoded = $this->postForm('https://www.google.com/recaptcha/api/siteverify', $data);
        if (!is_array($decoded) || empty($decoded['success'])) {
            return false;
        }

        if ($this->version !== 'v3') {
            return true;
        }

        $score = isset($decoded['score']) ? (float) $decoded['score'] : -1.0;
        $action = isset($decoded['action']) ? (string) $decoded['action'] : '';

        return $score >= $this->minScore
            && $this->expectedAction !== ''
            && $action === $this->expectedAction;
    }
}
