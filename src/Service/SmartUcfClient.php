<?php

namespace Gentor\SmartUcf\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;


/**
 * Class SmartUcfClient
 *
 * @package Gentor\SmartUcf\Service
 */
class SmartUcfClient
{
    /**
     * Test endpoint
     */
    const TEST_ENDPOINT = 'https://onlinetest.ucfin.bg/';

    /**
     * Live endpoint
     */
    const LIVE_ENDPOINT = 'https://online.ucfin.bg/';

    /**
     * @var Client
     */
    protected $http;

    /**
     * @var string
     */
    protected $user;
    /**
     * @var string
     */
    protected $pass;

    /**
     * SmartUcf constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->user = $config['username'];
        $this->pass = $config['password'];

        $requestOptions = [
            'headers' => [
                'Accept' => 'application/json'
            ],
            'verify' => $config['verify_ssl'] ?? true,
            'timeout' => $config['timeout'] ?? 0,
        ];

        if (isset($config['cert_path'])) {
            $requestOptions = array_merge($requestOptions, [
                'cert' => [
                    $config['cert_path'],
                    $config['cert_pass'] ?? null
                ]
            ]);
        }

        if (isset($config['base_uri'])) {
            $requestOptions['base_uri'] = $config['base_uri'];
        }

        $this->http = new Client(array_merge([
            'base_uri' => $config['test_mode'] ? static::TEST_ENDPOINT : static::LIVE_ENDPOINT,
        ], $requestOptions));
    }

    /**
     * @param array $params
     * @return string
     * @throws SmartUcfException
     */
    public function sessionStart(array $params): string
    {
        $this->validateParams($params, [
            'orderNo',
            'onlineProductCode',
            'totalPrice',
            'installmentCount',
            'monthlyPayment',
        ]);

        /** @var Response $response */
        $response = $this->http->post($this->getServiceUrl('sucfOnlineSessionStart'),
            [
                'json' => $params
            ]);

        return $this->handleResponse($response)->sucfOnlineSessionID;
    }

    /**
     * @param $orderNo
     * @return \stdClass
     * @throws SmartUcfException
     */
    public function getStatus($orderNo): \stdClass
    {
        $params = ['orderNo' => $orderNo];
        $this->validateParams($params, ['orderNo']);

        /** @var Response $response */
        $response = $this->http->post($this->getServiceUrl('getOrderStatus'),
            [
                'json' => $params
            ]);

        return $this->handleResponse($response);
    }

    /**
     * @param $orderNo
     * @return \stdClass
     * @throws SmartUcfException
     */
    public function getInfo($orderNo): \stdClass
    {
        $params = ['orderNo' => $orderNo];
        $this->validateParams($params, ['orderNo']);

        /** @var Response $response */
        $response = $this->http->post($this->getServiceUrl('getOrderInfo'),
            [
                'json' => $params
            ]);

        return $this->handleResponse($response);
    }

    /**
     * @param array $params
     * @return \stdClass
     * @throws SmartUcfException
     */
    public function getCoeff(array $params = []): \stdClass
    {
        $this->validateParams($params, []);

        /** @var Response $response */
        $response = $this->http->post($this->getServiceUrl('getCoeff'),
            [
                'json' => $params
            ]);

        return $this->handleResponse($response);
    }

    /**
     * @param $suosId
     * @return string
     */
    public function redirect($suosId): string
    {
        $html = file_get_contents(__DIR__ . '/redirect.html');
        $html = str_replace('{$url}', $this->getRedirectUrl(), $html);
        $html = str_replace('{$suosId}', $suosId, $html);

        return $html;
    }

    /**
     * @param array $params
     * @return \stdClass
     * @throws SmartUcfException
     */
    public function sendInvoice(array $params): \stdClass
    {
        $this->validateParams($params, [
            'orderNo',
            'invoiceNo',
            'invoiceFile',
        ]);

        /** @var Response $response */
        $response = $this->http->post($this->getServiceUrl('sendInvoice'),
            [
                'json' => $params
            ]);

        return $this->handleResponse($response);
    }

    /**
     * @param string $service
     * @return string
     */
    protected function getServiceUrl(string $service): string
    {
        return 'suos/api/otp/' . $service;
    }

    /**
     * @return string
     */
    protected function getRedirectUrl()
    {
        return (string)$this->http->getConfig('base_uri') . 'sucf-online/Request/Create';
    }

    /**
     * @param array $params
     * @param array $requiredParams
     * @throws SmartUcfException
     */
    protected function validateParams(array &$params, array $requiredParams)
    {
        foreach ($requiredParams as $requiredParam) {
            if (empty($params[$requiredParam])) {
                throw new SmartUcfException("Invalid value for parameter '{$requiredParam}'");
            }
        }

        $params['user'] = $this->user;
        $params['pass'] = $this->pass;
    }

    /**
     * @param Response $response
     * @return \stdClass
     * @throws SmartUcfException
     */
    protected function handleResponse(Response $response): \stdClass
    {
        $json = (string)$response->getBody();
        $data = json_decode($json);

        if (!empty($data->errorCode)) {
            throw new SmartUcfException($data->errorText, $data->errorCode, $data);
        }

        unset($data->errorCode, $data->errorText);

        return $data;
    }
}