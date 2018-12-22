<?php
/**
 * Created by PhpStorm.
 * User: chenmingming
 * Date: 2018/12/18
 * Time: 17:21
 */

namespace MMXS\Swoole\ConnectionPool\Connection;

class Redis
{
    private $redis;
    private $options
        = [
            'host'      => '127.0.0.1',
            'port'      => 6379,
            'password'  => '',
            'timeout'   => 1,
            'try_times' => 3
        ];

    public function __construct(array $options)
    {
        $this->options = array_merge($this->options, $options);
        $this->connect();
    }

    /**
     * connect
     *
     * @author chenmingming
     * @throws \RedisException
     */
    public function connect()
    {
        $redis = new \Redis();

        $isConnected = $redis->connect($this->options['host'], $this->options['port'], $this->options['timeout']);
        if (!$isConnected) {
            throw new \RedisException("connect failed");
        }
        if ($this->options['password']) {
            $isOk = $redis->auth($this->options['password']);
            if ($isOk === false) {
                throw new \RedisException('redis connect failed: auth check failed. error :' . $redis->getLastError());
            }
        }
        $this->redis = $redis;
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        for ($try = 0; $try < $this->options['try_times']; $try++) {
            if (!$this->redis) {
                if ($try > 0) {
                    usleep(10000);
                }
                try {
                    $this->connect();
                } catch (\RedisException $e) {
                    trigger_error("redis connect failed:" . $e->getMessage(), E_USER_WARNING);

                    continue;
                }
            }
            try {
                return call_user_func_array([$this->redis, $name], $arguments);
            } catch (\RedisException $e) {
                trigger_error("redis exec {$name} failed:" . $e->getMessage(), E_USER_WARNING);
                $this->redis = null;
            }
        }

        return false;
    }
}