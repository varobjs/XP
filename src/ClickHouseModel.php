<?php

namespace Varobj\XP;

use ClickHouseDB\Client;
use ClickHouseDB\Statement;
use Varobj\XP\Exception\UsageErrorException;

/**
 * Class ClickHouse
 *
 * 使用前需要引入
 *
 *  composer require smi2/phpclickhouse:^1.3
 *
 * @package Varobj\XP
 */
abstract class ClickHouseModel
{
    use Instance;

    /**
     * @var Client
     */
    public static $client;

    public function __construct(array $params = [])
    {
        self::connection($params);
    }

    public static function new(array $params = []): self
    {
        return new static($params);
    }

    public static function connection(array $params = []): void
    {
        if (null !== static::$client) {
            return;
        }
        $host = $params['host'] ?? env('beanstalkd.host') ?? '127.0.0.1';
        $port = $params['port'] ?? env('beanstalkd.port') ?? '8123';
        $username = $params['username'] ?? env('clickhouse.username') ?? '';
        $password = $params['password'] ?? env('clickhouse.password') ?? '';
        $database = static::database() ?: $params['database'] ??
            env('clickhouse.database') ?? 'default';
        if (!$host || !$port) {
            throw new UsageErrorException('clickhouse配置有误');
        }
        if (is_prod() && (!$username || !$password)) {
            throw new UsageErrorException('clickhouse线上必须要配置用户名和密码');
        }

        static::$client = new Client(
            [
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'password' => $password
            ]
        );
        static::$client->database($database);
        static::$client->setConnectTimeOut(3);
        static::$client->setTimeout(60);
    }

    // 指定database 和 table
    abstract public static function database(): string;

    abstract public static function table(): string;


    public function getConnection(): Client
    {
        return static::$client;
    }

    /**
     * @param mixed[][] $values
     * @param string[] $columns
     * @return Statement
     */
    public function insert(array $values, array $columns = []): Statement
    {
        return static::$client->insert(
            static::table(),
            $values,
            $columns
        );
    }
}