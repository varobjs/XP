<?php

namespace Varobj\XP;

use Varobj\XP\Exception\UsageErrorException;
use xobotyi\beansclient\BeansClient;
use xobotyi\beansclient\Connection;
use xobotyi\beansclient\Serializer\JsonSerializer;

/**
 * Class Beastalkd
 *
 * 使用前需要引入
 *
 * composer require xobotyi/beansclient:^1.0
 *
 * @package Varobj\XP
 */
class Beanstalkd
{
    use Instance;

    /**
     * @var BeansClient
     */
    public $client;

    public function __construct(array $params = [])
    {
        $host = $params['host'] ?? env('beanstalkd.host') ?? '127.0.0.1';
        $port = $params['port'] ?? env('beanstalkd.port') ?? '11300';
        $timeout = $params['timeout'] ?? env('beanstalkd.timeout') ?? 2;
//        $persistent = $params['persistent'] ?? env('beanstalkd.persistent') ?? false;
        if (!$host || !$port || !$timeout) {
            throw new UsageErrorException('beanstalkd配置有误');
        }
        $connection = new Connection($host, $port, $timeout);
        $this->client = new BeansClient($connection);

        // 默认使用 Json 序列化
        $this->client->setSerializer(new JsonSerializer());
    }
}