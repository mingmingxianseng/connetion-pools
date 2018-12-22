<?php
/**
 * Created by PhpStorm.
 * User: chenmingming
 * Date: 2018/12/19
 * Time: 17:05
 */

include __DIR__ . '/../vendor/autoload.php';

Swoole\Runtime::enableCoroutine();

class LoggerA extends \Psr\Log\AbstractLogger
{
    public function log($level, $message, array $context = array())
    {
        list($msec, $sec) = explode(' ', microtime());
        $msec = substr($msec, 2);
        echo date('Y-m-d H:i:s', $sec) . ".{$msec}\t{$level} {$message} " . json_encode($context) . "\tC" . Co::getuid()
            . "\n";
    }

}

$server = new \Swoole\Http\Server('0.0.0.0', 9999);

function createRedis()
{

    return new RedisA(new \MMXS\Swoole\ConnectionPool\Connection\Redis([]));
}

class RedisA
{
    private $redis;

    public function __construct($redis)
    {
        $this->redis = $redis;
    }

    /**
     * @return Redis
     */
    public function getRedis()
    {
        return $this->redis;
    }
}

$logger = new LoggerA();
$server->on(
    'request', function (\Swoole\Http\Request $request, \Swoole\Http\Response $response) use ($logger) {
    static $redisPool;
    if ($redisPool === null) {
        $redisPool = new \Swoole\Coroutine\Channel(100);
        $redisPool->push(createRedis());
        $redisPool->push(createRedis());
        $redisPool->push(createRedis());
    }
    if ($request->server['request_uri'] === '/favicon.ico') {
        $response->status(404);
        $response->end();

        return;
    }
    $logger->info('uri:' . $request->server['request_uri']);

    /** @var RedisA $redisA */
    $redisA = $redisPool->pop();
    $redis  = $redisA->getRedis();

    $key = 'test_go_redis_pool_' . mt_rand(0, 10);
    $logger->debug('start set');
    $redis->set($key, '111111');
    $logger->debug('set success');

    $response->end($redis->get($key));
    $redisPool->push($redisA);

}
);

$server->start();