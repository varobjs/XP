<?php

namespace Varobj\XP;

use MongoDB\Client;
use MongoDB\Collection;
use Varobj\XP\Exception\UsageErrorException;

/**
 * Class MongoModel
 *
 * 使用前需要引入
 *
 * composer require mongodb/mongodb:^1.6
 *
 * @package Varobj\XP
 */
abstract class MongoModel
{
    public function __construct(array $params = [])
    {
        static::connection($params);
    }

    abstract public static function collection(): string;

    /**
     * 子类设置 database 可以覆盖链接中配置的默认 database
     * @return string
     */
    public static function database(): string
    {
        return '';
    }

    protected static $_database;

    protected static function connection(array $params = []): void
    {
        $host = $params['host'] ?? env('mongodb.host');
        $port = $params['port'] ?? env('mongodb.port');
        $username = $params['username'] ?? env('mongodb.username');
        $password = $params['password'] ?? env('mongodb.password');
        static::$_database = $params['database'] ?? env('mongodb.database') ?? '';
        $auth = '';
        $authM = '';
        if ($username && $password) {
            $auth = $username . ':' . $password . '@';
            $authM = '?authMechanism=SCRAM-SHA-1';
        }
        if (!$host || !$port) {
            throw new UsageErrorException('mongodb config error');
        }

        static::$client = new Client(
            sprintf(
                'mongodb://%s%s:%s/%s%s',
                $auth,
                $host,
                $port,
                static::$_database,
                $authM
            )
        );
    }

    /**
     * @var Client
     */
    protected static $client;

    /**
     *  返回 collection
     * @param array $params
     * @return Collection
     */
    public static function new(array $params = []): ?Collection
    {
        if (null === static::$client) {
            static::connection($params);
        }
        if (!static::$_database && !static::database()) {
            throw new UsageErrorException('必须设置database');
        }
        if (!static::collection()) {
            throw new UsageErrorException('必须设置collection');
        }

        if (static::database()) {
            return static::$client
                ->selectDatabase(static::database())
                ->selectCollection(static::collection());
        }

        return static::$client->selectCollection(static::$_database, static::collection());
    }

    public function getClient(array $params = []): Client
    {
        if (null === static::$client) {
            static::connection($params);
        }

        return static::$client;
    }
}