<?php

/**
 * TwitterOAuth - https://github.com/ricardoper/TwitterOAuth
 * PHP library to communicate with Twitter OAuth API version 1.1
 *
 * @author Ricardo Pereira <github@ricardopereira.es>
 * @copyright 2013
 */

namespace TwitterOAuth;

class TwitterOAuth
{
    protected $url = 'https://api.twitter.com/1.1/';

    protected $format = 'json';

    protected $config = array();

    protected $call = '';

    protected $method = 'GET';

    protected $getParams = array();

    protected $postParams = array();


    /**
     * Prepare a new conection with Twitter API via OAuth
     *
     * @params array $config Configuration array with OAuth access data
     */
    public function __construct(array $config)
    {
        $keys = array(
            'consumer_key' => '',
            'consumer_secret' => '',
            'oauth_token' => '',
            'oauth_token_secret' => ''
        );

        if (count(array_intersect_key($keys, $config)) !== count($keys)) {
            throw new \Exception('Missing parameters in configuration array');
        }

        $this->config = $config;

        unset($keys, $config);
    }

    /**
     * Send a GET call to Twitter API via OAuth
     *
     * @params string $call Twitter resource string
     * @params array $getParams GET parameters to send
     * @params string $format Set the response format
     */
    public function get($call, array $getParams = null, $format = null)
    {
        $this->call = $call;

        if ($getParams !== null && is_array($getParams)) {
            $this->getParams = $getParams;
        }

        if ($format !== null) {
            $this->format = $format;
        }

        return $this->sendRequest();
    }

    /**
     * Send a POST call to Twitter API via OAuth
     *
     * @params string $call Twitter resource string
     * @params array $postParams POST parameters to send
     * @params array $getParams GET parameters to send
     * @params string $format Set the response format
     */
    public function post($call, array $postParams = null, array $getParams = null, $format = null)
    {
        $this->call = $call;

        $this->method = 'POST';

        if ($postParams !== null && is_array($postParams)) {
            $this->postParams = $postParams;
        }

        if ($getParams !== null && is_array($getParams)) {
            $this->getParams = $getParams;
        }

        if ($format !== null) {
            $this->format = $format;
        }

        return $this->sendRequest();
    }

    /**
     * Converting parameters array to a single string with encoded values
     *
     * @param array $params Input parameters
     * @return string Single string with encoded values
     */
    protected function getParams(array $params)
    {
        $r = '';

        ksort($params);

        foreach ($params as $key => $value) {
            $r .= '&' . $key . '=' . rawurlencode($value);
        }

        unset($params, $key, $value);

        return trim($r, '&');
    }

    /**
     * Getting full URL from a Twitter resource and format
     *
     * @param bool $withParams If true then parameters will be outputted
     * @return string Full URL
     */
    protected function getUrl($withParams = false)
    {
        $getParams = '';

        if ($withParams === true) {
            $getParams = $this->getParams($this->getParams);

            if (!empty($getParams)) {
                $getParams = '?' . $getParams;
            }
        }

        return $this->url . $this->call . '.' . $this->format . $getParams;
    }

    /**
     * Getting OAuth parameters to be used in request headers
     *
     * @return array OAuth parameters
     */
    protected function getOauthParameters()
    {
        $time = time();

        return array(
            'oauth_consumer_key' => $this->config['consumer_key'],
            'oauth_nonce' => trim(base64_encode($time), '='),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => $time,
            'oauth_token' => $this->config['oauth_token'],
            'oauth_version' => '1.0'
        );
    }

    /**
     * Converting all parameters arrays to a single string with encoded values
     *
     * @return string Single string with encoded values
     */
    protected function getRequestString()
    {
        $params = array_merge($this->getParams, $this->postParams, $this->getOauthParameters());

        $params = $this->getParams($params);

        return rawurlencode($params);
    }

    /**
     * Getting OAuth signature base string
     *
     * @return string OAuth signature base string
     */
    protected function getSignatureBaseString()
    {
        $method = strtoupper($this->method);

        $url = rawurlencode($this->getUrl());

        return $method . '&' . $url . '&' . $this->getRequestString();
    }

    /**
     * Getting a signing key
     *
     * @return string Signing key
     */
    protected function getSigningKey()
    {
        return $this->config['consumer_secret'] . '&' . $this->config['oauth_token_secret'];
    }

    /**
     * Calculating the signature
     *
     * @return string Signature
     */
    protected function calculateSignature()
    {
        return base64_encode(hash_hmac('sha1', $this->getSignatureBaseString(), $this->getSigningKey(), true));
    }

    /**
     * Converting OAuth parameters array to a single string with encoded values
     *
     * @return string Single string with encoded values
     */
    protected function getOauthString()
    {
        $oauth = array_merge($this->getOauthParameters(), array('oauth_signature' => $this->calculateSignature()));

        ksort($oauth);

        $values = array();

        foreach ($oauth as $key => $value) {
            $values[] = $key . '="' . rawurlencode($value) . '"';
        }

        $oauth = implode(', ', $values);

        unset($values, $key, $value);

        return $oauth;
    }

    /**
     * Building request HTTP headers
     *
     * @return array HTTP headers
     */
    protected function buildRequestHeader()
    {
        return array(
            'Authorization: OAuth ' . $this->getOauthString(),
            'Expect:'
        );
    }

    /**
     *  Send GET or POST requests to Twitter API
     *
     * @throws Exception\TwitterException
     * @return mixed Response output with the selected format
     */
    protected function sendRequest()
    {
        $url = $this->getUrl(true);

        $header = $this->buildRequestHeader();

        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        );

        if (!empty($this->postParams)) {
            $options[CURLOPT_POST] = count($this->postParams);
            $options[CURLOPT_POSTFIELDS] = $this->getParams($this->postParams);
        }

        $c = curl_init();

        curl_setopt_array($c, $options);

        $response = curl_exec($c);

        curl_close($c);

        unset($options, $c);

        $response = json_decode($response);
        
        if(isset($response->errors)) {
            foreach($response->errors as $error) {
                throw new TwitterException($error->message, $error->code);
            }
        }
        
        return $response;
    }
}
