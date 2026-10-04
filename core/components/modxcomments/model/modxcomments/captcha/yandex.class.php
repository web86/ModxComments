<?php
require_once dirname(__FILE__) . '/http.class.php';

class ModxCommentsYandexSmartCaptcha extends ModxCommentsCaptchaHttpProvider
{
    protected $serverKey;

    public function __construct($modx, $serverKey)
    {
        parent::__construct($modx);
        $this->serverKey = trim((string) $serverKey);
    }

    public function verify($token, array $context = array())
    {
        $token = trim((string) $token);
        if ($this->serverKey === '' || $token === '') return false;

        $data = array(
            'secret' => $this->serverKey,
            'token' => $token,
        );

        $remoteIp = $this->remoteIp($context);
        if ($remoteIp !== '') {
            $data['ip'] = $remoteIp;
        }

        $decoded = $this->postForm(
            'https://smartcaptcha.cloud.yandex.ru/validate',
            $data
        );

        return is_array($decoded)
            && isset($decoded['status'])
            && $decoded['status'] === 'ok';
    }
}
