<?php

namespace Varobj\XP;

use Phalcon\Storage\Adapter\Redis as PhalconRedis;
use Phalcon\Storage\Exception;
use Phalcon\Storage\SerializerFactory;
use Redis;
use Throwable;
use Varobj\XP\Exception\SystemConfigException;
use Varobj\XP\Exception\UsageErrorException;

/**
 * XRedis 工具类,单例模式 && 默认读取配置
 *
 * Usage:
 *
 * ```php
 * // 自定义配置
 * $redisWithConfig = XRedis::getInstance( $configParams );
 * // 默认配置 .env file ( REDIS_* )
 * $xRedis = XRedis::getInstance();
 * $redis = XRedis::getInstance()->redis
 * $predis = XRedis::getInstance()->predis;
 *
 * // 三者区别
 * // 1. $xRedis 高层,封装 lock unlock send recv 等复杂业务
 * // 1. $redis 中层,框架的实现
 * // 3. $predis 低层,基于 php-redis 支持多种命令
 *
 * // `set prefix:abc test 86400`其中`prefix:`为配置的`prefix`,`86400`依赖于配置的`lifetime`
 * $redis->set('abc', 'test');
 *
 * // `set abc test 10`
 * $predis->set('abc', 'test', 10)
 * $predis->set('key', 'value', ['nx', 'ex' => 10])
 * $predis->eval('luaScript', $arguments, $keySize);
 * ```
 */
class XRedis
{
    use Instance;

    public $redis;
    /**
     * @var Redis
     */
    public $predis;

    /**
     * XRedis constructor.
     * @param array $params
     * @throws Exception
     */
    public function __construct(array $params = [])
    {
        $options = [
            'host' => $params['host'] ?? config('redis.host'),
            'port' => (int)($params['port'] ?? config('redis.port')),
            'prefix' => $params['prefix'] ?? config('redis.prefix'),
            'index' => (int)($params['index'] ?? config('redis.index')),
            'persistent' => $params['persistent'] ?? false,
            'lifetime' => $params['lifetime'] ?? 3600,
            'defaultSerializer' => $params['defaultSerializer'] ?? 'none',
        ];
        if (empty($options['host']) || empty($options['port'])) {
            throw (new SystemConfigException('Redis配置缺失'))->debug($options);
        }
        $options['prefix'] and $options['prefix'] = rtrim($options['prefix'], ':') . ':';
        $options['auth'] = $params['password'] ?? config('redis.password');
        $this->redis = new PhalconRedis(new SerializerFactory(), $options);
        $this->predis = $this->redis->getAdapter();
    }

    // 封装一些 Redis 常用的实现

    /**
     * Redis 实现单节点'分布式'锁
     *
     * usage:
     * ```php
     * // 支付锁
     * $redis = XRedis::getInstance();
     *
     * $lockName = 'pay:201905120001';
     * $lockValue = $redis->lock($lockName);
     * if (!$lockValue) {
     *     throw new Exception('正在支付中,请稍后');
     * }
     *
     * // logic code
     *
     * // 主动释放锁，不要依赖过期释放，效率太低
     * $redis->unLock($lockName, $lockValue);
     * ```
     * @param string $lockName
     * @param int $lockTime
     * @return bool|string 返回false自行决定如何后续处理，是抛出异常还是重试
     */
    public function lock(string $lockName, int $lockTime = 3)
    {
        $value = 'lv:' . time();
        // 加锁失败的解决方法主要有, 1. 抛出指定异常，供前端决定重试；2. 重试，容易导致响应加长
        // 这里不去重试，只返回 false 供上层应用决定
        return $this->predis->set(
            'lock:' . $lockName,
            $value,
            ['ex' => $lockTime, 'nx']
        ) ? $value : false;
    }

    /**
     * 任务结束释放锁
     * @param string $lockName
     * @param string $lockValue
     * @return bool
     */
    public function unLock(string $lockName, string $lockValue): bool
    {
        $luaScript = "if redis.call('get',KEYS[1]) == ARGV[1] then return redis.call('del',KEYS[1]) else return 0 end";
        return $this->predis->eval(
            $luaScript,
            [
                'lock:' . $lockName,
                $lockValue
            ],
            1
        );
    }

    /**
     * 简单的 Push/Pop 模式的队列
     * send() & recv()
     */
    protected $queue_name = '';

    public function queueName(string $queueName): void
    {
        $this->queue_name = $queueName;
    }

    /**
     * 发送消息
     * ```php
     * $redis = XRedis::getInstance();
     * $redis->queueName('my-queue');
     * $redis->send('hi world');
     * ```
     * @param string $value
     * @return bool|int
     * @throws Exception
     */
    public function send(string $value)
    {
        if (!$this->queue_name) {
            throw new UsageErrorException('must set queue name[0]');
        }
        $predis = $this->redis->getAdapter();
        $key = $this->redis->getPrefix() . 'queue:' . $this->queue_name;
        return $predis->lPush($key, $value);
    }

    protected $queue_consume;

    public function callback(callable $c): void
    {
        $this->queue_consume = $c;
    }

    /**
     * 接收消息、常驻进程
     * ```php
     * class MyClass {
     *   function main() {
     *      $redis = XRedis::getInstance();
     *      $redis->queueName('my-queue');
     *      $redis->callback([new self(), 'consume']);
     *   }
     *
     *   function consume(string $message) {
     *      echo $message . PHP_EOL;
     *   }
     * }
     * ```
     * @param bool $verbose
     * @throws Exception
     */
    public function recv(bool $verbose = false): void
    {
        if (!$this->queue_name) {
            throw new UsageErrorException('must set queue name[1]');
        }
        if (!$this->queue_consume) {
            throw new UsageErrorException('must set callback for queue consumer');
        }
        $predis = $this->redis->getAdapter();
        $key = $this->redis->getPrefix() . 'queue:' . $this->queue_name;
        if ($verbose) {
            echo date('Y-m-d H:i:s') . ' start recv' . PHP_EOL;
        }
        try {
            while ($result = $predis->brPop($key, 30)) {
                if (!empty($result[1])) {
                    if ($verbose) {
                        echo date('Y-m-d H:i:s') . ' recv msg: ' . $result[1] . PHP_EOL;
                    }
                    call_user_func($this->queue_consume, $result[1]);
                }
            }
            if (empty($result)) {
                if ($verbose) {
                    echo 'pop timeout wait for 3 seconds ..' . PHP_EOL;
                }
                sleep(3);
                $this->recv($verbose);
            }
        } catch (Throwable $e) {
            if ($verbose) {
                echo 'socket timeout wait for 5 seconds ..' . PHP_EOL;
            }
            sleep(5);
            $this->recv($verbose);
        }
    }

    public function getOriginKey(string $key): string
    {
        return $this->redis->getPrefix() . $key;
    }
}
