<?php

namespace Cpro\ApiWrapper;

use Cpro\ApiWrapper\Concerns\HasCache;
use Cpro\ApiWrapper\Transports\TransportInterface;

/**
 * Class Api.
 *
 * @throws Cpro\ApiWrapper\Exceptions\ApiException
 */
class Api
{
    use HasCache;

    /**
     * @var TransportInterface
     */
    protected $transport;

    /**
     * Api constructor.
     *
     * @param TransportInterface $transport
     */
    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Get current instance of TransportInterface.
     *
     * @return TransportInterface
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * Magical use for getObjects(), getObject(), createObject(), updateObject(), deleteObject().
     *
     * @param $name
     * @param $arguments
     *
     * @return array
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this, $name)) {
            return call_user_func_array([$this, $name], $arguments);
        }

        preg_match('/^(get|create|update|delete)([\w-\_\/]+?)$/', $name, $matches);

        $endpoint = strtolower($matches[2]);
        if ('get' === $matches[1]) {
            if (isset($arguments[0]) && !is_array($arguments[0])) {
                return call_user_func_array([$this, 'findOne'], array_merge([$endpoint], $arguments));
            }

            return call_user_func_array([$this, 'findAll'], array_merge([$endpoint], $arguments));
        }

        return call_user_func_array([$this, $matches[1]], array_merge([$endpoint], $arguments));
    }

    /**
     * Call API for find all results of the entrypoint.
     *
     * @param string $endpoint
     * @param array  $filters
     *
     * @return array
     */
    protected function findAll($endpoint, array $filters = [])
    {
        $key = md5(__FUNCTION__.$endpoint.json_encode($filters));

        if ($this->hasCache($key)) {
            return $this->getCache($key);
        }

        $res = [];
        try {
            $res = $this->getTransport()->request('/'.$endpoint, $filters) ?: [];
        } catch (\Exception $e) {
            //
        }

        return $this->setCache($key, $res);
    }

    /**
     * Call API for find entity of entrypoint.
     *
     * @param string $endpoint
     * @param int    $id
     * @param array  $filters
     *
     * @return array
     */
    protected function findOne($endpoint, $id, array $filters = [])
    {
        $uri = '/'.$endpoint.'/'.$id;
        $key = $uri.'?'.http_build_query($filters);
        if ($this->hasCache($key)) {
            return $this->getCache($key);
        }

        try {
            return $this->getTransport()->request($uri, $filters) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Call API for update an entity.
     *
     * @param string $endpoint
     * @param int    $id
     * @param        $attributes
     *
     * @return array
     */
    protected function update($endpoint, $id, $attributes)
    {
        $key = $endpoint.'/'.$id.'?';

        $res = [];
        try {
            $res = $this->getTransport()->request('/'.$endpoint.'/'.$id, $attributes, 'put') ?: [];
        } catch (\Exception $e) {
            // 
        }

        return $this->setCache($key, $res);
    }

    /**
     * Call API for create an entity.
     *
     * @param string $endpoint
     * @param        $attributes
     *
     * @return array
     */
    protected function create($endpoint, $attributes)
    {
        try {
            return $this->getTransport()->request('/'.$endpoint, $attributes, 'post') ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Call API for delete an entity.
     *
     * @param string $endpoint
     * @param int    $id
     *
     * @return array
     */
    protected function delete($endpoint, $id)
    {
        $key = $endpoint.'/'.$id.'?';
        $this->deleteCache($key);

        try {
            return $this->getTransport()->request('/'.$endpoint.'/'.$id, [], 'delete') ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
