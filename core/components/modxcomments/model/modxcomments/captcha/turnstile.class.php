<?php
class ModxCommentsTurnstile
{
    protected $modx;
    protected $secret;

    public function __construct(modX $modx, $secret)
    {
        $this->modx = $modx;
        $this->secret = trim((string) $secret);
    }

    public function verify($token, $remoteIp = '')
    {
        $token = trim((string) $token);
        if ($this->secret === '' || $token === '') return false;

        $payload = http_build_query(array(
            'secret' => $this->secret,
            'response' => $token,
            'remoteip' => (string) $remoteIp,
        ), '', '&');

        $response = false;

        if (function_exists('curl_init')) {
            $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
            curl_setopt_array($ch, array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
            ));
            $response = curl_exec($ch);
            curl_close($ch);
        } elseif (ini_get('allow_url_fopen')) {
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $payload,
                    'timeout' => 10,
                ),
            ));
            $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
        }

        if (!$response) return false;

        $decoded = json_decode($response, true);
        return is_array($decoded) && !empty($decoded['success']);
    }
}
