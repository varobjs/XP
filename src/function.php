<?php

use Phalcon\Di\FactoryDefault;
use Varobj\XP\Exception\SystemConfigException;
use Varobj\XP\XLogger;

/**
 * 配置读取
 */
if (!function_exists('config')) {
    function config($path, $default = null)
    {
        /** @var \Phalcon\Config $config */
        $config = get_service('config');
        return $config->path($path, $default);
    }
}

/**
 * 是否是开发环境
 * @return bool
 */
if (!function_exists('is_dev')) {
    function is_dev(): bool
    {
        return in_array(
            config('app_env'),
            ['dev', 'test']
        );
    }
}

/**
 * 是否是线上环境
 * @return bool
 */
if (!function_exists('is_prod')) {
    function is_prod(): bool
    {
        return in_array(
            config('app_env'),
            ['prod', 'beta', 'stage']
        );
    }
}

if (!function_exists('load_file_env')) {
    /**
     * 读取关键配置文件到环境变量
     * @param string $envFile
     */
    function load_file_env(string $envFile = ''): void
    {
        $conf = conf_from_file($envFile);
        foreach ($conf as $key => $value) {
            if (getenv($key)) {
                continue;
            }
            putenv("$key=$value");
        }
    }

    /**
     * @param string $envFile
     * @return array<string, mixed>
     */
    function conf_from_file(string $envFile = ''): array
    {
        !$envFile and $envFile = BASE_PATH . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envFile)) {
            throw new SystemConfigException('cannot found .env file');
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $_conf = [];
        foreach ($lines as $line) {
            $line = ltrim($line);
            if (strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                if (!$key) {
                    continue;
                }
                $_conf[$key] = $value;
            }
        }
        return $_conf;
    }
}

if (!function_exists('env')) {
    /**
     * @param string $key
     * @param null $default
     * @param bool $fromFile
     * @return array|bool|false|mixed|string|null
     */
    function env(string $key, $default = null, bool $fromFile = false)
    {
        $value = getenv($key);
        if ($value === false) {
            if (is_null($default)) {
                throw new SystemConfigException(sprintf(
                    "cannot found config[%s]",
                    $key
                ));
            }
            return ($default instanceof Closure) ? $default() : $default;
        }
        switch (strtolower($value)) {
            case 'true':
                $value = true;
                break;
            case 'false':
                $value = false;
                break;
            case 'empty':
                $value = '';
                break;
            case 'null':
                $value = null;
                break;
        }
        return $value;
    }
}

if (!function_exists('get_datetime_micro')) {
    function get_datetime_micro(): string
    {
        return date('Y-m-d H:i:s') . ' ' . str_pad(
                (int)(explode(' ', microtime())[0] * 1000),
                3,
                '0',
                STR_PAD_LEFT
            );
    }
}

if (!function_exists('json_encode_utf8')) {
    /**
     * @param array $arr
     * @return string
     */
    function json_encode_utf8(array $arr): string
    {
        $_str = json_encode(
            $arr,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            512
        );
        return $_str ?: '';
    }
}

if (!function_exists('XLog')) {
    function XLog(): XLogger
    {
        $di = Phalcon\Di\FactoryDefault::getDefault();
        return $di->getShared('logger');
    }
}

if (!function_exists('tmp_log')) {
    function tmp_log(string $msg, bool $isDie = false): void
    {
        $msg = sprintf('[%s] -> %s', date('Y-m-d H:i:s'), $msg) . PHP_EOL;
        $traces = debug_backtrace();
        $i = 0;
        $j = count($traces);
        foreach ($traces as $trace) {
            $i++;
            $msg .= '  #' . $i . ' [' . $trace['function'] . "]\t";
            if (isset($trace['line'])) {
                $msg .= ($i > 1 && isset($trace['args'])) ? '(' . json_encode_utf8(
                        $trace['args']
                    ) . ')' : '';
                $msg .= ' in ' . $trace['file'] . '@' . $trace['line'];
            } elseif (isset($trace['class'])) {
                $msg .= ' in ' . $trace['class'];
            }
            $msg .= $j > $i ? "\r\n" : '';
        }

        @file_put_contents(
            sys_get_temp_dir() . '/BaseApp_error.log',
            $msg . PHP_EOL . PHP_EOL,
            FILE_APPEND
        );
        $isDie and die($msg . PHP_EOL . PHP_EOL);
    }
}

if (!function_exists('_verbose')) {
    function _verbose(string $msg, string $type = ''): bool
    {
        global $_VERBOSE;
        if (!$_VERBOSE) {
            return false;
        }
        if (!$msg) {
            return true;
        }
        $green = "\33[1;38;2;33;223;23m";
        $red = "\33[1;38;2;255;0;0m";
        $yellow = "\33[1;38;2;255;227;132m";
        $clear = "\33[0m";
        if ($type === 'error') {
            echo '[verbose] ' . $red . $msg . $clear . PHP_EOL;
        } elseif ($type === 'success') {
            echo '[verbose] ' . $green . $msg . $clear . PHP_EOL;
        } elseif ($type === 'warning') {
            echo '[verbose] ' . $yellow . $msg . $clear . PHP_EOL;
        } else {
            echo '[verbose] ' . $msg . PHP_EOL;
        }
        return true;
    }
}

if (!function_exists('get_service')) {
    /**
     * @param string $service_name
     * @param bool $shared
     * @return mixed
     * @throws ErrorException
     */
    function get_service(string $service_name, bool $shared = true)
    {
        $di = FactoryDefault::getDefault();
        if (!$di->has($service_name)) {
            throw new ErrorException("服务不存在[{$service_name}]");
        }
        return $shared ? $di->getShared($service_name) : $di->get($service_name);
    }
}