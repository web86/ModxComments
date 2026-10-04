<?php
require_once dirname(__FILE__) . '/captchaprovider.interface.php';

abstract class ModxCommentsCaptchaHttpProvider implements ModxCommentsCaptchaProviderInterface
{
    protected $modx;

    public function __construct($modx)
    {
        $this->modx = $modx;
    }

    protected function postForm($url, array $data)
    {
        $payload = http_build_query($data, '', '&');
        $response = false;

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ));
            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $status < 200 || $status >= 300) {
                return false;
            }
        } elseif (ini_get('allow_url_fopen')) {
            $stream = stream_context_create(array(
                'http' => array(
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $payload,
                    'timeout' => 10,
                    'ignore_errors' => true,
                ),
                'ssl' => array(
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ),
            ));

            $response = @file_get_contents($url, false, $stream);
            if ($response === false) {
                return false;
            }
        } else {
            return false;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : false;
    }

    protected function remoteIp(array $context)
    {
        return isset($context['remoteIp']) ? trim((string) $context['remoteIp']) : '';
    }
}
