<?php
/**
 * Created by PhpStorm.
 * User: chenmingming
 * Date: 2018/12/21
 * Time: 20:28
 */

namespace MMXS\Swoole\ConnectionPool;

use MMXS\Swoole\ConnectionPool\Connection\ConnectionInterface;
use MMXS\Swoole\ConnectionPool\Connection\PDOMysql;
use MMXS\Swoole\ConnectionPool\Connection\PDOMysqlConnection;

class PDOMysqlPool extends AbstractConnectionPool
{
    protected function createNewOne(string $connectionId): ConnectionInterface
    {
        $urlInfo = parse_url($this->options['dsn']);
        $options = [
            'host'     => $urlInfo['host'],
            'port'     => $urlInfo['port'] ?? 3306,
            'user'     => $urlInfo['user'] ?? '',
            'password' => $urlInfo['pass'] ?? '',
            'dbname'   => substr($urlInfo['path'], 1),
            'attrs'    => $this->options['attrs'] ?? []
        ];

        if (!empty($urlInfo['query'])) {
            parse_str($urlInfo['query'], $queries);
            $options = array_merge($options, $queries);
        }

        $connection = new PDOMysqlConnection(new PDOMysql($options), $connectionId);

        $this->logger->debug("create a new PDOMysql connection#{$connectionId} success.");

        return $connection;
    }

}