<?php
/*
 * Copyright 2013 Jan Eichhorn <exeu65@googlemail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace ZalandoPHP\Request\Rest;

use ZalandoPHP\ZalandoPHP;
use ZalandoPHP\Configuration\ConfigurationInterface;
use ZalandoPHP\Operations\OperationInterface;
use ZalandoPHP\Request\RequestInterface;
use ZalandoPHP\Request\Util;

/**
 * Basic implementation of the rest request
 *
 * @author Jan Eichhorn <exeu65@googlemail.com>
 */
class Request implements RequestInterface
{
    /**
     * Connection time out in seconds
     *
     * @var int
     */
    const CONNECTION_TIMEOUT = CURLOPT_CONNECTTIMEOUT;

    /**
     * Time out in seconds
     *
     * @var int
     */
    const TIMEOUT = CURLOPT_TIMEOUT;

    /**
     * Enable/Disable location following
     *
     * @var int
     */
    const FOLLOW_LOCATION = CURLOPT_FOLLOWLOCATION;

    /**
     * Useragent
     *
     * @var string
     */
    const USERAGENT = CURLOPT_USERAGENT;

    /**
     * curl options
     *
     * @var array
     */
    private $options = array();

    /**
     * The requestscheme
     *
     * @var string
     */
    protected $requestScheme = "https://api.zalando.com/";

    /**
     * @var ConfigurationInterface
     */
    protected $configuration;

    /**
     * Initialize instance
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->options = array(
            self::USERAGENT          => "ZalandoPHP [" . ZalandoPHP::VERSION . "]",
            self::CONNECTION_TIMEOUT => 10,
            self::TIMEOUT            => 10,
            self::FOLLOW_LOCATION    => 1
        );
        $this->setOptions($options);
    }

    /**
     * Sets the curl options
     *
     * @param array $options
     */
    public function setOptions(array $options = array())
    {
        foreach ($options as $currentOption => $currentOptionValue) {
            $this->options[$currentOption] = $currentOptionValue;
        }
        $this->options[CURLOPT_RETURNTRANSFER] = 1; // force the return transfer
    }

    /**
     * return the current curl options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritdoc}
     */
    public function setConfiguration(ConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }




















    /**
     * {@inheritdoc}
     */
    public function perform(OperationInterface $operation)
    {

        // prep headers & query string
        $headers        = $this->buildHeaders();
        $queryString    = $this->buildQueryString($operation->getOperationFilter());

        $requestUrl = $this->requestScheme . $operation->getEndpoint() . (!Empty($queryString) ? '?' .$queryString : '');
//        $requestUrl = 'http://zalando.dev:80/Samples/debug_headers.php';

//        die($requestUrl);

        $params = [
            'headers'               => $headers,
            'debug'                 => ZalandoPHP::DEBUG,
            //'exceptions'          => true
            'exceptions'            => false,
            'timeout'               => !is_null($this->configuration->getTimeout()) ? $this->configuration->getTimeout() : $this->options[self::TIMEOUT],
            'connect_timeout'       => !is_null($this->configuration->getConnectionTimeout()) ? $this->configuration->getConnectionTimeout() : $this->options[self::CONNECTION_TIMEOUT]
        ];

        // init Guzzle client
        $guzzle = new \GuzzleHttp\Client();

        if (!is_object($guzzle)) {
            throw new \RuntimeException('Cannot initialize Guzzle');
        }

        // send request via Guzzle
        $response = $guzzle->get($requestUrl, $params);

        // Zalando returns a 400 error when nothing is found, let's just forward their errors instead of throwing exceptions
//        if ($response->getStatusCode() != 200) {
//            $body = json_decode($response->getBody());
//
//            throw new \RuntimeException(
//                sprintf(
//                    "An error occurred while sending request. Status code: %d; Url: %s; Message: %s; Errors: %s",
//                    $body->status,
//                    $requestUrl,
//                    $body->message,
//                    @json_encode($body->errors)
//                )
//            );
//        }

        return $response->getBody();
    }



    /**
     * DEPRECATED
     * {@inheritdoc}
     */
    public function performCurl(OperationInterface $operation)
    {
        $ch = curl_init();

        if (false === $ch) {
            throw new \RuntimeException('Cannot initialize curl resource');
        }

        $headers        = $this->buildHeaders();
        $queryString    = $this->buildQueryString($operation->getOperationFilter());

        $options                        = $this->options;
        $options[CURLOPT_HEADER]        = true;
        $options[CURLINFO_HEADER_OUT]   = true;
        $options[CURLOPT_HTTPHEADER]    = $headers;
        $options[CURLOPT_URL]           = $this->requestScheme . $operation->getEndpoint() . (!Empty($queryString) ? '?' .$queryString : '');

        // overwrite default options here
        if (!is_null($this->configuration->getTimeout())) {
            $options[self::TIMEOUT]             = $this->configuration->getTimeout();
        }
        if (!is_null($this->configuration->getConnectionTimeout())) {
            $options[self::CONNECTION_TIMEOUT]  = $this->configuration->getConnectionTimeout();
        }

        // show curl debug?
        if(ZalandoPHP::DEBUG) {
            $options[CURLOPT_VERBOSE]   = true;
        }

        foreach ($options as $currentOption => $currentOptionValue) {
            if (false === curl_setopt($ch, $currentOption, $currentOptionValue)) {
                throw new \RuntimeException(
                    sprintf(
                        "An error occurred while setting %s with value %s",
                        $currentOption,
                        $currentOptionValue
                    )
                );
            }
        }

        $curlError = false;
        $errorNumber = null;
        $errorMessage = null;

        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $info = curl_getinfo($ch);
        $result = curl_exec($ch);

        if (false === $result) {
            $curlError = true;
            $errorNumber = curl_errno($ch);
            $errorMessage = curl_error($ch);
        }

        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException(
                sprintf(
                    "An error occurred while sending request. Error number: %d; Error message: %s",
                    $errorNumber,
                    $errorMessage
                )
            );
        }

        // debug!
//        var_dump($options[CURLOPT_URL]);
//        echo '<pre>';
//        print_r($headers);
//        echo '</pre>';
////        echo '<pre>';
////        print_r($options);
////        echo '</pre>';
//        echo '<pre>';
//        print_r($info);
//        echo '</pre>';
//        echo '<pre>';
//        print_r($result);
//        echo '</pre>';
//        exit;

        return $result;
    }

    /**
     * Builds the http headers
     *
     * @param array $params
     *
     * @return array
     */
    protected function buildHeaders()
    {
        $headers = [];
        $headers["Signature"]           = "ZalandoPHP";
        $headers["Accept-Encoding"]     = "gzip";
//        $headers["test"]                = "blaat";

        // only set when input is geven
        if(!Empty($this->configuration->getClientName())) {
            $headers["x-client-name"]       = $this->configuration->getClientName();
        }
        if(!Empty($this->configuration->getLocale()))  {
            $headers["Accept-Language"]     = $this->configuration->getLocale();
        }

        return $headers;
    }

    /**
     * Builds the final query string
     *
     * @param array $params
     *
     * @return string
     */
    protected function buildQueryString(array $params)
    {
        $parameterList = [];
        foreach ($params as $key => $value) {
            // is bool?
            if(is_bool($value)) {
                // replace boolean with 'string' value
                if($value === true) {
                    $value = 'true';
                }
                if($value === false) {
                    $value = 'false';
                }
            }

            // has multiple values?
            if(strpos($value, ',')) {
                $multiples = explode(',', $value);

                foreach($multiples as $multiple) {
                    $parameterList[] = sprintf('%s=%s', $key, rawurlencode($multiple));
                }
            }
            else {
                $parameterList[] = sprintf('%s=%s', $key, rawurlencode($value));
            }
        }

        //$parameterList[] = 'Signature=' . rawurlencode($this->buildSignature($parameterList));

        return implode("&", $parameterList);
    }
}
