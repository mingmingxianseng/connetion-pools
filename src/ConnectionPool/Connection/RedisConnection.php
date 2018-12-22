<?php
/**
 * Created by PhpStorm.
 * User: chenmingming
 * Date: 2018/12/18
 * Time: 19:17
 */

namespace MMXS\Swoole\ConnectionPool\Connection;

class RedisConnection implements ConnectionInterface
{
    /**
     * @var \Redis
     */
    private $redis;
    /**
     * @var string
     */
    private $id;

    public function __construct(Redis $redis, string $id = null)
    {
        $this->redis = $redis;
        $id === null && $id = uniqid();
        $this->id = $id;
    }

    public function getConnection()
    {
        return $this->redis;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function release(): void
    {
        $this->redis->close();
    }


}