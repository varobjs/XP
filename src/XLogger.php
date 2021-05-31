<?php

namespace Varobj\XP;

use Varobj\XP\Exception\SystemConfigException;

use function is_array;
use function is_object;

class XLogger
{
    /**
     * 日志文件夹路径
     * @var string $path
     */
    protected $log_path;

    protected $default_prefix = '';

    /**
     * 打开文件句柄
     */
    protected $fp = [];

    public function __construct(string $log_path = '')
    {
        $this->log_path = $log_path ?: STORAGE_PATH . '/logs';
        // 第一次创建文件夹
        if (!is_dir($this->log_path) && !mkdir($this->log_path) && !is_dir($this->log_path)) {
            throw (new SystemConfigException(
                '创建日志目录失败：dir[' . $this->log_path . ']'
            ));
        }
        if (!is_writable($this->log_path)) {
            throw (new SystemConfigException(
                '日志文件夹必须可写：dir[' . $this->log_path . ']'
            ));
        }
    }

    /**
     * @param mixed $message 需要记录的消息
     * @param string $userPrefix '自定义的前缀，便于查询'
     * @param int $location '日志最后打上的 file & line, 即trace的位置，默认0表示当前函数调用的地方'
     * @return mixed|void
     */
    public function fatal($message, string $userPrefix = '', int $location = 0)
    {
        $_message = $this->_getMessageStr($message);
        if (strpos($_message, '[') !== 0) {
            $_message = $this->getDefaultPrefix() . $userPrefix . '[TY:FATAL] ' . $_message;
        } else {
            $_message = $userPrefix . '[TY:FATAL]' . $_message;
        }
        $this->logInternal($_message, 'F', '', $location);
    }

    /**
     * @param mixed $message 需要记录的消息
     * @param string $userPrefix '自定义的前缀，便于查询'
     * @param int $location '日志最后打上的 file & line, 即trace的位置，默认0表示当前函数调用的地方'
     * @return mixed|void
     */
    public function error($message, string $userPrefix = '', int $location = 0)
    {
        $_message = $this->_getMessageStr($message);
        if (strpos($_message, '[') !== 0) {
            $_message = $this->getDefaultPrefix() . $userPrefix . '[TY:ERROR] ' . $_message;
        } else {
            $_message = $userPrefix . '[TY:ERROR]' . $_message;
        }
        $this->logInternal($_message, 'E', '', $location);
    }

    /**
     * @param mixed $message 需要记录的消息
     * @param string $userPrefix '自定义的前缀，便于查询'
     * @param int $location '日志最后打上的 file & line, 即trace的位置，默认0表示当前函数调用的地方'
     * @return mixed|void
     */
    public function debug($message, string $userPrefix = '', int $location = 0)
    {
        $_message = $this->_getMessageStr($message);
        if (strpos($_message, '[') !== 0) {
            $_message = $this->getDefaultPrefix() . $userPrefix . '[TY:DEBUG] ' . $_message;
        } else {
            $_message = $userPrefix . '[TY:DEBUG]' . $_message;
        }
        $this->logInternal($_message, 'D', '', $location);
    }

    /**
     * @param mixed $message 需要记录的消息
     * @param string $userPrefix '自定义的前缀，便于查询'
     * @param int $location '日志最后打上的 file & line, 即trace的位置，默认0表示当前函数调用的地方'
     * @return mixed|void
     */
    public function warn($message, string $userPrefix = '', int $location = 0)
    {
        $_message = $this->_getMessageStr($message);
        if (strpos($_message, '[') !== 0) {
            $_message = $this->getDefaultPrefix() . $userPrefix . '[TY:WARN] ' . $_message;
        } else {
            $_message = $userPrefix . '[TY:WARN]' . $_message;
        }
        $this->logInternal($_message, 'W', '', $location);
    }

    /**
     * @param mixed $message 需要记录的消息
     * @param string $userPrefix '自定义的前缀，便于查询'
     * @param int $location '日志最后打上的 file & line, 即trace的位置，默认0表示当前函数调用的地方'
     * @return mixed|void
     */
    public function notice($message, string $userPrefix = '', int $location = 0)
    {
        $_message = $this->_getMessageStr($message);
        if (strpos($_message, '[') !== 0) {
            $_message = $this->getDefaultPrefix() . $userPrefix . '[TY:WARN] ' . $_message;
        } else {
            $_message = $userPrefix . '[TY:WARN]' . $_message;
        }
        $this->logInternal($_message, 'N', '', $location);
    }

    /**
     * @param mixed $message 需要记录的消息
     * @param string $userPrefix '自定义的前缀，便于查询'
     * @param int $location '日志最后打上的 file & line, 即trace的位置，默认0表示当前函数调用的地方'
     * @return mixed|void
     */
    public function info($message, string $userPrefix = '', int $location = 0)
    {
        $_message = $this->_getMessageStr($message);
        if (strpos($_message, '[') !== 0) {
            $_message = $this->getDefaultPrefix() . $userPrefix . '[TY:INFO] ' . $_message;
        } else {
            $_message = $userPrefix . '[TY:INFO]' . $_message;
        }
        $this->logInternal($_message, 'I', '', $location);
    }

    /**
     * @param mixed $message '需要记录的消息'
     * @param string $type '日志类型 [yyyy-mm-dd h:i:s][type]'
     * @param string $file '指定日志文件地址 相对 log_path 的地址，或者 / 开头的绝对地址'
     * @param string $userPrefix '自定义的前缀，便于查询'
     * @param int $location 日志最后打上的 file & line, 即trace的位置
     *                  <ul><li>默认0，表示当前函数调用的地方</li>
     *                  <li>正数时，表示不记录尾部位置</li>
     *                  <li>负数时，表示trace向上指定层</li></ul>
     */
    public function other(
        $message,
        string $type,
        string $file,
        string $userPrefix = '',
        int $location = 0
    ): void
    {
        $_message = $this->_getMessageStr($message);
        if (strpos($_message, '[') !== 0) {
            $_message = $this->getDefaultPrefix() . $userPrefix . '[TY:' . $type . '] ' . $_message;
        } else {
            $_message = $userPrefix . '[TY:' . $type . ']' . $_message;
        }
        if (strpos($file, '/') !== 0) {
            $file = rtrim($this->log_path, '/') . '/' . $file;
        }
        $this->logInternal($_message, 'O', $file, $location);
    }

    public function getDefaultPrefix(): string
    {
        if ($this->default_prefix) {
            return str_replace(
                ['[datetime]', '[pid]'],
                ['[DT:' . get_datetime_micro() . ']', '[PID:' . getmypid() . ']'],
                $this->default_prefix
            );
        }
        return '[DT:' . get_datetime_micro() . ']';
    }

    public function setDefaultPrefix(string $prefix): void
    {
        $this->default_prefix = $prefix;
    }

    /**
     * @param string $message
     * @param string $type
     * @param string $file
     * @param int $location 尾部定位的位置 0 函数调用位置，-1 则向上一层, 正数表示不记录尾部位置
     */
    protected function logInternal(
        string $message,
        string $type,
        $file = '',
        int $location = 1
    ): void
    {
        $location = 1 - $location;
        if ($location > 0) {
            $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            foreach ($traces as $i => $trace) {
                if ($i < $location) {
                    continue;
                }
                if (empty($trace['class'])) {
                    continue;
                }
                if (strpos($trace['file'] ?? '', APP_PATH) === 0) {
                    $message .= ' [' . substr(
                            ($trace['file'] ?? $trace['class'] ?? ''),
                            defined('BASE_DIR') ? strlen(BASE_DIR) : 0
                        ) . '@' . ($trace['line'] ?? $trace['function'] ?? '') . ']';
                    break;
                }
            }
        }

        $fp = null;
        switch ($type) {
            case 'F':
                if (empty($this->fp['F'])) {
                    $this->fp['F'] = fopen($this->getLogFile('fatal'), 'ab+');
                }
                $fp = $this->fp['F'];
                break;
            case 'E':
                if (empty($this->fp['E'])) {
                    $this->fp['E'] = fopen($this->getLogFile('error'), 'ab+');
                }
                $fp = $this->fp['E'];
                break;
            case 'W':
                if (empty($this->fp['W'])) {
                    $this->fp['W'] = fopen($this->getLogFile('warn'), 'ab+');
                }
                $fp = $this->fp['W'];
                break;
            case 'D':
                if (empty($this->fp['D'])) {
                    $this->fp['D'] = fopen($this->getLogFile('debug'), 'ab+');
                }
                $fp = $this->fp['D'];
                break;
            case 'N':
                if (empty($this->fp['N'])) {
                    $this->fp['N'] = fopen($this->getLogFile('notice'), 'ab+');
                }
                $fp = $this->fp['N'];
                break;
            case 'O':
                $md5 = 'O' . md5($file);
                if (empty($this->fp[$md5])) {
                    if (!is_file($file)) {
                        !@mkdir(dirname($file), '0777', true) && !is_dir(dirname($file));
                        touch($file);
                    }
                    if (!is_file($file)) {
                        tmp_log(
                            '[FILE.LOGGER] 创建日志文件失败，或者不可写 file:' . $file,
                            true
                        );
                    }
                    $this->fp[$md5] = fopen($file, 'ab+');
                }
                $fp = $this->fp[$md5];
                break;
            case 'I':
            default:
                if (empty($this->fp['I'])) {
                    $this->fp['I'] = fopen($this->getLogFile('info'), 'ab+');
                }
                $fp = $this->fp['I'];
                break;
        }
        // dev 模式可以只通过 /tmp/BaseApp_error.log 快速查看问题
        if (is_dev() && ErrorHandler::hasHandle()) {
            tmp_log($message);
        }

        if (@fwrite($fp, $message . PHP_EOL) === false) {
            tmp_log('[FILE.LOGGER] 写日志到文件失败');
        }
    }

    public function getLogPath(): string
    {
        return $this->log_path;
    }

    public function setLogPath(string $path): void
    {
        $this->log_path = $path;
    }

    protected function getLogFile($type): string
    {
        if (PHP_SAPI === 'cli') {
            $type .= '-cli';
        }
        return rtrim($this->log_path, '/') . '/' . $type . '.log';
    }

    protected function _getMessageStr($message): string
    {
        if (is_array($message)) {
            return 'array: ' . json_encode_utf8($message);
        }
        if (is_object($message)) {
            return 'object: ' . serialize($message);
        }

        return $message;
    }
}