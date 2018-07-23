<?php

namespace Starif\ApiWrapper\Transports;

use Curl\Curl as CurlClient;
use Starif\ApiWrapper\ApiException;
use Starif\ApiWrapper\TransportInterface;

class Curl implements TransportInterface
{
    /**
     * @var string
     */
    protected $jwt;

    /**
     * @var null|string
     */
    protected $entrypoint;

    /**
     * @var CurlClient
     */
    protected $client;

    /**
     * Curl constructor.
     *
     * @param string     $jwt
     * @param string     $entrypoint
     * @param CurlClient $client
     */
    public function __construct(string $jwt, string $entrypoint, CurlClient $client)
    {
        $this->client = $client;
        $this->jwt = $jwt;
        $this->entrypoint = rtrim($entrypoint, '/').'/';
        $this->client->setHeader('Authorization', 'Bearer '.$this->jwt);
        $this->client->setHeader('Content-Type', 'application/json');
    }

    /**
     * {@inheritdoc}
     */
    public function request($endpoint, array $data = [], $method = 'get'): array
    {
        $method = strtolower($method);

        switch ($method) {
            case 'get':
                $url = $this->getUrl($endpoint, $data);
                $this->client->get($url);
                break;
            case 'post':
                $url = $this->getUrl($endpoint);
                $this->client->post($url, json_encode($data));
                break;
            case 'put':
                $url = $this->getUrl($endpoint);
                $this->client->put($url, json_encode($data));
                break;
            case 'delete':
                $url = $this->getUrl($endpoint);
                $this->client->delete($url, json_encode($data));
                break;
        }

        if (!($this->client->httpStatusCode >= 200 && $this->client->httpStatusCode <= 299)) {
            $response = json_decode($this->client->rawResponse, true);
            if ($response === null && json_last_error() !== JSON_ERROR_NONE && !isset($response['message'])) {
                throw new \Exception($this->client->rawResponse ?? $this->client->errorMessage);
            }
            throw new ApiException($response, $this->client->httpStatusCode);
        }

        if (!$this->client->rawResponse) {
            return [];
        }

        return json_decode($this->client->rawResponse, true);
    }

    /**
     * Build URL with stored entrypoint, the endpoint and data queries.
     *
     * @param string $endpoint
     * @param array  $data
     * @return string
     */
    protected function getUrl(string $endpoint, array $data = [])
    {
        $url = $this->entrypoint.ltrim($endpoint, '/');
        return (count($data)) ? $url.'?'.http_build_query($data) : $url;
    }
}
