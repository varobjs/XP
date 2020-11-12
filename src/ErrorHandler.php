<?php

namespace Varobj\XP;

use Error;
use Exception;
use Phalcon\Di\Injectable;
use Throwable;
use Varobj\XP\Exception\BaseException;
use Varobj\XP\Exception\NoticeException;
use Varobj\XP\Exception\UndefinedException;
use Varobj\XP\Service\UserService;
use Varobj\XP\Struct\ApiResult;

use function in_array;

class ErrorHandler extends Injectable
{
    protected static $has_error = false;
    protected static $has_handle = false;
    public static $turn_undefined_index_to_exception = true;
    public static $all_is_error = false;

    public function register(): void
    {
        // 处理 php 致命错误
        set_error_handler(
            function ($errorNo, $errorMsg, $errorFile, $errorLine): bool {
                // 错误控制符@，可以把当前的的错误标记改成 0
                if ($errorNo !== 0 && !($errorNo & error_reporting())) {
                    return true;
                }
                static::$has_error = !in_array(
                    $errorNo,
                    [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE],
                    true
                );
                if (!static::$has_error && static::$turn_undefined_index_to_exception
                    && preg_match('/^Undefined\sindex|offset/', $errorMsg)) {
                    throw new UndefinedException(
                        '{' . $errorMsg . '} at ' . $errorFile . '@' . $errorLine
                    );
                }
                if (!static::$has_error && static::$all_is_error) {
                    throw new NoticeException(
                        '{' . $errorMsg . '} at ' . $errorFile . '@' . $errorLine
                    );
                }

                $traces = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 10);
                // 去掉与当前的
                array_shift($traces);

                $this->handle(
                    [
                        'e_src' => 'error',
                        'e_no' => $errorNo,
                        'e_msg' => $errorMsg,
                        'e_pos' => $errorFile . '@' . $errorLine,
                        'e_trace' => $traces,
                        'no_interrupt' => !self::$has_error,
                        'errno' => 500,
                        'errmsg' => !is_prod() ? $errorMsg : '系统错误',
                        'ignore_log' => false,
                    ]
                );
                return true;
            }
        );

        // 统一处理 接口异常 处理, 不管什么异常，都会输出接口
        set_exception_handler(
            function (Throwable $e): void {
                try {
                    /** @var Exception|Error $e */
                    $traces = $e->getTraceAsString();
                    $biz_code = $e->getCode();
                    $biz_msg = $e->getMessage();
                    if ($e instanceof BaseException) {
                        $debug = $e->getDebug();
                        $ignore = $e->getIgnore();
                        static::$has_error = $e->isError();
                        !is_prod() and $biz_msg .= ' | debug: ' . json_encode($debug);
                    } else {
                        static::$has_error = true;
                        $debug = [$biz_msg];
                        is_prod() and $biz_msg = '系统异常';
                        $ignore = false;
                    }

                    $this->handle(
                        [
                            'e_src' => 'exception',
                            'e_no' => $biz_code,
                            'e_msg' => $e->getMessage() .
                                ($debug ? ' | debug: ' . json_encode($debug) : ''),
                            'e_pos' => $e->getFile() . '@' . $e->getLine(),
                            'e_trace' => $traces,
                            'errno' => $biz_code,
                            'errmsg' => $biz_msg,
                            'ignore_log' => $ignore,
                        ]
                    );
                } catch (Throwable $exception) {
                    die(
                        'INNER_ERROR: ' . $exception->getMessage() . ' in ' .
                        $exception->getFile() . '@' . $exception->getLine()
                    );
                }
            }
        );

        register_shutdown_function(// Access level to xxx must be public 类的错误.
            function (): void {
                if (($options = error_get_last()) !== null) {
                    static::$has_error = true;
                    $this->handle(
                        [
                            'e_src' => 'shutdown',
                            'e_no' => $options['type'],
                            'e_msg' => $options['message'],
                            'e_pos' => $options['file'] . '@' . $options['line'],
                            'e_trace' => debug_backtrace(-1),
                            'errno' => 599,
                            'errmsg' => !is_prod() ? $options['message'] : '系统中断',
                            'ignore_log' => false,
                        ]
                    );
                }
            }
        );
    }

    /**
     * 统一处理 错误输出 & 日志
     * @param array $error
     * @throws Exception
     */
    public function handle(array $error): void
    {
        if (!static::$has_handle) {
            static::$has_handle = true;
        }

        if (PHP_SAPI !== 'cli') {
            if (empty($error['no_interrupt']) && $this->response && $this->response->getContent()) {
                !$this->response->isSent() and $this->response->setStatusCode(200)->send();
            } elseif (empty($error['no_interrupt'])) {
                $this->response->setJsonContent(
                    (new ApiResult(
                        self::$has_error ? 'error' : 'warning',
                        (int)($error['errno'] ?? -1)
                    ))->setMessage($error['errmsg'] ?? '')->toArray()
                )->setStatusCode(200)->send();
            }
        } else {
            $_error = $error;
            unset($_error['e_trace']);
            echo json_encode(
                [
                    'status' => 'error',
                    'error' => $_error
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
        }
        if (!empty($error['ignore_log'])) {
            return;
        }
        unset($error['ignore_log'], $error['errno'], $error['errmsg']);
        $error = array_merge(
            $error,
            [
                'req.id' => UserService::getRequestID(),
                'req.uri' => PHP_SAPI !== 'cli' ? ($_SERVER['REQUEST_URI'] ?? '') : '',
                'req.method' => PHP_SAPI !== 'cli' ? $this->request->getMethod() : '',
                'req.query' => PHP_SAPI !== 'cli' ? json_encode($this->request->getQuery()) : '',
                'req.ip' => PHP_SAPI !== 'cli' ? $this->request->getClientAddress() : '',
                'req.agent' => PHP_SAPI !== 'cli' ? $this->request->getUserAgent() : '',
            ]
        );
        $_err_str = json_encode_utf8($error);
        $_err_str = str_replace(
            ['\\\\', '\\"'],
            ['\\', '\''],
            $_err_str
        );

        /** @var XLogger $logger */
        $logger = $this->di->getShared('logger');

        if (!static::$has_error) { // warning log
            $logger->warn($_err_str);
        } else {
            $logger->error($_err_str);
        }
    }

    public static function hasHandle(): bool
    {
        return static::$has_handle;
    }
}
