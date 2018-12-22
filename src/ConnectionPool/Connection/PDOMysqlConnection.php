<?php
/**
 * Created by PhpStorm.
 * User: chenmingming
 * Date: 2018/12/21
 * Time: 20:29
 */

namespace MMXS\Swoole\ConnectionPool\Connection;

class PDOMysqlConnection implements ConnectionInterface
{
    /**
     * @var PDOMysql
     */
    private $conn;
    /**
     * @var string
     */
    private $id;

    /**
     * PDOMysqlConnection constructor.
     *
     * @param PDOMysql $conn
     * @param string   $id
     */
    public function __construct(PDOMysql $conn, string $id)
    {
        $this->conn = $conn;
        $this->id   = $id;
    }

    public function release(): void
    {
        $this->conn->close();
    }

    public function getConnection()
    {
        return $this->conn;
    }

    public function getId(): string
    {
        return $this->id;
    }

}