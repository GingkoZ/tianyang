<?php
namespace Topxia\Service\CloudPlatform\Client;

use Psr\Log\LoggerInterface;

class AbstractCloudAPI
{
    const VERSION = 'v1';

    protected $userAgent = 'EduSoho Cloud API Client 1.0';

    protected $connectTimeout = 15;

    protected $timeout = 15;

    protected $apiUrl = 'http://api.edusoho.net';

    protected $debug = false;

    protected $logger = null;

    public function __construct(array $options)
    {
        $this->setKey($options['accessKey'], $options['secretKey']);

        if (!empty($options['apiUrl'])) {
            $this->setApiUrl($options['apiUrl']);
        }

        $this->debug = empty($options['debug']) ? false : true;
    }

    public function setApiUrl($url)
    {
        $this->apiUrl = rtrim($url, '/');
        return $this;
    }

    public function setKey($accessKey, $secretKey)
    {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;

        return $this;
    }

    public function getAccessKey()
    {
        return $this->accessKey;
    }

    public function post($uri, array $params = array(), array $header = array())
    {
        return $this->_request('POST', $uri, $params, $header);
    }

    public function put($uri, array $params = array(), array $header = array())
    {
        return $this->_request('PUT', $uri, $params, $header);
    }

    public function patch($uri, array $params = array(), array $header = array())
    {
        return $this->_request('PATCH', $uri, $params, $header);
    }

    public function get($uri, array $params = array(), array $header = array())
    {
        return $this->_request('GET', $uri, $params, $header);
    }

    public function delete($uri, array $params = array(), array $header = array())
    {
        return $this->_request('DELETE', $uri, $params, $header);
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    protected function _request($method, $uri, $params, $headers)
    {
        $requestId = substr(md5(uniqid('', true)), -16);

        $url = $this->apiUrl.'/'.self::VERSION.$uri;
        $this->debug && $this->logger && $this->logger->debug("[{$requestId}] {$method} {$url}", array('params' => $params, 'headers' => $headers));

        $headers[] = 'Content-type: application/json';

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_USERAGENT, $this->userAgent);

        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 1);

        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        } else
        if ($method == 'PUT') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        } else
        if ($method == 'DELETE') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        } else
        if ($method == 'PATCH') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        } else {
            if (!empty($params)) {
                $url = $url.(strpos($url, '?') ? '&' : '?').http_build_query($params);
            }
        }

        $headers[] = 'Auth-Token: '.$this->_makeAuthToken($url, $method == 'GET' ? array() : $params);
        $headers[] = 'API-REQUEST-ID: '.$requestId;

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_URL, $url);

        $response = curl_exec($curl);
        $curlinfo = curl_getinfo($curl);

        $header = substr($response, 0, $curlinfo['header_size']);
        $body   = substr($response, $curlinfo['header_size']);

        $this->debug && $this->logger && $this->logger->debug("[{$requestId}] CURL_INFO", $curlinfo);
        $this->debug && $this->logger && $this->logger->debug("[{$requestId}] RESPONSE_HEADER {$header}");
        $this->debug && $this->logger && $this->logger->debug("[{$requestId}] RESPONSE_BODY {$body}");

        curl_close($curl);

        $context = array(
            'CURLINFO' => $curlinfo,
            'HEADER'   => $header,
            'BODY'     => $body
        );

        if (empty($curlinfo['namelookup_time'])) {
            $this->logger && $this->logger->error("[{$requestId}] NAME_LOOK_UP_TIMEOUT", $context);
        }

        if (empty($curlinfo['connect_time'])) {
            $this->logger && $this->logger->error("[{$requestId}] API_CONNECT_TIMEOUT", $context);
            throw new CloudAPIIOException("Connect api server timeout (url: {$url}).");
        }

        if (empty($curlinfo['starttransfer_time'])) {
            $this->logger && $this->logger->error("[{$requestId}] API_TIMEOUT", $context);
            throw new CloudAPIIOException("Request api server timeout (url:{$url}).");
        }

        if ($curlinfo['http_code'] >= 500) {
            $this->logger && $this->logger->error("[{$requestId}] API_RESOPNSE_ERROR", $context);
            throw new CloudAPIIOException("Api server internal error (url:{$url}).");
        }

        $result = json_decode($body, true);

        if (empty($result)) {
            $this->logger && $this->logger->error("[{$requestId}] RESPONSE_JSON_DECODE_ERROR", $context);
            throw new CloudAPIIOException("Api result json decode error: (url:{$url}).");
        }

        return $result;
    }

    protected function _makeAuthToken($url, $params)
    {
        $matched = preg_match('/:\/\/.*?(\/.*)$/', $url, $matches);

        if (!$matched) {
            throw new \RuntimeException('Make AuthToken Error.');
        }

        $text = $matches[1]."\n".json_encode($params)."\n".$this->secretKey;

        $hash = md5($text);

        return "{$this->accessKey}:{$hash}";
    }
}
