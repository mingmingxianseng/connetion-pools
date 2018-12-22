<?php
/**
 * Created by PhpStorm.
 * User: chenmingming
 * Date: 2018/12/18
 * Time: 16:41
 */

namespace MMXS\Swoole\ConnectionPool;

use MMXS\Swoole\ConnectionPool\Connection\ConnectionInterface;
use MMXS\Swoole\ConnectionPool\Connection\RedisConnection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Swoole\Coroutine\Channel;

abstract class AbstractConnectionPool implements ConnectionPoolInterface
{
    /**
     * @var Channel
     */
    protected $pool;
    // 连接池初始化时间
    protected $startTime;
    // 正在使用的连接列表
    protected $usingConnections = [];
    // 所有连接列表
    protected $connections = [];
    // 连接计数
    protected $index = 0;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    // 配置
    protected $options
        = [
            // 连接 dsn配置
            'dsn'             => '',
            // 最大连接数量
            'max_count'       => 10,
            // 最少空闲数量
            'min_count'       => 3,
            // 每次重试等待时间 单位秒 必须大于等于 一毫秒 0.001
            'wait_second'     => 3,
            // 最大使用时间 单位秒
            'max_use_time'    => 1800,
            // 最大空闲时间
            'max_idle_time'   => 300,
            'connection_name' => 'CNT'
        ];

    public function __construct(array $options, LoggerInterface $logger = null)
    {
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger  = $logger;
        $this->options = array_merge($this->options, $options);
        $this->reset();
    }

    public function reset()
    {
        if ($this->pool) {
            $this->pool->close();
            $this->pool = null;
        }
        $this->startTime = time();
        $this->pool      = new Channel($this->options['max_count'] * 2);
        for ($i = 0; $i < $this->options['min_count']; $i++) {
            $id                     = $this->createId();
            $this->connections[$id] = time();
            $this->put($this->createNewOne($id));
        }
    }

    /**
     * @return string
     */
    protected function createId()
    {
        return $this->options['connection_name'] . '_' . ($this->index++);
    }

    abstract protected function createNewOne(string $connectionId): ConnectionInterface;

    /**
     * @return ConnectionInterface
     * @throws ConnectionPoolException
     */
    public function pop(): ConnectionInterface
    {

        $connection = null;
        if ($this->pool->length() <= 0 && count($this->connections) < $this->options['max_count']) {
            $id                     = $this->createId();
            $this->connections[$id] = time();
            $connection             = $this->createNewOne($id);
        }
        if (null === $connection) {
            /** @var RedisConnection $connection */
            $connection = $this->pool->pop($this->options['wait_second']);
            if ($connection instanceof ConnectionInterface) {
                if (!isset($this->connections[$connection->getId()])) {
                    $this->logger->debug("connection #{$connection->getId()} is invalid, prepare drop it");

                    return $this->pop();
                }
            } else {
                throw new ConnectionPoolException(
                    "get connection timeout was reached. wait {$this->options['wait_second']} second"
                );
            }
        }

        $this->usingConnections[$connection->getId()] = time();

        $this->logger->debug('pop connection #' . $connection->getId() . ' success. ');

        return $connection;

    }

    public function put(ConnectionInterface $connection): void
    {
        if (isset($this->usingConnections[$connection->getId()])) {
            // 正常回收
            unset($this->usingConnections[$connection->getId()]);
            $this->logger->debug("put connection #{$connection->getId()} back");
        } else {
            // 非正常回收连接 收回到连接到 id hash表中 并
            $this->logger->debug("put a new connection #{$connection->getId()}");
        }
        //更新最后时间
        $this->connections[$connection->getId()] = time();
        $this->pool->push($connection);
    }

    public function length(): int
    {
        return $this->pool->length();
    }

    public function stats(): array
    {
        return [
            'min'        => $this->options['min_count'],
            'max'        => $this->options['max_count'],
            'alive'      => count($this->connections),
            'used'       => count($this->usingConnections),
            'start_time' => $this->startTime,
            'total'      => $this->index
        ];
    }

    public function healthCheck(): void
    {
        $this->logger->debug('health check');
        // 健康检查
        // 如果有连接使用超过设定最大时间 则认为该连接已经丢弃 将当前连接数减一
        $currentTime = time();
        foreach ($this->usingConnections as $id => $useTime) {
            if ($currentTime - $useTime > $this->options['max_use_time']) {
                $this->logger->warning("connection #{$id} is timeout.");
                unset($this->usingConnections[$id]);
                unset($this->connections[$id]);
            }
        }

        // 释放空闲连接
        foreach ($this->connections as $id => $lastActiveTime) {
            if (isset($this->usingConnections[$id])) {
                continue;
            }
            if ($currentTime - $lastActiveTime > $this->options['max_idle_time']) {
                unset($this->connections[$id]);
                $this->logger->debug("connection #{$id} is idle, will release soon");
            }
        }

        // 检查是否有足够连接 如果不够则创建新连接push 入连接池
        while (count($this->connections) < $this->options['min_count']) {
            $this->logger->debug("soon a new connection will be created and put into the pool");
            $id                     = $this->createId();
            $this->connections[$id] = time();
            $this->put($this->createNewOne($id));
        }
    }

    public function remove(ConnectionInterface $connection): void
    {
        unset($this->connections[$connection->getId()]);
        unset($this->usingConnections[$connection->getId()]);
    }

}