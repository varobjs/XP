<?php

namespace Varobj\XP\Service;

use Phalcon\Di\FactoryDefault;
use Varobj\XP\Application;

class UserService
{
    /**
     * 登陆用户名
     * @var string
     */
    public static $user_name;

    /**
     * 当前使用的请求token
     * @var string
     */
    public static $access_token;

    public static function setUserName(string $user_name): void
    {
        if (static::$user_name) {
            return;
        }

        static::$user_name = $user_name;
        /** @var Application $applicaiton */
        $applicaiton = FactoryDefault::getDefault()->getShared('application');
        $applicaiton->logger->setDefaultPrefix(
            sprintf(
                $applicaiton->logger->getDefaultPrefix(),
                static::$user_name
            )
        );
    }

    public static function setAccessToken(string $access_token): void
    {
        if (static::$access_token) {
            return;
        }
        static::$access_token = $access_token;
    }

    /**
     * 唯一请求ID
     *
     * @var string
     */
    protected static $request_id;

    public static function setRequestID(string $request_id = ''): void
    {
        if (static::$request_id) {
            return;
        }

        if ($request_id) {
            static::$request_id = $request_id;
            return;
        }

        $_string = getmygid() . microtime(true) . random_bytes(10);
        static::$request_id = strtoupper(substr(md5($_string), 4, 18)) . random_int(11, 99);
    }

    public static function getRequestID(): string
    {
        if (!static::$request_id) {
            static::setRequestID();
        }

        return static::$request_id;
    }
}