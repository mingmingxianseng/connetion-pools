<?php
/**
 * Created by PhpStorm.
 * User: chenmingming
 * Date: 2018/12/20
 * Time: 19:24
 */

namespace MMXS\Swoole\ConnectionPool;

use MMXS\Swoole\ConnectionPool\Connection\ConnectionInterface;
use MMXS\Swoole\ConnectionPool\Connection\Redis;
use MMXS\Swoole\ConnectionPool\Connection\RedisConnection;

class RedisPool extends AbstractConnectionPool
{
    /**
     * @param string $connectionId
     *
     * @return ConnectionInterface
     */
    protected function createNewOne(string $connectionId): ConnectionInterface
    {
        $urlInfo = parse_url($this->options['dsn']);
        $options = [
            'host'     => $urlInfo['host'],
            'port'     => $urlInfo['port'] ?? 6379,
            'password' => $urlInfo['user'] ?? '',
        ];
        $options = array_merge($options, $urlInfo['query'] ?? []);

        $connection = new RedisConnection(new Redis($options), $connectionId);

        $this->logger->debug("create a new connection#{$connectionId} success.");

        return $connection;
    }

}