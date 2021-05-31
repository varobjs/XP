<?php

namespace Varobj\XP;

trait Instance
{
    protected static $_singletonStack = [];

    /**
     * @param mixed $params
     * @param bool $refresh
     * @return static
     */
    public static function getInstance($params = null, bool $refresh = false): self
    {
        $class = static::class;
        $key = md5($class . serialize($params));
        if (!$refresh && !empty(static::$_singletonStack[$key])) {
            return static::$_singletonStack[$key];
        }

        if ($params) {
            static::$_singletonStack[$key] = new $class($params);
        } else {
            static::$_singletonStack[$key] = new $class();
        }
        return static::$_singletonStack[$key];
    }
}