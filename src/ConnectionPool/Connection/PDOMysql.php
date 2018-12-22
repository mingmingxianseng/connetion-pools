<?php
/**
 * Created by PhpStorm.
 * User: chenmingming
 * Date: 2018/12/20
 * Time: 19:31
 */

namespace MMXS\Swoole\ConnectionPool\Connection;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PDOMysql
{
    /**
     * @var \PDO
     */
    private $conn;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $options
        = [
            'host'                => '127.0.0.1',
            'port'                => 3306,
            'dbname'              => '',
            'user'                => '',
            'password'            => '',
            'charset'             => 'utf8',
            'unix_socket'         => '',
            'attrs'               => [],
            'logging'             => false,
            // 重连次数
            'reconnect_times'     => 3,
            // 每次重连等待时间
            'reconnect_wait_time' => 1,
        ];

    public function __construct(array $options, LoggerInterface $logger = null)
    {
        foreach ($options as $k => $v) {
            if (isset($this->options[$k])) {
                $this->options[$k] = $v;
            } else {
                if (substr($k, 0, 5) === 'PDO::') {
                    $key = constant($k);
                    if (empty($key)) {
                        throw new \InvalidArgumentException("undefined mysql attribute '{$k}'.");
                    }
                } else {
                    $key = $k;
                }
                $this->options['attrs'][$key] = $v;
            }
        }
        if ($logger === null) {
            $logger                   = new NullLogger();
            $this->options['logging'] = false;
        }
        $this->logger = $logger;
        $this->connect();
    }

    public function connect()
    {
        $this->conn = new \PDO(
            $this->constructPdoDsn($this->options),
            $this->options['user'],
            $this->options['password'],
            $this->options['attrs']
        );
        foreach ($this->options['attrs'] as $k => $v) {
            $this->conn->setAttribute($k, $v);
        }
        $this->conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function close()
    {
        $this->conn = null;
    }

    /**
     * Constructs the MySql PDO DSN.
     *
     * @param array $params
     *
     * @return string The DSN.
     */
    protected function constructPdoDsn(array $params)
    {
        $dsn = 'mysql:';
        if (isset($params['host']) && $params['host'] != '') {
            $dsn .= 'host=' . $params['host'] . ';';
        }
        if (isset($params['port'])) {
            $dsn .= 'port=' . $params['port'] . ';';
        }
        if (isset($params['dbname'])) {
            $dsn .= 'dbname=' . $params['dbname'] . ';';
        }
        if (isset($params['unix_socket']) && $params['unix_socket']) {
            $dsn .= 'unix_socket=' . $params['unix_socket'] . ';';
        }
        if (isset($params['charset'])) {
            $dsn .= 'charset=' . $params['charset'] . ';';
        }

        return $dsn;
    }

    public function getConnection()
    {
        return $this->conn;
    }

    /**
     * @param string|null $name
     *
     * @return string
     */
    public function lastInsertId(string $name = null)
    {
        return $this->conn->lastInsertId($name);
    }

    public function execute($sql, array $parameters = [])
    {
        $this->logger->debug($sql, $parameters);

        return $this->reconnect(
            function () use ($sql, $parameters) {
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($parameters);

                return $stmt->rowCount();
            }
        );
    }

    /**
     * @param string $sql
     * @param array  $parameters
     *
     * @return array
     */
    public function fetchAll(string $sql, array $parameters = [])
    {
        $this->logger->debug($sql, $parameters);

        return $this->reconnect(
            function () use ($sql, $parameters) {
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($parameters);

                return $stmt->fetchAll();
            }
        );
    }

    /**
     * @param string $sql
     * @param array  $parameters
     *
     * @return array
     */
    public function fetch(string $sql, array $parameters = [])
    {
        $this->logger->debug($sql, $parameters);

        return $this->reconnect(
            function () use ($sql, $parameters) {
                $stmt = $this->conn->prepare($sql);
                $stmt->execute($parameters);

                return $stmt->fetch();
            }
        );
    }

    private function reconnect(callable $func)
    {
        for ($i = 0; $i < $this->options['reconnect_times']; $i++) {
            $this->conn || $this->init();
            try {
                return $func();
            } catch (\PDOException $e) {
                if (!$this->needReconnect($e->getMessage())) {
                    throw $e;
                }
            }
            sleep($this->options['reconnect_wait_time']);
        }
        throw new \PDOException("reconnect times reach the limit {$this->options['reconnect_times']}.", 500, $e);
    }

    private function needReconnect(string $errorMsg)
    {
        if (strpos($errorMsg, 'MySQL server has gone away') !== false) {
            $this->logger->info('catch the exception.' . $errorMsg);
            $this->conn = null;

            return true;
        } else {
            return false;
        }
    }

}