<?php
/**
 * Created by PhpStorm.
 * User: chenmingming
 * Date: 2018/12/18
 * Time: 16:39
 */

namespace MMXS\Swoole\ConnectionPool;

use MMXS\Swoole\ConnectionPool\Connection\ConnectionInterface;

interface ConnectionPoolInterface
{
    public function pop(): ConnectionInterface;
    /**
     * @param ConnectionInterface $connection
     */
    public function put(ConnectionInterface $connection): void;

    public function length(): int;

    public function stats(): array;

    public function healthCheck(): void;

    /**
     * @param ConnectionInterface $connection
     */
    public function remove(ConnectionInterface $connection): void;
}