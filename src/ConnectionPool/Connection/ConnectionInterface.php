<?php
/**
 * Created by PhpStorm.
 * User: chenmingming
 * Date: 2018/12/18
 * Time: 19:08
 */

namespace MMXS\Swoole\ConnectionPool\Connection;

interface ConnectionInterface
{
    /**
     * 释放该连接
     */
    public function release(): void;

    /**
     * 获取该连接
     *
     * @return mixed
     */
    public function getConnection();

    /**
     * 获取该资源id
     *
     * @return string
     */
    public function getId(): string;
}